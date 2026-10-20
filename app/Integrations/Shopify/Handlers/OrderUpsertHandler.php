<?php

namespace App\Integrations\Shopify\Handlers;

use App\Domain\Orders\Actions\IngestShopifyOrder;
use App\Integrations\Shopify\Support\NormalizedOrder;
use App\Jobs\FetchOrderPaymentFees;
use Throwable;

/**
 * orders/create, orders/updated, orders/paid (and fulfilled topics). All go through the same upsert,
 * so out-of-order delivery (updated before paid) converges to the same result.
 */
class OrderUpsertHandler implements ShopifyWebhookHandler
{
    public function __construct(private readonly IngestShopifyOrder $ingest) {}

    public function handle(string $topic, array $payload): void
    {
        $normalized = NormalizedOrder::fromPayload($payload);
        $order = $this->ingest->handle($normalized);

        if ($normalized->isPaid() && config('shopify.fetch_fees') && $order->fee_status === null) {
            $this->queueFeeLookup($order->id);
        }
    }

    /**
     * The fee is nice-to-have. A failure here must never fail the order webhook,
     * or Shopify would redeliver an order that was already recorded correctly.
     */
    private function queueFeeLookup(int $orderId): void
    {
        try {
            FetchOrderPaymentFees::dispatch($orderId);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
