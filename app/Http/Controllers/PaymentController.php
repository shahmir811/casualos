<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:1',
            'payment_type'   => 'required|in:cash,bank_transfer,easypaisa,jazzcash,advance',
            'payment_date'   => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $order) {
            $payment = Payment::create([
                'order_id'     => $order->id,
                'customer_id'  => $order->customer_id,
                'amount'       => $validated['amount'],
                'payment_type' => $validated['payment_type'],
                'payment_date' => $validated['payment_date'],
                'notes'        => $validated['notes'] ?? null,
                'logged_by'    => Auth::id(),
            ]);

            // Update order financials
            $newTotalPaid = $order->total_paid + $validated['amount'];
            $order->update([
                'total_paid'          => $newTotalPaid,
                'outstanding_balance' => max(0, $order->total_amount - $newTotalPaid),
            ]);

            // Flag the order if fully paid (optional logic)
            if ($order->outstanding_balance <= 0) {
                $order->update(['is_flagged' => false]);
            }

            // Ledger entry
            CustomerLedger::create([
                'customer_id'             => $order->customer_id,
                'transaction_type'        => 'payment',
                'amount'                  => -$validated['amount'], // credit reduces balance
                'running_advance_balance' => 0, // will be computed properly in full implementation
                'reference_type'          => 'App\Models\Payment',
                'reference_id'            => $payment->id,
                'notes'                   => "Payment for Order #{$order->id} via {$validated['payment_type']}",
                'created_by'              => Auth::id(),
            ]);
        });

        return back()->with('success', 'Payment of PKR ' . number_format($validated['amount']) . ' recorded.');
    }

    public function applyCredit(Request $request, Order $order)
    {
        $validated = $request->validate([
            'credit_amount' => 'required|numeric|min:1',
            'notes'         => 'nullable|string',
        ]);

        CustomerLedger::create([
            'customer_id'             => $order->customer_id,
            'transaction_type'        => 'credit_adjustment',
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
