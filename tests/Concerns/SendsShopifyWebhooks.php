<?php

namespace Tests\Concerns;

use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SendsShopifyWebhooks
{
    protected string $webhookSecret = 'test-webhook-secret';

    protected function useWebhookSecret(): void
    {
        config(['shopify.webhook_secret' => $this->webhookSecret]);
    }

    /**
     * Sends the exact bytes it signed, like Shopify does.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(string $topic, array $payload, ?string $eventId = null, ?string $signWith = null): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $this->call('POST', '/api/v1/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => $topic,
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'dummy.myshopify.com',
            'HTTP_X_SHOPIFY_EVENT_ID' => $eventId ?? (string) Str::uuid(),
            'HTTP_X_SHOPIFY_HMAC_SHA256' => FakeShopifyPayload::sign($body, $signWith ?? $this->webhookSecret),
        ], $body);
    }
}
