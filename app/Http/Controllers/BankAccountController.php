<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $bankAccount = BankAccount::create([
            'title'     => $request->title,
            'is_active' => true,
        ]);

        activity()
            ->performedOn($bankAccount)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'title'      => $bankAccount->title,
                'status'     => 'Active',
                'created_by' => Auth::user()->name,
            ])
            ->log('Bank account "' . $bankAccount->title . '" created');

        return back()->with('success', "Bank account \"{$request->title}\" added.");
    }

    public function toggle(BankAccount $bankAccount)
    {
        $previousState = $bankAccount->is_active ? 'active' : 'inactive';
        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);
        $newState = $bankAccount->is_active ? 'active' : 'inactive';

        activity()
            ->performedOn($bankAccount)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'title'          => $bankAccount->title,
                'status_changed' => $previousState . ' → ' . $newState,
                'action_by'      => Auth::user()->name,
            ])
            ->log('Bank account "' . $bankAccount->title . '" ' . $newState);

        $state = $bankAccount->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$bankAccount->title}\" {$state}.");
    }
}
