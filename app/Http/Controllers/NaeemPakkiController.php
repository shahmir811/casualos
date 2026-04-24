<?php

namespace App\Http\Controllers;

use App\Models\NaeemPakkiSend;
use App\Models\NaeemPakkiReturn;
use App\Models\ProductionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NaeemPakkiController extends Controller
{
    public function index()
    {
        $sends = NaeemPakkiSend::with(['assignment.design.catalogue', 'returns'])->latest()->paginate(20);
        return view('production.naeem-pakki.index', compact('sends'));
    }

    public function create()
    {
        $assignments = ProductionAssignment::with('design.catalogue')
            ->where('destination', 'naeem_pakki')
            ->get();
        return view('production.naeem-pakki.create', compact('assignments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_assignment_id' => 'required|exists:production_assignments,id',
            'sent_date'                => 'required|date',
            'per_piece_price'          => 'required|numeric|min:0',
            'items'                    => 'required|array',
            'items.*.size'             => 'required|in:xs,s,m,l,xl',
            'items.*.quantity'         => 'required|integer|min:0',
        ]);

        $send = NaeemPakkiSend::create([
            'production_assignment_id' => $validated['production_assignment_id'],
            'sent_date'                => $validated['sent_date'],
            'per_piece_price'          => $validated['per_piece_price'],
            'logged_by'                => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['quantity'] > 0) {
                $send->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        return redirect()->route('naeem-pakki-sends.show', $send)
            ->with('success', 'Naeem Pakki send recorded.');
    }

    public function show(NaeemPakkiSend $naeemPakkiSend)
    {
        $naeemPakkiSend->load(['assignment.design.catalogue', 'items', 'returns.items', 'loggedBy']);
        return view('production.naeem-pakki.show', compact('naeemPakkiSend'));
    }

    public function logReturn(Request $request, NaeemPakkiSend $send)
    {
        $validated = $request->validate([
            'return_date'  => 'required|date',
            'items'        => 'required|array',
            'items.*.size' => 'required|in:xs,s,m,l,xl',
            'items.*.qty'  => 'required|integer|min:0',
        ]);

        $return = NaeemPakkiReturn::create([
            'naeem_pakki_send_id' => $send->id,
            'return_date'         => $validated['return_date'],
            'logged_by'           => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['qty'] > 0) {
                $return->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        return back()->with('success', 'Return of ' . array_sum(array_column($validated['items'], 'qty')) . ' pieces logged.');
    }
}
