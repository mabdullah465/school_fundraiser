<?php

namespace App\Integrations\Shopify\Handlers;

use App\Domain\Orders\Actions\IngestShopifyOrder;
use App\Integrations\Shopify\Support\NormalizedOrder;

/**
 * orders/create, orders/updated, orders/paid. All three go through the same upsert,
 * so out-of-order delivery (updated before paid) converges to the same result.
 */
class OrderUpsertHandler implements ShopifyWebhookHandler
{
    public function __construct(private readonly IngestShopifyOrder $ingest) {}

    public function handle(string $topic, array $payload): void
    {
        $this->ingest->handle(NormalizedOrder::fromPayload($payload));
    }
}
