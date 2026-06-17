<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogueController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Authorization helpers
    |--------------------------------------------------------------------------
    */
    private function adminOnly(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }

    private function adminOrProductionManager(): void
    {
        if (! in_array(Auth::user()->role, ['admin', 'production_manager', 'creative_head'])) {
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
     * GET /catalogues/create  (admin + production_manager)
     */
    public function create()
    {
        $this->adminOrProductionManager();
        return view('catalogues.create');
    }

    /**
     * POST /catalogues  (admin + production_manager)
     */
    public function store(Request $request)
    {
        $this->adminOrProductionManager();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'cover_photo'        => 'nullable|image|max:10240',
            'qty_per_design'     => 'required|integer|min:1',
            'number_of_designs'  => 'required|integer|min:1',
            'quantity_benchmark' => 'nullable|integer|min:1',
            'notes'              => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status']     = 'open';

        if ($request->hasFile('cover_photo')) {
            $file = $request->file('cover_photo');
            $validated['cover_photo']    = $file->store('catalogues');
            $validated['cover_photo_og'] = $this->generateOgImage($file);
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

        // Total pieces ordered = across all designs × all sizes (sum of order_items)
        $totalOrdered = $catalogue->orders()
            ->whereIn('status', ['received', 'confirmed', 'stitching', 'dispatched'])
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('SUM(order_items.qty_xs + order_items.qty_s + order_items.qty_m + order_items.qty_l + order_items.qty_xl) as total')
            ->value('total') ?? 0;

        // Total suits ordered = total pieces ÷ number of designs
        // (each order has one order_item per design with identical quantities)
        $designCount     = $catalogue->designs->count();
        $totalQtyOrdered = $designCount > 0 ? (int) round($totalOrdered / $designCount) : 0;

        // Total production = qty_per_design × number_of_designs
        $available = max(0, $catalogue->totalPieces() - (int) $totalOrdered);

        // Generate shareable public order URL
        $shareUrl = $catalogue->order_token
            ? route('order.public', $catalogue->order_token)
            : null;

        return view('catalogues.show', compact('catalogue', 'ordersCount', 'totalQtyOrdered', 'totalOrdered', 'available', 'shareUrl'));
    }

    /**
     * GET /catalogues/{catalogue}/edit  (admin + production_manager)
     */
    public function edit(Catalogue $catalogue)
    {
        $this->adminOrProductionManager();
        return view('catalogues.edit', compact('catalogue'));
    }

    /**
     * PUT /catalogues/{catalogue}  (admin + production_manager)
     */
    public function update(Request $request, Catalogue $catalogue)
    {
        $this->adminOrProductionManager();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'cover_photo'        => 'nullable|image|max:10240',
            'qty_per_design'     => 'required|integer|min:1',
            'number_of_designs'  => 'required|integer|min:1',
            'quantity_benchmark' => 'nullable|integer|min:1',
            'notes'              => 'nullable|string',
        ]);

        if ($request->hasFile('cover_photo')) {
            if ($catalogue->cover_photo) {
                Storage::delete($catalogue->cover_photo);
            }
            if ($catalogue->cover_photo_og) {
                Storage::delete($catalogue->cover_photo_og);
            }
            $file = $request->file('cover_photo');
            $validated['cover_photo']    = $file->store('catalogues');
            $validated['cover_photo_og'] = $this->generateOgImage($file);
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
            Storage::delete($catalogue->cover_photo);
        }
        if ($catalogue->cover_photo_og) {
            Storage::delete($catalogue->cover_photo_og);
        }

        $catalogue->delete();

        return redirect()->route('catalogues.index')
            ->with('success', 'Catalogue deleted.');
    }

    /**
     * POST /catalogues/{catalogue}/close  (admin + production_manager)
     */
    public function close(Catalogue $catalogue)
    {
        $this->adminOrProductionManager();

        $catalogue->update(['status' => 'closed']);

        return back()->with('success', 'Catalogue "' . $catalogue->name . '" has been closed.');
    }

    /**
     * POST /catalogues/{catalogue}/reopen  (admin + production_manager)
     */
    public function reopen(Catalogue $catalogue)
    {
        $this->adminOrProductionManager();

        $catalogue->update(['status' => 'open']);

        return back()->with('success', 'Catalogue "' . $catalogue->name . '" is now open.');
    }

    private function generateOgImage(UploadedFile $file): string
    {
        $source = imagecreatefromstring($file->getContent());

        $srcW = imagesx($source);
        $srcH = imagesy($source);

        // Cover crop to 1200×630
        if (($srcW / $srcH) > (1200 / 630)) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * (1200 / 630));
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / (1200 / 630));
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $canvas = imagecreatetruecolor(1200, 630);
        imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, 1200, 630, $cropW, $cropH);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        $path = 'catalogues/og/' . Str::uuid() . '.jpg';
        Storage::put($path, $jpeg);

        return $path;
    }
}
