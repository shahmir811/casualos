<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Order;
use App\Models\CustomerLedger;
use App\Models\PressPack;
use App\Models\Wage;
use App\Models\Design;
use App\Models\DispatchBatch;
use App\Models\OrderReduction;
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
        $records = PressPack::with(['catalogue', 'design', 'items'])
            ->orderBy('packed_date', 'desc')
            ->get();

        return view('reports.packed-inventory', compact('records'));
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
}
