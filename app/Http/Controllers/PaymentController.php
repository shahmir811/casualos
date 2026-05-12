<?php

namespace App\Http\Controllers;

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
            'amount'          => 'required|numeric|min:1',
            'payment_type'    => 'required|in:cash,bank_transfer,advance',
            'bank_account_id' => 'required_if:payment_type,bank_transfer|nullable|exists:bank_accounts,id',
            'payment_date'    => 'required|date',
            'notes'           => 'nullable|string',
            'receipt_image'   => 'required_if:payment_type,bank_transfer|nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $receiptPath = $request->hasFile('receipt_image')
            ? $request->file('receipt_image')->store('receipts')
            : null;

        DB::transaction(function () use ($request, $receiptPath, $order) {
            $payment = Payment::create([
                'order_id'        => $order->id,
                'customer_id'     => $order->customer_id,
                'amount'          => $request->amount,
                'payment_type'    => $request->payment_type,
                'bank_account_id' => $request->payment_type === 'bank_transfer' ? $request->bank_account_id : null,
                'payment_date'    => $request->payment_date,
                'notes'           => $request->notes ?? null,
                'receipt_image'   => $receiptPath,
                'logged_by'       => Auth::id(),
            ]);

            // Update order financials
            $newTotalPaid = $order->total_paid + $request->amount;
            $order->update([
                'total_paid'          => $newTotalPaid,
                'outstanding_balance' => max(0, $order->total_amount - $newTotalPaid),
            ]);

            // Unflag if fully paid
            if ($order->outstanding_balance <= 0) {
                $order->update(['is_flagged' => false]);
            }

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

        return back()->with('success', 'Payment of PKR ' . number_format($request->amount) . ' recorded.');
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

        return back()->with('success', 'Credit of PKR ' . number_format($validated['credit_amount']) . ' applied.');
    }
}
