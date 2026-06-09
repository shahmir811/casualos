<?php

namespace App\Http\Controllers;

use App\Exports\PaymentSheetExport;
use App\Models\Order;
use App\Models\Catalogue;
use App\Models\BankAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $catalogues = Catalogue::orderBy('name')->get(['id', 'name', 'qty_per_design', 'number_of_designs']);

        $sort      = $request->input('sort');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Order::with(['customer', 'catalogue', 'items', 'assignedBankAccount']);

        // Always filter by the sidebar-selected catalogue
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: null;
        if ($selectedCatalogueId) {
            $query->where('catalogue_id', $selectedCatalogueId);
        }

        // Filter by status
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by customer name, city, or order number
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('submitted_name', 'like', '%' . $search . '%')
                  ->orWhere('submitted_city', 'like', '%' . $search . '%')
                  ->orWhere('order_number', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', '%' . $search . '%'));
            });
        }

        // Sorting
        if ($sort === 'customer_name') {
            $query->select('orders.*')
                  ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                  ->orderByRaw('COALESCE(customers.name, orders.submitted_name) COLLATE utf8mb4_unicode_ci ' . $direction);
        } else {
            $query->latest('submitted_at');
        }

        // When a catalogue is selected, load all (no pagination) — mirrors the PDF sheet
        $orders = $selectedCatalogueId
            ? $query->get()
            : $query->paginate(50);

        // Summary totals (size columns + grand total)
        $summary = [
            'xs'    => 0, 's'  => 0, 'm'  => 0,
            'l'     => 0, 'xl' => 0, 'total_pieces' => 0, 'total_bill' => 0,
        ];

        $collection = $selectedCatalogueId ? $orders : $orders->getCollection();
        foreach ($collection as $order) {
            // Use first item only — quantities per design are the same across all designs
            $item = $order->items->first();
            if ($item) {
                $summary['xs']           += $item->qty_xs;
                $summary['s']            += $item->qty_s;
                $summary['m']            += $item->qty_m;
                $summary['l']            += $item->qty_l;
                $summary['xl']           += $item->qty_xl;
                $summary['total_pieces'] += $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
            }
            $summary['total_bill'] += $order->total_amount;
        }

        $selectedCatalogue = $selectedCatalogueId
            ? $catalogues->firstWhere('id', $selectedCatalogueId)
            : null;

        $bankAccounts    = BankAccount::where('is_active', true)->orderBy('title')->get();
        $hideFinancials  = Auth::user()->role === 'production_manager';

        return view('orders.index', compact(
            'orders', 'catalogues', 'selectedCatalogue', 'selectedCatalogueId', 'summary', 'bankAccounts', 'sort', 'direction', 'hideFinancials'
        ));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'payments.bankAccount', 'reductions.items.design', 'reductions.reducedBy', 'reductions.refund']);
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('title')->get();
        return view('orders.show', compact('order', 'bankAccounts'));
    }

    public function edit(Order $order)
    {
        return view('orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'notes'      => 'nullable|string',
            'is_flagged' => 'nullable|boolean',
        ]);

        $order->update($validated);

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    public function confirm(Order $order)
    {
        if ($order->status !== 'received') {
            return back()->with('error', 'Only received orders can be confirmed.');
        }

        $order->update(['status' => 'confirmed']);

        return back()->with('success', 'Order #' . $order->order_number . ' confirmed.');
    }

    public function invoice(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'payments.bankAccount', 'reductions.items']);

        $logoDataUri = pdf_logo_data_uri();

        $pdf = Pdf::loadView('orders.invoice', compact('order', 'logoDataUri'))
            ->setPaper('a4', 'portrait');

        $filename = 'invoice-' . $order->order_number . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadPdf(Request $request)
    {
        $catalogueId = (int) session('active_catalogue_id');
        $catalogue   = Catalogue::findOrFail($catalogueId);

        $query = Order::with(['customer', 'items', 'payments.bankAccount'])
            ->where('catalogue_id', $catalogue->id)
            ->orderBy('submitted_at');

        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $orders = $query->get();

        $bankAccounts   = BankAccount::orderBy('id')->get();
        $hideFinancials = Auth::user()->role === 'production_manager';

        $logoDataUri = pdf_logo_data_uri();

        $pdf = Pdf::loadView('orders.pdf', compact('orders', 'catalogue', 'bankAccounts', 'logoDataUri', 'hideFinancials'))
            ->setPaper('a3', 'landscape');

        $filename = str($catalogue->name)->slug() . ($hideFinancials ? '-orders' : '-payments') . '.pdf';

        return $pdf->download($filename);
    }

    public function downloadExcel(Request $request)
    {
        $catalogueId = (int) session('active_catalogue_id');
        $catalogue   = Catalogue::findOrFail($catalogueId);

        $query = Order::with(['customer', 'items', 'payments.bankAccount'])
            ->where('catalogue_id', $catalogue->id)
            ->orderBy('submitted_at');

        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $orders = $query->get();

        $bankAccounts   = BankAccount::orderBy('id')->get();
        $hideFinancials = Auth::user()->role === 'production_manager';

        $filename = str($catalogue->name)->slug() . ($hideFinancials ? '-orders' : '-payments') . '.xlsx';

        return (new PaymentSheetExport($orders, $catalogue, $bankAccounts, $hideFinancials))->download($filename);
    }

    public function destroy(Order $order)
    {
        if ($order->status !== 'received' || $order->total_paid > 0) {
            abort(403, 'Only received orders with no payments recorded can be deleted.');
        }

        $orderNumber = $order->order_number;

        DB::transaction(function () use ($order) {
            // Bypass the CustomerLedger model's boot-level deletion guard
            DB::table('customer_ledger')
                ->where('reference_type', 'App\\Models\\Order')
                ->where('reference_id', $order->id)
                ->where('transaction_type', 'order_charged')
                ->delete();

            // order_items cascade via FK; activity_log rows are preserved
            $order->delete();
        });

        return redirect()->route('orders.index')
            ->with('success', 'Order #' . $orderNumber . ' has been permanently deleted.');
    }

    public function markStitching(Order $order)
    {
        if (!in_array(Auth::user()->role, ['admin', 'production_manager'])) {
            abort(403);
        }

        if ($order->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed orders can be sent to stitching.');
        }

        $order->update(['status' => 'stitching']);

        return back()->with('success', 'Order #' . $order->order_number . ' moved to stitching.');
    }

}
