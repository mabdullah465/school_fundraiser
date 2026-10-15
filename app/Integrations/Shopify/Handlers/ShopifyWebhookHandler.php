<?php

namespace App\Integrations\Shopify\Handlers;

interface ShopifyWebhookHandler
{
    /**
     * Must be idempotent. Shopify retries, duplicates and reorders deliveries.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $topic, array $payload): void;
}
