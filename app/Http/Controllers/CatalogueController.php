<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CatalogueController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Authorization helper — only admin can create / edit / close / reopen
    |--------------------------------------------------------------------------
    */
    private function adminOnly(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }

    /**
     * GET /catalogues
     * All authenticated users can view catalogues list.
     */
    public function index()
    {
        $catalogues = Catalogue::withCount('designs')
            ->withCount(['orders as orders_count'])
            ->latest()
            ->paginate(15);

        return view('catalogues.index', compact('catalogues'));
    }

    /**
     * GET /catalogues/create  (admin only)
     */
    public function create()
    {
        $this->adminOnly();
        return view('catalogues.create');
    }

    /**
     * POST /catalogues  (admin only)
     */
    public function store(Request $request)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'cover_photo'      => 'nullable|image|max:10240',
            'total_pieces'     => 'required|integer|min:1',
            'number_of_designs'=> 'required|integer|min:1',
            'wage_rate'        => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status']     = 'open';

        if ($request->hasFile('cover_photo')) {
            $validated['cover_photo'] = $request->file('cover_photo')
                ->store('catalogues', 'public');
        }

        $catalogue = Catalogue::create($validated);

        return redirect()->route('catalogues.show', $catalogue)
            ->with('success', 'Catalogue "' . $catalogue->name . '" created successfully.');
    }

    /**
     * GET /catalogues/{catalogue}
     * All authenticated users can view a catalogue.
     */
    public function show(Catalogue $catalogue)
    {
        $catalogue->load(['designs' => fn($q) => $q->orderBy('sort_order'), 'createdBy']);

        $ordersCount  = $catalogue->orders()->count();
        $totalOrdered = $catalogue->orders()
            ->whereIn('status', ['received', 'confirmed', 'stitching', 'dispatched'])
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('SUM(order_items.qty_xs + order_items.qty_s + order_items.qty_m + order_items.qty_l + order_items.qty_xl) as total')
            ->value('total') ?? 0;

        $available = max(0, $catalogue->total_pieces - (int) $totalOrdered);

        // Generate shareable public order URL
        $shareUrl = $catalogue->order_token
            ? route('order.public', $catalogue->order_token)
            : null;

        return view('catalogues.show', compact('catalogue', 'ordersCount', 'totalOrdered', 'available', 'shareUrl'));
    }

    /**
     * GET /catalogues/{catalogue}/edit  (admin only)
     */
    public function edit(Catalogue $catalogue)
    {
        $this->adminOnly();
        return view('catalogues.edit', compact('catalogue'));
    }

    /**
     * PUT /catalogues/{catalogue}  (admin only)
     */
    public function update(Request $request, Catalogue $catalogue)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'cover_photo'      => 'nullable|image|max:10240',
            'total_pieces'     => 'required|integer|min:1',
            'number_of_designs'=> 'required|integer|min:1',
            'wage_rate'        => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        if ($request->hasFile('cover_photo')) {
            if ($catalogue->cover_photo) {
                Storage::disk('public')->delete($catalogue->cover_photo);
            }
            $validated['cover_photo'] = $request->file('cover_photo')
                ->store('catalogues', 'public');
        }

        $catalogue->update($validated);

        return redirect()->route('catalogues.show', $catalogue)
            ->with('success', 'Catalogue updated successfully.');
    }

    /**
     * DELETE /catalogues/{catalogue}  (admin only — only if no orders)
     */
    public function destroy(Catalogue $catalogue)
    {
        $this->adminOnly();

        if ($catalogue->orders()->exists()) {
            return back()->with('error', 'Cannot delete a catalogue that has orders.');
        }

        if ($catalogue->cover_photo) {
            Storage::disk('public')->delete($catalogue->cover_photo);
        }

        $catalogue->delete();

        return redirect()->route('catalogues.index')
            ->with('success', 'Catalogue deleted.');
    }

    /**
     * POST /catalogues/{catalogue}/close  (admin only)
     */
    public function close(Catalogue $catalogue)
    {
        $this->adminOnly();

        $catalogue->update(['status' => 'closed']);

        return back()->with('success', 'Catalogue "' . $catalogue->name . '" has been closed.');
    }

    /**
     * POST /catalogues/{catalogue}/reopen  (admin only)
     */
    public function reopen(Catalogue $catalogue)
    {
        $this->adminOnly();

        $catalogue->update(['status' => 'open']);

        return back()->with('success', 'Catalogue "' . $catalogue->name . '" is now open.');
    }
}
