<?php

namespace App\Http\Controllers;

use App\Models\PressPack;
use App\Models\Catalogue;
use App\Models\Design;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PressPackController extends Controller
{
    public function index()
    {
        $records = PressPack::with(['catalogue', 'design', 'loggedBy'])->latest()->paginate(20);
        return view('production.press-pack.index', compact('records'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')->with('designs')->orderBy('name')->get();
        return view('production.press-pack.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'design_id'    => 'required|exists:designs,id',
            'packed_date'  => 'required|date',
            'items'        => 'required|array',
            'items.*.size' => 'required|in:xs,s,m,l,xl',
            'items.*.qty'  => 'required|integer|min:0',
        ]);

        $record = PressPack::create([
            'catalogue_id' => $validated['catalogue_id'],
            'design_id'    => $validated['design_id'],
            'packed_date'  => $validated['packed_date'],
            'logged_by'    => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['qty'] > 0) {
                $record->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        return redirect()->route('press-pack.index')->with('success', 'Press & Pack record saved.');
    }

    public function inventory()
    {
        $records = PressPack::with(['catalogue', 'design', 'items'])
            ->orderBy('packed_date', 'desc')
            ->paginate(20);

        return view('production.press-pack.inventory', compact('records'));
    }
}
