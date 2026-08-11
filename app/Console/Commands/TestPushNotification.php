<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Notifications\OrderStatusChanged;
use App\Services\OrderStatusNotificationService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'notify:test-push
                            {email : Customer email address to send the test push to}
                            {status=confirmed : One of: confirmed, stitching, partially_dispatched, dispatched, cancelled}
                            {--order= : Order number to reference (default: the customer\'s most recent order)}';

    protected $description = 'Send a real push notification to a customer\'s subscribed devices for manual testing. Does not change the order\'s actual status in the database.';

    public function handle(OrderStatusNotificationService $notificationService): int
    {
        $status = $this->argument('status');

        if (! in_array($status, OrderStatusChanged::statuses(), true)) {
            $this->error("Invalid status \"{$status}\". Valid options: " . implode(', ', OrderStatusChanged::statuses()));
            return self::FAILURE;
        }

        $customer = Customer::where('email', $this->argument('email'))->first();

        if (! $customer) {
            $this->error("No customer found with email \"{$this->argument('email')}\".");
            return self::FAILURE;
        }

        $ordersQuery = $customer->orders()->latest('submitted_at');

        if ($orderNumber = $this->option('order')) {
            $ordersQuery->where('order_number', $orderNumber);
        }

        $order = $ordersQuery->first();

        if (! $order) {
            $this->error($this->option('order')
                ? "Order #{$this->option('order')} not found for {$customer->name}."
                : "{$customer->name} has no orders to attach a test notification to.");
            return self::FAILURE;
        }

        $subscriptionCount = $customer->pushSubscriptions()->count();
        $expoTokenCount    = $customer->expoPushTokens()->count();

        if ($subscriptionCount === 0 && $expoTokenCount === 0) {
            $this->warn("{$customer->name} has no web push subscriptions and no registered Expo push tokens — they haven't tapped \"Enable Order Updates\" on the portal, or opened the RN app with notifications enabled. The command will still run but nothing will actually arrive.");
        }

        $notificationService->notify($order, $status);

        $this->info("Queued \"{$status}\" push notification for {$customer->name} ({$customer->email}) referencing Order #{$order->order_number}.");
        $this->line("Active web push subscriptions: {$subscriptionCount}");
        $this->line("Registered Expo push tokens: {$expoTokenCount}");
        $this->line('Note: QUEUE_CONNECTION=database, so this sends on the next queue:work tick (runs every minute via the scheduler) — not instantly.');

        return self::SUCCESS;
    }
}
