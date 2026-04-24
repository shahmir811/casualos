<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function show(Customer $customer)
    {
        $entries = CustomerLedger::where('customer_id', $customer->id)
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        // Positive amounts = charges, Negative amounts = credits/payments
        $balance = CustomerLedger::where('customer_id', $customer->id)->sum('amount');

        return view('customers.ledger', compact('customer', 'entries', 'balance'));
    }
}
