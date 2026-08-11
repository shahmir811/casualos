<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Catalogue;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\Request;

class PublicOrderController extends Controller
{
    public function __construct(protected OrderPlacementService $orders) {}

    public function show(string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)
            ->with(['designs' => fn($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        // Sold-out: only when admin explicitly closes the catalogue
        $soldOut = $catalogue->status !== 'open';

        return view('public.order', compact('catalogue', 'soldOut'));
    }

    /**
     * All order maths and writes live in OrderPlacementService so that the
     * mobile app's POST /api/orders produces identical totals. This method is
     * only responsible for turning the form request into service arguments,
     * and turning service failures into redirects.
     */
    public function submit(Request $request, string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)
            ->with(['designs'])
            ->firstOrFail();

        // Guard: reject submission if catalogue has been closed by admin
        if ($catalogue->status !== 'open') {
            return redirect()->route('order.public', $token);
        }

        // Validate customer details + collective size quantities
        $request->validate([
            'customer_name'   => 'required|string|max:255',
            'submitted_email' => 'required|email|max:255',
            'city'            => 'required|string|max:100',
            'notes'           => 'nullable|string|max:1000',
            'qty_xs'          => 'nullable|integer|min:0',
            'qty_s'           => 'nullable|integer|min:0',
            'qty_m'           => 'nullable|integer|min:0',
            'qty_l'           => 'nullable|integer|min:0',
            'qty_xl'          => 'nullable|integer|min:0',
        ]);

        $sizes = $this->orders->normaliseSizes($request->all());

        try {
            // Priced first so an empty order is rejected before we go looking
            // for the customer — preserves the original guard ordering.
            $this->orders->quote($catalogue, $sizes);

            $customer = $this->orders->resolveCustomerByEmail($request->input('submitted_email'));

            $this->orders->assertNotAlreadyOrdered($catalogue, $customer);

            $order = $this->orders->place($catalogue, $customer, $sizes, [
                'name'  => $request->input('customer_name'),
                'city'  => $request->input('city'),
                'email' => $request->input('submitted_email'),
                'notes' => $request->input('notes'),
            ]);
        } catch (OrderPlacementException $e) {
            return match ($e->reason()) {
                OrderPlacementException::NO_QUANTITY => back()
                    ->withErrors(['qty_s' => $e->getMessage()])
                    ->withInput(),

                OrderPlacementException::CUSTOMER_NOT_FOUND => back()
                    ->withInput()
                    ->with('customer_not_found', true),

                OrderPlacementException::DUPLICATE_ORDER => back()
                    ->withInput()
                    ->with('duplicate_order', true),

                default => redirect()->route('order.public', $token),
            };
        }

        // Store order ID in session for the thank-you page
        session(['last_order_id' => $order->id]);

        return redirect()->route('order.thankyou', $token);
    }

    public function thankyou(string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)->firstOrFail();

        $orderId = session('last_order_id');
        $order   = $orderId ? Order::with('catalogue')->find($orderId) : null;

        return view('public.thankyou', compact('catalogue', 'order'));
    }
}
