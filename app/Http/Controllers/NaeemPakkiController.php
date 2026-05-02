<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\NaeemPakkiSend;
use App\Models\NaeemPakkiReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NaeemPakkiController extends Controller
{
    public function index()
    {
        $sends = NaeemPakkiSend::with(['catalogue', 'design', 'returns'])
            ->latest()
            ->paginate(20);

        return view('production.naeem-pakki.index', compact('sends'));
    }

    public function create()
    {
        // Only in-house designs that require Naeem Pakki work, from open catalogues
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q
                ->where('manufacturing_type', 'in_house')
                ->where('needs_naeem_pakki', true)
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn($c) => $c->designs->isNotEmpty())
            ->values();

        return view('production.naeem-pakki.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'    => 'required|exists:catalogues,id',
            'design_id'       => 'required|exists:designs,id',
            'sent_date'       => 'required|date',
            'quantity'        => 'required|integer|min:1',
            'per_piece_price' => 'required|numeric|min:0',
        ]);

        $send = NaeemPakkiSend::create([
            'catalogue_id'    => $validated['catalogue_id'],
            'design_id'       => $validated['design_id'],
            'sent_date'       => $validated['sent_date'],
            'quantity'        => $validated['quantity'],
            'per_piece_price' => $validated['per_piece_price'],
            'logged_by'       => Auth::id(),
        ]);

        return redirect()->route('naeem-pakki-sends.show', $send)
            ->with('success', 'Naeem Pakki send of ' . $validated['quantity'] . ' pieces recorded.');
    }

    public function show(NaeemPakkiSend $naeemPakkiSend)
    {
        $naeemPakkiSend->load(['catalogue', 'design', 'returns.loggedBy', 'loggedBy']);
        return view('production.naeem-pakki.show', compact('naeemPakkiSend'));
    }

    public function logReturn(Request $request, NaeemPakkiSend $send)
    {
        $outstanding = $send->outstandingPieces();

        $validated = $request->validate([
            'return_date' => 'required|date',
            'quantity'    => 'required|integer|min:1|max:' . $outstanding,
        ]);

        NaeemPakkiReturn::create([
            'naeem_pakki_send_id' => $send->id,
            'return_date'         => $validated['return_date'],
            'quantity'            => $validated['quantity'],
            'logged_by'           => Auth::id(),
        ]);

        return back()->with('success', $validated['quantity'] . ' pieces returned from Naeem Pakki.');
    }
}
