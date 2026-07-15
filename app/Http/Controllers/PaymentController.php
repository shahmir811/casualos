<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Services\OrderStatusNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Reverses a payment's ledger entry and its advance-credit-balance effects
     * (credit consumed + any overpayment surplus it created). Must be called
     * while $order->total_paid still includes this payment's current amount,
     * and before the payment row itself is deleted or its fields are changed —
     * shared by destroy() and update() as the "undo the old state" step.
     *
     * Restores exactly the credit portion this payment actually drew (derived
     * from its linked ledger entry, not the full amount) — an advance payment
     * split into credit + payment portions (rule 5.19) only ever consumed the
     * credit portion, so only that much should ever be given back.
     */
    private function reversePaymentContribution(Order $order, Payment $payment, Customer $customer): void
    {
        $oldSurplus = max(0, $order->total_paid - $order->total_amount);

        $ledgerEntry = DB::table('customer_ledger')
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->where('transaction_type', 'payment_received')
            ->first();

        $paymentPortion = $ledgerEntry ? abs($ledgerEntry->amount) : 0;

        if ($ledgerEntry) {
            DB::table('customer_ledger')->where('id', $ledgerEntry->id)->delete();
        }

        if ($payment->payment_type === 'advance') {
            $creditPortion = $payment->amount - $paymentPortion;
            if ($creditPortion > 0) {
                $customer->increment('advance_credit_balance', $creditPortion);
                $customer->refresh();
            }
        }

        // Surplus this payment contributed, reversed against total_paid as if this
        // payment's amount was never included — applies to every payment type,
        // including advance (previously only handled for cash/bank_transfer).
        $totalPaidExcluding  = $order->total_paid - $payment->amount;
        $surplusAfterRemoval = max(0, $totalPaidExcluding - $order->total_amount);
        $surplusReversed     = $oldSurplus - $surplusAfterRemoval;

        if ($surplusReversed > 0) {
            $customer->decrement('advance_credit_balance', min($surplusReversed, $customer->advance_credit_balance));
            $customer->refresh();
        }
    }

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_type'      => 'required|in:cash,bank_transfer,advance',
            'bank_account_id'   => 'required_unless:payment_type,advance|nullable|exists:bank_accounts,id',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'receipt_images'    => 'required_if:payment_type,bank_transfer|nullable|array|min:1',
            'receipt_images.*'  => 'file|mimes:pdf,jpeg,jpg,png,webp|max:5120',
        ]);

        $receiptPaths = null;
        if (in_array($request->payment_type, ['bank_transfer', 'advance']) && $request->hasFile('receipt_images')) {
            $receiptPaths = collect($request->file('receipt_images'))
                ->map(fn($file) => $file->store('receipts'))
                ->values()
                ->toArray();
        }

        $bankAccount = in_array($request->payment_type, ['cash', 'bank_transfer']) && $request->bank_account_id
            ? BankAccount::find($request->bank_account_id)
            : null;

        $titleGiven = match ($request->payment_type) {
            'bank_transfer' => $bankAccount?->title ?? 'Bank Transfer',
            'cash'          => $bankAccount ? 'Cash · ' . $bankAccount->title : 'Cash',
            'advance'       => 'Advance',
            default         => null,
        };

        $payment          = null;
        $wasAutoConfirmed = false;
        $surplus          = 0;

        DB::transaction(function () use ($request, $receiptPaths, $titleGiven, $order, &$payment, &$wasAutoConfirmed, &$surplus) {
            // Lock the order row so concurrent payments on the same order can't race
            // each other into computing the same next sequence number.
            Order::where('id', $order->id)->lockForUpdate()->first();
            $nextSequence = (Payment::where('order_id', $order->id)->max('sequence_number') ?? 0) + 1;

            $payment = Payment::create([
                'order_id'        => $order->id,
                'sequence_number' => $nextSequence,
                'customer_id'     => $order->customer_id,
                'amount'          => $request->amount,
                'payment_type'    => $request->payment_type,
                'bank_account_id' => in_array($request->payment_type, ['cash', 'bank_transfer']) ? $request->bank_account_id : null,
                'title_given'     => $titleGiven,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $receiptPaths,
                'logged_by'       => Auth::id(),
            ]);

            // Capture surplus that existed BEFORE this payment so we only increment the delta
            $oldSurplus = max(0, $order->total_paid - $order->total_amount);

            // Update order financials
            $newTotalPaid   = $order->total_paid + $request->amount;
            $newOutstanding = max(0, $order->total_amount - $newTotalPaid);
            $statusUpdate   = ['total_paid' => $newTotalPaid, 'outstanding_balance' => $newOutstanding];

            // Auto-confirm on first payment
            if ($order->status === 'received') {
                $statusUpdate['status'] = 'confirmed';
                $wasAutoConfirmed       = true;
            }

            // Unflag if fully paid
            if ($newOutstanding <= 0) {
                $statusUpdate['is_flagged'] = false;
            }

            $order->update($statusUpdate);

            $customer = $order->customer;

            if ($request->payment_type === 'advance') {
                // Amount beyond the available advance_credit_balance (including the
                // full amount when the balance is 0) is not blocked — it falls
                // through and is recorded as a normal payment_received entry instead.
                $creditPortion  = min((float) $request->amount, (float) $customer->advance_credit_balance);
                $paymentPortion = $request->amount - $creditPortion;

                if ($creditPortion > 0) {
                    // Advance credit consumed. No new ledger entry — the credit is already in the
                    // ledger via the prior overpayment's payment_received entry exceeding its order_charged.
                    $customer->decrement('advance_credit_balance', $creditPortion);
                }

                if ($paymentPortion > 0) {
                    CustomerLedger::create([
                        'customer_id'             => $order->customer_id,
                        'transaction_type'        => 'payment_received',
                        'amount'                  => -$paymentPortion,
                        'running_advance_balance' => $customer->advance_credit_balance,
                        'reference_type'          => 'App\Models\Payment',
                        'reference_id'            => $payment->id,
                        'notes'                   => "Payment for Order #{$order->order_number} via advance (exceeded available credit)",
                        'created_by'              => Auth::id(),
                    ]);
                }

                // Surplus can arise purely from the credit portion overpaying the order
                // (e.g. available credit exceeds what was actually owed), so this must
                // run regardless of whether there was a separate payment portion.
                $newSurplus = max(0, $newTotalPaid - $order->total_amount);
                $surplus    = $newSurplus - $oldSurplus;
                if ($surplus > 0) {
                    $customer->increment('advance_credit_balance', $surplus);
                }
            } else {
                CustomerLedger::create([
                    'customer_id'             => $order->customer_id,
                    'transaction_type'        => 'payment_received',
                    'amount'                  => -$request->amount,
                    'running_advance_balance' => $customer->advance_credit_balance,
                    'reference_type'          => 'App\Models\Payment',
                    'reference_id'            => $payment->id,
                    'notes'                   => "Payment for Order #{$order->order_number} via {$request->payment_type}",
                    'created_by'              => Auth::id(),
                ]);

                // Only increment advance credit by the ADDITIONAL surplus from this payment,
                // not the total surplus — prevents double-counting on repeated overpayments.
                $newSurplus = max(0, $newTotalPaid - $order->total_amount);
                $surplus    = $newSurplus - $oldSurplus;
                if ($surplus > 0) {
                    $customer->increment('advance_credit_balance', $surplus);
                }
            }
        });

        $order->loadMissing('customer');

        if ($wasAutoConfirmed) {
            app(OrderStatusNotificationService::class)->notify($order, 'confirmed');
        }

        $props = [
            'order'          => 'Order #' . $order->order_number,
            'customer'       => $order->customer?->name ?? $order->submitted_name,
            'payment_type'   => ucfirst(str_replace('_', ' ', $request->payment_type)),
            'amount'         => 'PKR ' . number_format((float) $request->amount, 0),
            'payment_date'   => \Carbon\Carbon::parse($request->payment_date)->format('d M Y'),
            'bank_account'   => $bankAccount?->title ?? '—',
            'receipt_attached' => $receiptPaths ? 'Yes (' . count((array) $receiptPaths) . ' file(s))' : 'No',
        ];
        if ($wasAutoConfirmed) {
            $props['order_status_changed'] = 'received → confirmed (auto)';
        }
        if ($surplus > 0) {
            $props['overpayment_surplus'] = 'PKR ' . number_format($surplus, 0) . ' added to advance credit';
        }
        activity()
            ->performedOn($order)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($props)
            ->log('Payment #' . $order->order_number . 'p' . $payment->sequence_number . ' of PKR ' . number_format((float) $request->amount, 0) . ' recorded on Order #' . $order->order_number);

        return back()->with('success', 'Payment of PKR ' . number_format($request->amount) . ' recorded.');
    }

    public function destroy(Order $order, Payment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }

        $amount         = $payment->amount;
        $paymentType    = $payment->payment_type;
        $paymentDate    = $payment->payment_date?->format('d M Y') ?? '—';
        $bankTitle      = $payment->bankAccount?->title ?? '—';
        $sequenceNumber = $payment->sequence_number;

        $statusReverted = false;

        DB::transaction(function () use ($order, $payment, &$statusReverted) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            $customer    = $lockedOrder->customer()->lockForUpdate()->first();

            $this->reversePaymentContribution($lockedOrder, $payment, $customer);

            $payment->delete();

            // Recalculate from DB after deletion (authoritative sum of remaining payments)
            $newTotalPaid    = $lockedOrder->payments()->sum('amount');
            $newOutstanding  = max(0, $lockedOrder->total_amount - $newTotalPaid);

            $update = [
                'total_paid'          => $newTotalPaid,
                'outstanding_balance' => $newOutstanding,
            ];

            // Revert to received if no payments remain and order is confirmed
            if ($newTotalPaid == 0 && $lockedOrder->status === 'confirmed') {
                $update['status']  = 'received';
                $statusReverted    = true;
            }

            $lockedOrder->update($update);
        });

        $order->loadMissing('customer');
        $order->refresh();
        $deleteProps = [
            'order'              => 'Order #' . $order->order_number,
            'customer'           => $order->customer?->name ?? $order->submitted_name,
            'deleted_payment_type' => ucfirst(str_replace('_', ' ', $paymentType)),
            'deleted_amount'     => 'PKR ' . number_format((float) $amount, 0),
            'original_date'      => $paymentDate,
            'bank_account'       => $bankTitle,
            'new_total_paid'     => 'PKR ' . number_format((float) $order->total_paid, 0),
            'new_outstanding'    => 'PKR ' . number_format((float) $order->outstanding_balance, 0),
        ];
        if ($statusReverted) {
            $deleteProps['order_status_changed'] = 'confirmed → received (no payments remain)';
        }
        activity()
            ->performedOn($order)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($deleteProps)
            ->log('Payment #' . $order->order_number . 'p' . $sequenceNumber . ' of PKR ' . number_format((float) $amount, 0) . ' DELETED from Order #' . $order->order_number);

        return back()->with('success', 'Payment of PKR ' . number_format($amount) . ' has been deleted and the order balance updated.');
    }

    public function edit(Order $order, Payment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }

        $order->loadMissing('customer');
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('title')->get();

        return view('orders.payments.edit', compact('order', 'payment', 'bankAccounts'));
    }

    public function update(Request $request, Order $order, Payment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }

        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_type'      => 'required|in:cash,bank_transfer,advance',
            'bank_account_id'   => 'required_unless:payment_type,advance|nullable|exists:bank_accounts,id',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'remove_receipts'   => 'nullable|array',
            'remove_receipts.*' => 'string',
            'receipt_images'    => 'nullable|array',
            'receipt_images.*'  => 'file|mimes:pdf,jpeg,jpg,png,webp|max:5120',
        ]);

        // Build the final receipt set: existing minus removed, plus new uploads.
        // Switching away from Bank Transfer clears any existing receipt entirely
        // (and deletes it from S3) rather than leaving a stale file attached.
        $existingReceipts = collect($payment->receipt_image ?? []);
        $removeRequested  = collect($request->input('remove_receipts', []));

        $toDelete = $request->payment_type !== 'bank_transfer'
            ? $existingReceipts
            : $existingReceipts->intersect($removeRequested);

        $keptReceipts = $existingReceipts->diff($toDelete);

        $newPaths = collect();
        if (in_array($request->payment_type, ['bank_transfer', 'advance']) && $request->hasFile('receipt_images')) {
            $newPaths = collect($request->file('receipt_images'))->map(fn($file) => $file->store('receipts'));
        }

        $finalReceipts = $keptReceipts->merge($newPaths)->values();

        if ($request->payment_type === 'bank_transfer' && $finalReceipts->isEmpty()) {
            return back()->withErrors(['receipt_images' => 'A receipt is required for bank transfer payments.'])->withInput();
        }

        $bankAccount = in_array($request->payment_type, ['cash', 'bank_transfer']) && $request->bank_account_id
            ? BankAccount::find($request->bank_account_id)
            : null;

        $titleGiven = match ($request->payment_type) {
            'bank_transfer' => $bankAccount?->title ?? 'Bank Transfer',
            'cash'          => $bankAccount ? 'Cash · ' . $bankAccount->title : 'Cash',
            'advance'       => 'Advance',
            default         => null,
        };

        $oldAmount = (float) $payment->amount;
        $oldType   = $payment->payment_type;

        $wasReverted        = false;
        $newSurplusFromEdit = 0;

        DB::transaction(function () use (
            $request, $order, $payment, $finalReceipts, $titleGiven,
            &$wasReverted, &$newSurplusFromEdit
        ) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            $customer    = $lockedOrder->customer()->lockForUpdate()->first();

            // Step 1 — reverse this payment's OLD ledger entry + advance-credit effects,
            // while total_paid still includes its old amount.
            $this->reversePaymentContribution($lockedOrder, $payment, $customer);

            // Step 2 — apply the new field values to the same row. sequence_number and
            // order_id are never touched, so the Payment ID (#{order}p{n}) stays stable.
            $payment->update([
                'amount'          => $request->amount,
                'payment_type'    => $request->payment_type,
                'bank_account_id' => in_array($request->payment_type, ['cash', 'bank_transfer']) ? $request->bank_account_id : null,
                'title_given'     => $titleGiven,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $finalReceipts->isNotEmpty() ? $finalReceipts->toArray() : null,
            ]);

            // Step 3 — recompute order totals from a fresh, authoritative sum.
            $newTotalPaid   = $lockedOrder->payments()->sum('amount');
            $newOutstanding = max(0, $lockedOrder->total_amount - $newTotalPaid);

            $statusUpdate = ['total_paid' => $newTotalPaid, 'outstanding_balance' => $newOutstanding];

            if ($newTotalPaid == 0 && $lockedOrder->status === 'confirmed') {
                $statusUpdate['status'] = 'received';
                $wasReverted            = true;
            }
            if ($newOutstanding <= 0) {
                $statusUpdate['is_flagged'] = false;
            }

            $lockedOrder->update($statusUpdate);
            $customer->refresh();

            // Surplus baseline is total_paid as if this payment's NEW amount weren't
            // added yet — used only to compute the delta applied to advance credit.
            $surplusBaseline = max(0, ($newTotalPaid - $request->amount) - $lockedOrder->total_amount);

            // Step 4 — apply the new payment's ledger entry + advance-credit effects
            // (mirrors store()).
            if ($request->payment_type === 'advance') {
                $creditPortion  = min((float) $request->amount, (float) $customer->advance_credit_balance);
                $paymentPortion = $request->amount - $creditPortion;

                if ($creditPortion > 0) {
                    $customer->decrement('advance_credit_balance', $creditPortion);
                    $customer->refresh();
                }

                if ($paymentPortion > 0) {
                    CustomerLedger::create([
                        'customer_id'             => $order->customer_id,
                        'transaction_type'        => 'payment_received',
                        'amount'                  => -$paymentPortion,
                        'running_advance_balance' => $customer->advance_credit_balance,
                        'reference_type'          => Payment::class,
                        'reference_id'            => $payment->id,
                        'notes'                   => "Payment for Order #{$order->order_number} via advance (exceeded available credit)",
                        'created_by'              => Auth::id(),
                    ]);
                }
            } else {
                CustomerLedger::create([
                    'customer_id'             => $order->customer_id,
                    'transaction_type'        => 'payment_received',
                    'amount'                  => -$request->amount,
                    'running_advance_balance' => $customer->advance_credit_balance,
                    'reference_type'          => Payment::class,
                    'reference_id'            => $payment->id,
                    'notes'                   => "Payment for Order #{$order->order_number} via {$request->payment_type}",
                    'created_by'              => Auth::id(),
                ]);
            }

            // Overpayment surplus check runs unconditionally regardless of type (rule 5.19).
            $newSurplus = max(0, $newTotalPaid - $lockedOrder->total_amount);
            $surplus    = $newSurplus - $surplusBaseline;
            if ($surplus > 0) {
                $customer->increment('advance_credit_balance', $surplus);
            }
            $newSurplusFromEdit = $surplus;
        });

        // Delete removed/replaced receipt files from S3 only after the transaction commits.
        foreach ($toDelete as $path) {
            Storage::delete($path);
        }

        $order->refresh();
        $order->loadMissing('customer');
        $payment->refresh();

        $props = [
            'order'        => 'Order #' . $order->order_number,
            'customer'     => $order->customer?->name ?? $order->submitted_name,
            'old_amount'   => 'PKR ' . number_format($oldAmount, 0),
            'new_amount'   => 'PKR ' . number_format((float) $request->amount, 0),
            'old_type'     => ucfirst(str_replace('_', ' ', $oldType)),
            'new_type'     => ucfirst(str_replace('_', ' ', $request->payment_type)),
            'bank_account' => $bankAccount?->title ?? '—',
        ];
        if ($wasReverted) {
            $props['order_status_changed'] = 'confirmed → received (no payments remain)';
        }
        if ($newSurplusFromEdit > 0) {
            $props['overpayment_surplus'] = 'PKR ' . number_format($newSurplusFromEdit, 0) . ' added to advance credit';
        }

        activity()
            ->performedOn($order)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($props)
            ->log('Payment #' . $order->order_number . 'p' . $payment->sequence_number . ' edited on Order #' . $order->order_number
                . ' (PKR ' . number_format($oldAmount, 0) . ' → PKR ' . number_format((float) $request->amount, 0) . ')');

        return redirect()->route('orders.show', $order)
            ->with('success', 'Payment #' . $order->order_number . 'p' . $payment->sequence_number . ' updated.');
    }

    public function applyCredit(Request $request, Order $order)
    {
        $order->loadMissing('customer');
        $customer  = $order->customer;
        $maxCredit = min((float) $customer->advance_credit_balance, (float) $order->outstanding_balance);

        $validated = $request->validate([
            'credit_amount' => ['required', 'numeric', 'min:1', 'max:' . $maxCredit],
            'notes'         => 'nullable|string',
        ]);

        $balanceBefore    = (float) $customer->advance_credit_balance;
        $wasAutoConfirmed = false;

        DB::transaction(function () use ($validated, $order, $customer, $balanceBefore, &$wasAutoConfirmed) {
            $creditAmount   = (float) $validated['credit_amount'];
            $newTotalPaid   = $order->total_paid + $creditAmount;
            $newOutstanding = max(0, $order->total_amount - $newTotalPaid);

            CustomerLedger::create([
                'customer_id'             => $order->customer_id,
                'transaction_type'        => 'credit_applied',
                'amount'                  => -$creditAmount,
                'running_advance_balance' => $balanceBefore,
                'reference_type'          => 'App\Models\Order',
                'reference_id'            => $order->id,
                'notes'                   => 'Credit: ' . ($validated['notes'] ?? 'Manual credit adjustment'),
                'created_by'              => Auth::id(),
            ]);

            $customer->decrement('advance_credit_balance', $creditAmount);

            $statusUpdate = [
                'total_paid'          => $newTotalPaid,
                'outstanding_balance' => $newOutstanding,
            ];

            if ($order->status === 'received') {
                $statusUpdate['status'] = 'confirmed';
                $wasAutoConfirmed       = true;
            }

            $order->update($statusUpdate);
        });

        if ($wasAutoConfirmed) {
            $order->loadMissing('customer');
            app(OrderStatusNotificationService::class)->notify($order, 'confirmed');
        }

        return back()->with('success', 'Credit of PKR ' . number_format($validated['credit_amount']) . ' applied.');
    }
}
