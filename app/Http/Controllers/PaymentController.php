<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_type'      => 'required|in:cash,bank_transfer,advance',
            'bank_account_id'   => 'required_if:payment_type,bank_transfer|nullable|exists:bank_accounts,id',
            'payment_date'      => 'required|date',
            'notes'             => 'nullable|string',
            'receipt_images'    => 'required_if:payment_type,bank_transfer|nullable|array|min:1',
            'receipt_images.*'  => 'file|mimes:pdf,jpeg,jpg,png,webp|max:5120',
        ]);

        $receiptPaths = null;
        if ($request->payment_type === 'bank_transfer' && $request->hasFile('receipt_images')) {
            $receiptPaths = collect($request->file('receipt_images'))
                ->map(fn($file) => $file->store('receipts'))
                ->values()
                ->toArray();
        }

        $titleGiven = match ($request->payment_type) {
            'bank_transfer' => BankAccount::find($request->bank_account_id)?->title ?? 'Bank Transfer',
            'cash'          => 'Cash',
            'advance'       => 'Advance',
            default         => null,
        };

        DB::transaction(function () use ($request, $receiptPaths, $titleGiven, $order) {
            $payment = Payment::create([
                'order_id'        => $order->id,
                'customer_id'     => $order->customer_id,
                'amount'          => $request->amount,
                'payment_type'    => $request->payment_type,
                'bank_account_id' => $request->payment_type === 'bank_transfer' ? $request->bank_account_id : null,
                'title_given'     => $titleGiven,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $receiptPaths,
                'logged_by'       => Auth::id(),
            ]);

            // Update order financials
            $newTotalPaid = $order->total_paid + $request->amount;
            $newOutstanding = max(0, $order->total_amount - $newTotalPaid);
            $statusUpdate = ['total_paid' => $newTotalPaid, 'outstanding_balance' => $newOutstanding];

            // Auto-confirm on first payment
            if ($order->status === 'received') {
                $statusUpdate['status'] = 'confirmed';
            }

            // Unflag if fully paid
            if ($newOutstanding <= 0) {
                $statusUpdate['is_flagged'] = false;
            }

            $order->update($statusUpdate);

            // Ledger entry
            CustomerLedger::create([
                'customer_id'             => $order->customer_id,
                'transaction_type'        => 'payment_received',
                'amount'                  => -$request->amount,
                'running_advance_balance' => 0,
                'reference_type'          => 'App\Models\Payment',
                'reference_id'            => $payment->id,
                'notes'                   => "Payment for Order #{$order->order_number} via {$request->payment_type}",
                'created_by'              => Auth::id(),
            ]);
        });

        return back()->with('success', 'Payment of PKR ' . lacs_format($request->amount) . ' recorded.');
    }

    public function destroy(Order $order, Payment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }

        $amount = $payment->amount;

        DB::transaction(function () use ($order, $payment) {
            // Bypass CustomerLedger model's boot-level deletion guard
            DB::table('customer_ledger')
                ->where('reference_type', 'App\Models\Payment')
                ->where('reference_id', $payment->id)
                ->delete();

            $payment->delete();

            // Recalculate from DB after deletion (authoritative sum of remaining payments)
            $newTotalPaid    = $order->payments()->sum('amount');
            $newOutstanding  = max(0, $order->total_amount - $newTotalPaid);

            $update = [
                'total_paid'          => $newTotalPaid,
                'outstanding_balance' => $newOutstanding,
            ];

            // Revert to received if no payments remain and order is confirmed
            if ($newTotalPaid == 0 && $order->status === 'confirmed') {
                $update['status'] = 'received';
            }

            $order->update($update);
        });

        return back()->with('success', 'Payment of PKR ' . lacs_format($amount) . ' has been deleted and the order balance updated.');
    }

    public function applyCredit(Request $request, Order $order)
    {
        $validated = $request->validate([
            'credit_amount' => 'required|numeric|min:1',
            'notes'         => 'nullable|string',
        ]);

        CustomerLedger::create([
            'customer_id'             => $order->customer_id,
            'transaction_type'        => 'credit_applied',
            'amount'                  => -$validated['credit_amount'],
            'running_advance_balance' => 0,
            'reference_type'          => 'App\Models\Order',
            'reference_id'            => $order->id,
            'notes'                   => 'Credit: ' . ($validated['notes'] ?? 'Manual credit adjustment'),
            'created_by'              => Auth::id(),
        ]);

        // Auto-confirm on first credit application
        if ($order->status === 'received') {
            $order->update(['status' => 'confirmed']);
        }

        return back()->with('success', 'Credit of PKR ' . lacs_format($validated['credit_amount']) . ' applied.');
    }
}
