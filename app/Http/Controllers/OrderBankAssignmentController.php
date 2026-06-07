<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderBankAssignmentController extends Controller
{
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'order_ids'       => 'required|array|min:1',
            'order_ids.*'     => 'integer|exists:orders,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        $catalogueId = session('active_catalogue_id');

        $query = Order::whereIn('id', $request->order_ids);

        // Scope to active catalogue when set — prevents cross-catalogue tampering
        if ($catalogueId) {
            $query->where('catalogue_id', $catalogueId);
        }

        $updated = $query->update(['assigned_bank_account_id' => $request->bank_account_id ?: null]);

        $bankTitle = $request->bank_account_id
            ? (BankAccount::find($request->bank_account_id)?->title ?? 'selected bank')
            : 'Unassigned';

        return back()->with('success', "{$updated} " . ($updated === 1 ? 'order' : 'orders') . " assigned to {$bankTitle}.");
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        $order->update(['assigned_bank_account_id' => $request->bank_account_id ?: null]);

        return back()->with('success', 'Designated bank updated.');
    }
}
