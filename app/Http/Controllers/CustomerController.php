<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')->orderBy('name')->paginate(20);
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_number' => 'required|string|max:30',
            'city'           => 'required|string|max:100',
            'email'          => 'required|email|unique:customers,email',
        ]);

        $validated['created_by'] = auth()->id();

        $customer = Customer::create($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer "' . $customer->name . '" added.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['orders' => fn($q) => $q->latest()->take(10)]);
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_number' => 'required|string|max:30',
            'city'           => 'required|string|max:100',
            'email'          => 'required|email|unique:customers,email,' . $customer->id,
        ]);

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated.');
    }
}
