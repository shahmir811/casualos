<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdvancePaymentController extends Controller
{
    /**
     * Reverses an advance payment's ledger entry and its balance effect, while
     * the customer row is locked. Unlike order payments, there's no order total
     * to reconcile against — the only complication is that the credit this row
     * added may have already been spent (via credit_applied or an advance-type
     * order payment) by the time it's edited or deleted. In that case the
     * balance is floored at 0 rather than going negative, and the caller is
     * told how much of the reversal couldn't be applied so it can warn the user.
     */
    private function reverseAdvancePaymentContribution(Customer $customer, AdvancePayment $advancePayment): float
    {
        DB::table('customer_ledger')
            ->where('reference_type', AdvancePayment::class)
            ->where('reference_id', $advancePayment->id)
            ->where('transaction_type', 'advance_received')
            ->delete();

        $amount    = (float) $advancePayment->amount;
        $available = (float) $customer->advance_credit_balance;
        $reversed  = min($amount, $available);
        $shortfall = $amount - $reversed;

        if ($reversed > 0) {
            $customer->decrement('advance_credit_balance', $reversed);
            $customer->refresh();
        }

        return $shortfall;
    }

    public function store(Request $request, Customer $customer)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:1',
            'payment_type'     => 'required|in:cash,bank_transfer',
            'bank_account_id'  => 'required|exists:bank_accounts,id',
            'payment_date'     => 'required|date',
            'notes'            => 'nullable|string',
            'receipt_images'   => 'required_if:payment_type,bank_transfer|nullable|array|min:1',
            'receipt_images.*' => 'file|mimes:pdf,jpeg,jpg,png,webp|max:5120',
        ]);

        $receiptPaths = null;
        if ($request->hasFile('receipt_images')) {
            $receiptPaths = collect($request->file('receipt_images'))
                ->map(fn($file) => $file->store('receipts'))
                ->values()
                ->toArray();
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        $advancePayment = null;

        DB::transaction(function () use ($request, $receiptPaths, $customer, &$advancePayment) {
            $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->first();

            $advancePayment = AdvancePayment::create([
                'customer_id'     => $lockedCustomer->id,
                'payment_type'    => $request->payment_type,
                'amount'          => $request->amount,
                'bank_account_id' => $request->bank_account_id,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $receiptPaths,
                'logged_by'       => Auth::id(),
            ]);

            // Negative — this is money in the customer's favour, same convention as
            // payment_received/credit_applied. A positive amount here would make the
            // ledger's SUM() (and the "Outstanding Balance" it drives) read as if the
            // customer owes more, when an advance payment is the opposite.
            CustomerLedger::create([
                'customer_id'             => $lockedCustomer->id,
                'transaction_type'        => 'advance_received',
                'amount'                  => -$request->amount,
                'running_advance_balance' => $lockedCustomer->advance_credit_balance,
                'reference_type'          => AdvancePayment::class,
                'reference_id'            => $advancePayment->id,
                'notes'                   => "Advance payment received via {$request->payment_type}",
                'created_by'              => Auth::id(),
            ]);

            $lockedCustomer->increment('advance_credit_balance', $request->amount);
        });

        activity()
            ->performedOn($customer)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'customer'      => $customer->name,
                'payment_type'  => ucfirst(str_replace('_', ' ', $request->payment_type)),
                'amount'        => 'PKR ' . number_format((float) $request->amount, 0),
                'payment_date'  => \Carbon\Carbon::parse($request->payment_date)->format('d M Y'),
                'bank_account'  => $bankAccount?->title ?? '—',
            ])
            ->log('Advance payment of PKR ' . number_format((float) $request->amount, 0) . ' recorded for "' . $customer->name . '"');

        return back()->with('success', 'Advance payment of PKR ' . number_format($request->amount) . ' recorded.');
    }

    public function edit(Customer $customer, AdvancePayment $advancePayment)
    {
        if ((int) $advancePayment->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $bankAccounts = BankAccount::where('is_active', true)->orderBy('title')->get();

        return view('customers.advance-payments.edit', compact('customer', 'advancePayment', 'bankAccounts'));
    }

    public function update(Request $request, Customer $customer, AdvancePayment $advancePayment)
    {
        if ((int) $advancePayment->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_type'      => 'required|in:cash,bank_transfer',
            'bank_account_id'   => 'required|exists:bank_accounts,id',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'remove_receipts'   => 'nullable|array',
            'remove_receipts.*' => 'string',
            'receipt_images'    => 'nullable|array',
            'receipt_images.*'  => 'file|mimes:pdf,jpeg,jpg,png,webp|max:5120',
        ]);

        $existingReceipts = collect($advancePayment->receipt_image ?? []);
        $removeRequested  = collect($request->input('remove_receipts', []));

        // Switching away from Bank Transfer clears any existing receipt entirely,
        // same convention as order payment editing (rule 5.21).
        $toDelete = $request->payment_type !== 'bank_transfer'
            ? $existingReceipts
            : $existingReceipts->intersect($removeRequested);

        $keptReceipts = $existingReceipts->diff($toDelete);

        $newPaths = collect();
        if ($request->hasFile('receipt_images')) {
            $newPaths = collect($request->file('receipt_images'))->map(fn($file) => $file->store('receipts'));
        }

        $finalReceipts = $keptReceipts->merge($newPaths)->values();

        if ($request->payment_type === 'bank_transfer' && $finalReceipts->isEmpty()) {
            return back()->withErrors(['receipt_images' => 'A receipt is required for bank transfer payments.'])->withInput();
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        $oldAmount   = (float) $advancePayment->amount;
        $oldType     = $advancePayment->payment_type;
        $shortfall   = 0.0;

        DB::transaction(function () use (
            $request, $customer, $advancePayment, $finalReceipts, &$shortfall
        ) {
            $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->first();

            // Step 1 — reverse the OLD contribution.
            $shortfall = $this->reverseAdvancePaymentContribution($lockedCustomer, $advancePayment);

            // Step 2 — mutate the same row with the new values.
            $advancePayment->update([
                'payment_type'    => $request->payment_type,
                'amount'          => $request->amount,
                'bank_account_id' => $request->bank_account_id,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $finalReceipts->isNotEmpty() ? $finalReceipts->toArray() : null,
            ]);

            // Step 3 — reapply the NEW contribution. Negative amount — see store().
            CustomerLedger::create([
                'customer_id'             => $lockedCustomer->id,
                'transaction_type'        => 'advance_received',
                'amount'                  => -$request->amount,
                'running_advance_balance' => $lockedCustomer->advance_credit_balance,
                'reference_type'          => AdvancePayment::class,
                'reference_id'            => $advancePayment->id,
                'notes'                   => "Advance payment received via {$request->payment_type} (edited)",
                'created_by'              => Auth::id(),
            ]);

            $lockedCustomer->increment('advance_credit_balance', $request->amount);
        });

        foreach ($toDelete as $path) {
            Storage::delete($path);
        }

        $customer->refresh();
        $advancePayment->refresh();

        $props = [
            'customer'   => $customer->name,
            'old_amount' => 'PKR ' . number_format($oldAmount, 0),
            'new_amount' => 'PKR ' . number_format((float) $request->amount, 0),
            'old_type'   => ucfirst(str_replace('_', ' ', $oldType)),
            'new_type'   => ucfirst(str_replace('_', ' ', $request->payment_type)),
            'bank_account' => $bankAccount?->title ?? '—',
        ];
        if ($shortfall > 0) {
            $props['advance_credit_shortfall'] = 'PKR ' . number_format($shortfall, 0) . ' of the original amount had already been spent — balance floored at 0';
        }

        activity()
            ->performedOn($customer)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($props)
            ->log('Advance payment edited for "' . $customer->name . '" (PKR ' . number_format($oldAmount, 0) . ' → PKR ' . number_format((float) $request->amount, 0) . ')');

        $message = 'Advance payment updated.';
        if ($shortfall > 0) {
            $message .= ' Note: PKR ' . number_format($shortfall, 0) . ' of the original amount had already been used, so the advance credit balance was floored at PKR 0 before the new amount was applied.';
        }

        return redirect()->route('customers.show', $customer)->with('success', $message);
    }

    public function destroy(Customer $customer, AdvancePayment $advancePayment)
    {
        if ((int) $advancePayment->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $amount      = (float) $advancePayment->amount;
        $paymentType = $advancePayment->payment_type;
        $paymentDate = $advancePayment->payment_date?->format('d M Y') ?? '—';
        $bankTitle   = $advancePayment->bankAccount?->title ?? '—';
        $shortfall   = 0.0;

        DB::transaction(function () use ($customer, $advancePayment, &$shortfall) {
            $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->first();
            $shortfall = $this->reverseAdvancePaymentContribution($lockedCustomer, $advancePayment);
            $advancePayment->delete();
        });

        $customer->refresh();

        $deleteProps = [
            'customer'              => $customer->name,
            'deleted_payment_type'  => ucfirst(str_replace('_', ' ', $paymentType)),
            'deleted_amount'        => 'PKR ' . number_format($amount, 0),
            'original_date'         => $paymentDate,
            'bank_account'          => $bankTitle,
            'new_advance_balance'   => 'PKR ' . number_format((float) $customer->advance_credit_balance, 0),
        ];
        if ($shortfall > 0) {
            $deleteProps['advance_credit_shortfall'] = 'PKR ' . number_format($shortfall, 0) . ' had already been spent — balance floored at 0';
        }

        activity()
            ->performedOn($customer)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($deleteProps)
            ->log('Advance payment of PKR ' . number_format($amount, 0) . ' DELETED for "' . $customer->name . '"');

        $message = 'Advance payment of PKR ' . number_format($amount) . ' has been deleted.';
        if ($shortfall > 0) {
            $message .= ' PKR ' . number_format($shortfall, 0) . ' of it had already been spent, so the advance credit balance was floored at PKR 0.';
        }

        return back()->with('success', $message);
    }
}
