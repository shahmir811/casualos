<?php

namespace App\Http\Controllers;

use App\Models\SizeChart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-only management of the single global size-chart image. Not
 * catalogue-specific and not customer-facing — purely an internal
 * reference image, optional, replaced in place on re-upload.
 */
class SizeChartController extends Controller
{
    public function index()
    {
        $sizeChart = SizeChart::current();

        return view('admin.size-chart.index', compact('sizeChart'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $sizeChart = SizeChart::current();
        $oldPath = $sizeChart->image_path;

        $path = $request->file('image')->store('size-charts', 's3');

        $sizeChart->update([
            'image_path'         => $path,
            'original_filename'  => $request->file('image')->getClientOriginalName(),
            'file_size'          => $request->file('image')->getSize(),
            'uploaded_by'        => Auth::id(),
            'uploaded_at'        => now(),
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('s3')->delete($oldPath);
        }

        return back()->with('success', 'Size chart uploaded.');
    }

    public function destroy()
    {
        $sizeChart = SizeChart::current();

        abort_unless($sizeChart->image_path, 404);

        Storage::disk('s3')->delete($sizeChart->image_path);

        $sizeChart->update([
            'image_path'        => null,
            'original_filename' => null,
            'file_size'         => null,
            'uploaded_by'       => null,
            'uploaded_at'       => null,
        ]);

        return back()->with('success', 'Size chart deleted.');
    }
}
