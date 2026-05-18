<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Order;
use App\Models\CustomerLedger;
use App\Models\PressReturnItem;
use App\Models\Wage;
use App\Models\Design;
use App\Models\DispatchBatch;
use App\Models\OrderReduction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function catalogueSummary(Request $request)
    {
        $catalogues = Catalogue::withCount('designs')
            ->withCount(['orders as orders_count'])
            ->with(['orders' => fn($q) => $q->withSum(['items as total_pieces'], \DB::raw('qty_xs+qty_s+qty_m+qty_l+qty_xl'))])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('reports.catalogue-summary', compact('catalogues'));
    }

    public function customerMasterList(Request $request)
    {
        $customers = Customer::withCount('orders')
            ->orderBy('name')
            ->get();

        return view('reports.customer-master-list', compact('customers'));
    }

    public function customerOrders(Request $request)
    {
        $customers = Customer::with(['orders.catalogue'])->orderBy('name')->get();
        return view('reports.customer-orders', compact('customers'));
    }

    public function customerLedger(Request $request)
    {
        $customers = Customer::orderBy('name')->get();
        $selectedCustomer = null;
        $entries = collect();
        $balance = 0;

        if ($request->customer_id) {
            $selectedCustomer = Customer::find($request->customer_id);
            $entries = CustomerLedger::where('customer_id', $request->customer_id)
                ->orderBy('created_at', 'desc')
                ->get();
            $balance = $entries->sum('amount');
        }

        return view('reports.customer-ledger', compact('customers', 'selectedCustomer', 'entries', 'balance'));
    }

    public function productionStatus(Request $request)
    {
        $orders = Order::with(['customer', 'catalogue'])
            ->whereIn('status', ['confirmed', 'stitching'])
            ->get();

        return view('reports.production-status', compact('orders'));
    }

    public function stitchingReconciliation(Request $request)
    {
        // Placeholder — will aggregate send vs return data
        return view('reports.stitching-reconciliation');
    }

    public function packedInventory()
    {
        $returnItems = PressReturnItem::with([
            'pressReturn.send.catalogue',
            'design',
        ])->get();

        $grouped = $returnItems->groupBy(fn($item) => $item->pressReturn->send->catalogue_id);

        return view('reports.packed-inventory', compact('grouped'));
    }

    public function payrollHistory(Request $request)
    {
        $wages = Wage::with(['catalogue', 'stitchingUnit', 'confirmedBy'])->latest()->get();
        return view('reports.payroll-history', compact('wages'));
    }

    public function outsourcedDesigns()
    {
        $designs = Design::where('manufacturing_type', 'outsourced')
            ->with(['catalogue', 'productionAssignment'])
            ->get();

        return view('reports.outsourced-designs', compact('designs'));
    }

    public function dispatchHistory(Request $request)
    {
        $dispatches = DispatchBatch::with(['order.customer', 'order.catalogue'])->latest()->get();
        return view('reports.dispatch-history', compact('dispatches'));
    }

    public function activityLog(Request $request)
    {
        $logs = Activity::with('causer')->latest()->paginate(50);
        return view('reports.activity-log', compact('logs'));
    }

    public function damageReductions(Request $request)
    {
        $reductions = OrderReduction::with(['order.customer', 'items.design', 'reducedBy'])
            ->latest()
            ->get();

        return view('reports.damage-reductions', compact('reductions'));
    }

    // ── Customer Order Bill ───────────────────────────────────────────────────

    public function customerOrderBill(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $orders            = $this->loadOrderBillData($catalogueId);

        return view('reports.customer-order-bill', compact('selectedCatalogue', 'orders'));
    }

    public function customerOrderBillPdf(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $orders            = $this->loadOrderBillData($catalogueId);

        return Pdf::loadView('reports.customer-order-bill-pdf', compact('selectedCatalogue', 'orders'))
            ->setPaper('a4', 'landscape')
            ->download('customer-order-bill-' . str($selectedCatalogue->name)->slug() . '.pdf');
    }

    // ── Bank Account Breakdown ────────────────────────────────────────────────

    public function bankAccountBreakdown(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $bankAccounts      = BankAccount::orderBy('title')->get();
        $orders            = $this->loadBankBreakdownData($catalogueId, $bankAccounts);

        return view('reports.bank-account-breakdown', compact('bankAccounts', 'selectedCatalogue', 'orders'));
    }

    public function bankAccountBreakdownPdf(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $bankAccounts      = BankAccount::orderBy('title')->get();
        $orders            = $this->loadBankBreakdownData($catalogueId, $bankAccounts);

        return Pdf::loadView('reports.bank-account-breakdown-pdf', compact('selectedCatalogue', 'bankAccounts', 'orders'))
            ->setPaper('a4', 'landscape')
            ->download('bank-account-breakdown-' . str($selectedCatalogue->name)->slug() . '.pdf');
    }

    // ── Receivables by Bank ───────────────────────────────────────────────────

    public function receivablesByBank(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $bankAccounts      = BankAccount::orderBy('title')->get();
        $orders            = $this->loadBankBreakdownData($catalogueId, $bankAccounts, receivableOnly: true);

        return view('reports.receivables-by-bank', compact('bankAccounts', 'selectedCatalogue', 'orders'));
    }

    public function receivablesByBankPdf(Request $request)
    {
        $catalogueId       = (int) session('active_catalogue_id');
        $selectedCatalogue = Catalogue::findOrFail($catalogueId);
        $bankAccounts      = BankAccount::orderBy('title')->get();
        $orders            = $this->loadBankBreakdownData($catalogueId, $bankAccounts, receivableOnly: true);

        return Pdf::loadView('reports.receivables-by-bank-pdf', compact('selectedCatalogue', 'bankAccounts', 'orders'))
            ->setPaper('a4', 'landscape')
            ->download('receivables-by-bank-' . str($selectedCatalogue->name)->slug() . '.pdf');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function loadOrderBillData(int $catalogueId): \Illuminate\Support\Collection
    {
        return Order::with(['customer', 'items', 'payments'])
            ->where('catalogue_id', $catalogueId)
            ->whereIn('status', ['confirmed', 'stitching', 'dispatched'])
            ->orderBy('submitted_at')
            ->get()
            ->map(function (Order $order) {
                $order->agg_xs    = $order->items->sum('qty_xs');
                $order->agg_s     = $order->items->sum('qty_s');
                $order->agg_m     = $order->items->sum('qty_m');
                $order->agg_l     = $order->items->sum('qty_l');
                $order->agg_xl    = $order->items->sum('qty_xl');
                $order->agg_total = $order->items->sum('total_qty');
                $order->agg_rate  = $order->agg_total > 0
                    ? (int) round($order->total_amount / $order->agg_total)
                    : 0;
                $order->title_given_label = $order->payments
                    ->pluck('title_given')
                    ->filter()
                    ->unique()
                    ->implode(' / ') ?: '—';
                return $order;
            });
    }

    private function loadBankBreakdownData(int $catalogueId, \Illuminate\Support\Collection $bankAccounts, bool $receivableOnly = false): \Illuminate\Support\Collection
    {
        $query = Order::with(['customer', 'payments'])
            ->where('catalogue_id', $catalogueId)
            ->whereIn('status', ['confirmed', 'stitching', 'dispatched']);

        if ($receivableOnly) {
            $query->where('outstanding_balance', '>', 0);
        }

        return $query->orderBy('submitted_at')
            ->get()
            ->map(function (Order $order) use ($bankAccounts) {
                $order->bank_totals = $bankAccounts->mapWithKeys(fn($ba) => [
                    $ba->id => (float) $order->payments
                        ->where('bank_account_id', $ba->id)
                        ->sum('amount'),
                ]);
                $order->misc_total = (float) $order->payments
                    ->whereNull('bank_account_id')
                    ->sum('amount');
                $order->title_given_label = $order->payments
                    ->pluck('title_given')
                    ->filter()
                    ->unique()
                    ->implode(' / ') ?: '—';
                return $order;
            });
    }
}
