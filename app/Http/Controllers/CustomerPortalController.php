<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function show(string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();
        return view('portal.show', compact('customer'));
    }

    public function verify(Request $request, string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        if (strtolower($customer->email) !== strtolower($validated['email'])) {
            return back()->with('error', 'Email address does not match our records.');
        }

        session(['portal_verified_' . $customer->id => true]);

        $customer->load(['orders.items.design', 'orders.catalogue', 'ledger']);

        return view('portal.dashboard', compact('customer'));
    }
}
