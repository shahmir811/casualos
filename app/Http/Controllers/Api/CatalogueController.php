<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CatalogueResource;
use App\Http\Resources\Api\CatalogueSummaryResource;
use App\Models\Catalogue;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogueController extends Controller
{
    public function __construct(protected OrderPlacementService $orders) {}

    public function index(Request $request)
    {
        $catalogues = Catalogue::where('status', 'open')->latest()->get();

        $this->markAlreadyOrdered($catalogues, $request->user());

        return response()->json([
            'catalogues' => CatalogueSummaryResource::collection($catalogues),
        ]);
    }

    public function show(Request $request, Catalogue $catalogue)
    {
        $catalogue->load(['designs' => fn ($q) => $q->orderBy('sort_order')]);

        $this->markAlreadyOrdered(collect([$catalogue]), $request->user());

        return response()->json([
            'catalogue' => new CatalogueResource($catalogue),
        ]);
    }

    /**
     * Pure pricing preview — thin wrapper over OrderPlacementService::quote().
     * Safe to call on every keystroke; never reimplement this math client-side
     * (see the docblock on quote() itself).
     */
    public function quote(Request $request, Catalogue $catalogue)
    {
        $request->validate([
            'qty_xs' => 'nullable|integer|min:0',
            'qty_s'  => 'nullable|integer|min:0',
            'qty_m'  => 'nullable|integer|min:0',
            'qty_l'  => 'nullable|integer|min:0',
            'qty_xl' => 'nullable|integer|min:0',
        ]);

        $sizes = $this->orders->normaliseSizes($request->all());

        try {
            $quote = $this->orders->quote($catalogue, $sizes);
        } catch (OrderPlacementException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason'  => $e->reason(),
            ], 422);
        }

        return response()->json(['quote' => $quote]);
    }

    /**
     * Fresh short-lived presigned S3 URL for the catalogue's book PDF —
     * generated on demand rather than embedded in the list/show payload, so
     * a link the app opens minutes after loading the catalogue screen hasn't
     * already expired. `has_catalogue_book` on the list/show resources is
     * what the app uses to decide whether to show a "View Catalog Book"
     * action at all.
     */
    public function book(Catalogue $catalogue)
    {
        abort_unless($catalogue->catalogue_book_path, 404);

        $filename = str_replace('"', '', $catalogue->catalogue_book_original_filename ?? 'catalogue-book.pdf');

        $url = Storage::disk('s3')->temporaryUrl(
            $catalogue->catalogue_book_path,
            now()->addMinutes(10),
            ['ResponseContentDisposition' => 'inline; filename="'.$filename.'"']
        );

        return response()->json(['url' => $url]);
    }

    /**
     * Batch-computes "already ordered this catalogue" for the given
     * customer and stamps it onto each Catalogue instance — one query
     * regardless of list size, instead of an exists() query per row.
     */
    protected function markAlreadyOrdered($catalogues, $customer): void
    {
        $orderedCatalogueIds = Order::where('customer_id', $customer->id)
            ->whereIn('catalogue_id', $catalogues->pluck('id'))
            ->pluck('catalogue_id')
            ->unique();

        $catalogues->each(function (Catalogue $catalogue) use ($orderedCatalogueIds) {
            $catalogue->already_ordered = $orderedCatalogueIds->contains($catalogue->id);
        });
    }
}
