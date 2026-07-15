<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderStatusChanged;

/**
 * Pushes a customer portal PWA notification when an order's status
 * auto-transitions. Called explicitly at each of the ~7 status-mutation call
 * sites across PaymentController, FabricBatchController, DispatchController,
 * and OrderReductionController — not via a model observer, since
 * FabricBatchController's bulk query-builder update() bypasses Eloquent
 * events entirely, and the revert paths (confirmed -> received) deliberately
 * get no notification, which an observer would need extra filtering for
 * anyway. See CasualiteOS project notes for the full reasoning.
 *
 * No-ops silently if the customer has no push subscriptions — the package's
 * WebPushChannel already handles an empty subscription collection safely.
 */
class OrderStatusNotificationService
{
    public function notify(Order $order, string $newStatus): void
    {
        if (! $order->relationLoaded('customer')) {
            $order->load('customer');
        }

        $order->customer->notify(new OrderStatusChanged($order, $newStatus));
    }
}
