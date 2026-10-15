<?php

namespace App\Integrations\Shopify\Handlers;

use App\Domain\Donations\Actions\CancelDonation;
use App\Domain\Orders\Actions\IngestShopifyOrder;
use App\Integrations\Shopify\Support\NormalizedOrder;

class OrderCancelledHandler implements ShopifyWebhookHandler
{
    public function __construct(
        private readonly IngestShopifyOrder $ingest,
        private readonly CancelDonation $cancel,
    ) {}

    public function handle(string $topic, array $payload): void
    {
        // Ingest first so the order exists and shows as cancelled, then reverse the donation.
        $order = $this->ingest->handle(NormalizedOrder::fromPayload($payload));

        $this->cancel->handle($order);
    }
}
