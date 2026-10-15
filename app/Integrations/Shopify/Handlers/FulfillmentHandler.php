<?php

namespace App\Integrations\Shopify\Handlers;

use App\Enums\OrderDisplayStatus;
use App\Models\ShopifyOrder;

/**
 * fulfillments/create|update and fulfillment_events/create. Moves the admin-facing status
 * along Shipped -> Received. It never touches money.
 */
class FulfillmentHandler implements ShopifyWebhookHandler
{
    public function handle(string $topic, array $payload): void
    {
        $order = ShopifyOrder::where('shopify_order_id', (int) ($payload['order_id'] ?? 0))->first();

        if ($order === null || $order->display_status === OrderDisplayStatus::Cancelled) {
            return;
        }

        $delivered = ($payload['shipment_status'] ?? null) === 'delivered'
            || ($payload['status'] ?? null) === 'delivered';

        if ($delivered) {
            $order->update(['display_status' => OrderDisplayStatus::Received]);

            return;
        }

        if (($payload['status'] ?? null) === 'success' && $order->display_status !== OrderDisplayStatus::Received) {
            $order->update(['display_status' => OrderDisplayStatus::Shipped]);
        }
    }
}
