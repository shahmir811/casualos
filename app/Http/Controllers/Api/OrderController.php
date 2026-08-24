<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderPlacementException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderResource;
use App\Http\Resources\Api\OrderSummaryResource;
use App\Models\Catalogue;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderPlacementService $orders) {}

    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['catalogue', 'items'])
            ->get();

        return response()->json([
            'orders' => OrderSummaryResource::collection($orders),
        ]);
    }

    /**
     * Route-model-bound, but ownership is checked explicitly before anything
     * else is touched — binding alone does not scope to the authenticated
     * customer. A mismatch returns 404, never 403, so a customer probing
     * another customer's order id can't even confirm it exists.
     */
    public function show(Request $request, Order $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        $order->load([
            'catalogue',
            'items.design',
            'payments.order',
            'payments.bankAccount',
            'dispatchBatches.items.design',
            'reductions.items',
        ]);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Places an order for the authenticated customer. All pricing and write
     * logic lives in OrderPlacementService::place() — shared byte-for-byte
     * with PublicOrderController::submit() — so this method only turns the
     * request into service arguments and turns service failures into a JSON
     * error body (the same split that controller's docblock describes).
     *
     * Unlike the web form, no email lookup is needed: $request->user() *is*
     * the customer, so resolveCustomerByEmail() is never called here and
     * CUSTOMER_NOT_FOUND can't occur on this path.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|integer|exists:catalogues,id',
            'qty_xs'       => 'nullable|integer|min:0',
            'qty_s'        => 'nullable|integer|min:0',
            'qty_m'        => 'nullable|integer|min:0',
            'qty_l'        => 'nullable|integer|min:0',
            'qty_xl'       => 'nullable|integer|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $catalogue = Catalogue::with('designs')->findOrFail($validated['catalogue_id']);
        $customer  = $request->user();
        $sizes     = $this->orders->normaliseSizes($validated);

        try {
            $order = $this->orders->place($catalogue, $customer, $sizes, [
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (OrderPlacementException $e) {
            $status = match ($e->reason()) {
                OrderPlacementException::DUPLICATE_ORDER => 409,
                default                                  => 422,
            };

            return response()->json([
                'message' => $e->getMessage(),
                'reason'  => $e->reason(),
            ], $status);
        }

        $order->load([
            'catalogue',
            'items.design',
            'payments.order',
            'payments.bankAccount',
            'dispatchBatches.items.design',
            'reductions.items',
        ]);

        return response()->json([
            'order' => new OrderResource($order),
        ], 201);
    }
}
