<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $bankAccounts = BankAccount::orderBy('title')->get();

        return view('admin.bank-accounts.index', compact('bankAccounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100|unique:bank_accounts,title',
        ]);

        BankAccount::create([
            'title'     => $request->title,
            'is_active' => true,
        ]);

        return back()->with('success', "Bank account \"{$request->title}\" added.");
    }

    public function toggle(BankAccount $bankAccount)
    {
        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        $state = $bankAccount->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$bankAccount->title}\" {$state}.");
    }
}
