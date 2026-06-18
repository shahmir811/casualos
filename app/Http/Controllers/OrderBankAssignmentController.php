<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        activity()
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'orders_updated'  => $updated,
                'order_ids'       => implode(', ', $request->order_ids),
                'bank_assigned'   => $bankTitle,
                'catalogue_scope' => $catalogueId ? 'Catalogue #' . $catalogueId : 'All catalogues',
                'action_by'       => Auth::user()->name,
            ])
            ->log("Bulk bank assignment: {$updated} order(s) → {$bankTitle}");

        return back()->with('success', "{$updated} " . ($updated === 1 ? 'order' : 'orders') . " assigned to {$bankTitle}.");
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        $previousBank = $order->assignedBankAccount?->title ?? 'Unassigned';
        $order->update(['assigned_bank_account_id' => $request->bank_account_id ?: null]);
        $newBank = $request->bank_account_id
            ? (BankAccount::find($request->bank_account_id)?->title ?? 'selected bank')
            : 'Unassigned';

        $order->loadMissing('customer');
        activity()
            ->performedOn($order)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'order'          => 'Order #' . $order->order_number,
                'customer'       => $order->customer?->name ?? $order->submitted_name,
                'bank_changed'   => $previousBank . ' → ' . $newBank,
                'action_by'      => Auth::user()->name,
            ])
            ->log('Bank assignment updated for Order #' . $order->order_number . ': ' . $previousBank . ' → ' . $newBank);

        return back()->with('success', 'Designated bank updated.');
    }
}
