<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Design;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DesignController extends Controller
{
    private function adminOrDesigner(): void
    {
        if (!in_array(Auth::user()->role, ['admin', 'designer'])) {
            abort(403);
        }
    }

    private function adminOnly(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }

    /**
     * GET /catalogues/{catalogue}/designs
     * Designs are displayed on the catalogue show page — redirect there.
     */
    public function index(Catalogue $catalogue)
    {
        return redirect()->route('catalogues.show', $catalogue);
    }

    /**
     * GET /catalogues/{catalogue}/designs/create
     */
    public function create(Catalogue $catalogue)
    {
        $this->adminOrDesigner();
        return view('catalogues.designs.create', compact('catalogue'));
    }

    /**
     * POST /catalogues/{catalogue}/designs
     */
    public function store(Request $request, Catalogue $catalogue)
    {
        $this->adminOrDesigner();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'photo'              => 'nullable|image|max:10240',
            'normal_price'       => 'required|numeric|min:0',
            'discount_price'     => 'nullable|numeric|min:0',
            'manufacturing_type' => 'required|in:in_house,outsourced',
            'needs_naeem_pakki'  => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
        ]);

        $validated['catalogue_id']    = $catalogue->id;
        $validated['sort_order']      = $validated['sort_order'] ?? ($catalogue->designs()->max('sort_order') + 1);
        // Only in-house designs can need Naeem Pakki work
        $validated['needs_naeem_pakki'] = ($validated['manufacturing_type'] === 'in_house')
            && !empty($validated['needs_naeem_pakki']);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('designs', 'public');
        }

        $design = Design::create($validated);

        return redirect()->route('catalogues.show', $catalogue)
            ->with('success', 'Design "' . $design->name . '" added to catalogue.');
    }

    /**
     * GET /designs/{design}  (shallow)
     */
    public function show(Design $design)
    {
        $design->load('catalogue');
        return view('catalogues.designs.show', compact('design'));
    }

    /**
     * GET /designs/{design}/edit  (shallow)
     */
    public function edit(Design $design)
    {
        $this->adminOrDesigner();
        $design->load('catalogue');
        return view('catalogues.designs.edit', compact('design'));
    }

    /**
     * PUT /designs/{design}  (shallow)
     */
    public function update(Request $request, Design $design)
    {
        $this->adminOrDesigner();

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'photo'              => 'nullable|image|max:10240',
            'normal_price'       => 'required|numeric|min:0',
            'discount_price'     => 'nullable|numeric|min:0',
            'manufacturing_type' => 'required|in:in_house,outsourced',
            'needs_naeem_pakki'  => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
        ]);

        // Only in-house designs can need Naeem Pakki work
        $validated['needs_naeem_pakki'] = ($validated['manufacturing_type'] === 'in_house')
            && !empty($validated['needs_naeem_pakki']);

        if ($request->hasFile('photo')) {
            if ($design->photo) {
                Storage::disk('public')->delete($design->photo);
            }
            $validated['photo'] = $request->file('photo')->store('designs', 'public');
        }

        $design->update($validated);

        return redirect()->route('catalogues.show', $design->catalogue)
            ->with('success', 'Design updated successfully.');
    }

    /**
     * DELETE /designs/{design}  (shallow — admin only, only if no order items)
     */
    public function destroy(Design $design)
    {
        $this->adminOnly();

        if ($design->orderItems()->exists()) {
            return back()->with('error', 'Cannot delete a design that has been ordered.');
        }

        if ($design->photo) {
            Storage::disk('public')->delete($design->photo);
        }

        $catalogueId = $design->catalogue_id;
        $design->delete();

        return redirect()->route('catalogues.show', $catalogueId)
            ->with('success', 'Design deleted.');
    }
}
