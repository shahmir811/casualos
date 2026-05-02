<?php

namespace App\Http\Controllers;

use App\Models\TarpaiSend;
use App\Models\TarpaiReturn;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TarpaiController extends Controller
{
    public function index()
    {
        $sends = TarpaiSend::with(['catalogue', 'design', 'returns'])->latest()->paginate(20);
        return view('production.tarpai.index', compact('sends'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();
        return view('production.tarpai.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'    => 'required|exists:catalogues,id',
            'design_id'       => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'sent_date'       => 'required|date',
            'per_piece_price' => 'required|numeric|min:0',
            'items'           => 'required|array',
            'items.*.size'    => 'required|in:xs,s,m,l,xl',
            'items.*.qty'     => 'required|integer|min:0',
        ]);

        // Ensure at least one piece is being sent
        $totalPieces = collect($validated['items'])->sum(fn($i) => (int) $i['qty']);
        if ($totalPieces === 0) {
            return back()
                ->withInput()
                ->withErrors(['items' => 'Please enter at least one piece quantity to log a Tarpai send.']);
        }

        $send = TarpaiSend::create([
            'catalogue_id'    => $validated['catalogue_id'],
            'design_id'       => $validated['design_id'],
            'sent_date'       => $validated['sent_date'],
            'per_piece_price' => $validated['per_piece_price'],
            'logged_by'       => Auth::id(),
        ]);

        // Save per-size quantities
        foreach ($validated['items'] as $item) {
            if ((int) $item['qty'] > 0) {
                $send->items()->create([
                    'size'     => $item['size'],
                    'quantity' => (int) $item['qty'],
                ]);
            }
        }

        return redirect()->route('tarpai-sends.show', $send)
            ->with('success', 'Tarpai send recorded.');
    }

    public function show(TarpaiSend $tarpaiSend)
    {
        $tarpaiSend->load(['catalogue', 'design', 'items', 'returns.items', 'loggedBy']);
        return view('production.tarpai.show', compact('tarpaiSend'));
    }

    public function logReturn(Request $request, TarpaiSend $send)
    {
        $validated = $request->validate([
            'return_date' => 'required|date',
            'items'       => 'required|array',
            'items.*.size'=> 'required|in:xs,s,m,l,xl',
            'items.*.qty' => 'required|integer|min:0',
        ]);

        $return = TarpaiReturn::create([
            'tarpai_send_id' => $send->id,
            'return_date'    => $validated['return_date'],
            'logged_by'      => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['qty'] > 0) {
                $return->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        return back()->with('success', 'Tarpai return logged.');
    }
}
