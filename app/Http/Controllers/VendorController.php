<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::orderBy('name')->get();

        return view('admin.vendors.index', compact('vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:vendors,name',
        ]);

        $vendor = Vendor::create([
            'name'      => $request->name,
            'is_active' => true,
        ]);

        activity()
            ->performedOn($vendor)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'name'       => $vendor->name,
                'status'     => 'Active',
                'created_by' => Auth::user()->name,
            ])
            ->log('Vendor "' . $vendor->name . '" created');

        return back()->with('success', "Vendor \"{$request->name}\" added.");
    }

    public function toggle(Vendor $vendor)
    {
        $previousState = $vendor->is_active ? 'active' : 'inactive';
        $vendor->update(['is_active' => ! $vendor->is_active]);
        $newState = $vendor->is_active ? 'active' : 'inactive';

        activity()
            ->performedOn($vendor)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'name'           => $vendor->name,
                'status_changed' => $previousState . ' → ' . $newState,
                'action_by'      => Auth::user()->name,
            ])
            ->log('Vendor "' . $vendor->name . '" ' . $newState);

        $state = $vendor->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$vendor->name}\" {$state}.");
    }
}
