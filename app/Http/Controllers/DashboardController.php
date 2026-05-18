<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Order;
use App\Models\FabricBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $role = $user->role;

        $data = [];

        if (in_array($role, ['admin', 'accountant'])) {
            $data['totalCustomers']    = Customer::count();
            $data['openCatalogues']    = Catalogue::where('status', 'open')->count();
            $data['ordersToday']       = Order::whereDate('created_at', today())->count();
            $data['flaggedOrders']     = Order::where('is_flagged', true)->count();
            $data['pendingOrders']     = Order::where('status', 'received')->count();
            $data['recentOrders']      = Order::with(['customer', 'catalogue'])
                                            ->latest()
                                            ->take(5)
                                            ->get();
        }

        if (in_array($role, ['admin', 'production_manager'])) {
            $data['activeCatalogues']  = Catalogue::where('status', 'open')->with('designs')->get();
            $data['fabricBatches']     = FabricBatch::latest()->take(5)->get();
        }

        if ($role === 'creative_head') {
            $data['catalogues']        = Catalogue::latest()->take(10)->get();
        }

        return view('dashboard.index', compact('data', 'user'));
    }
}
