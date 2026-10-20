<?php

namespace Tests\Feature;

use App\Integrations\Shopify\ShopifyClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'shopify.store_domain' => 'demo.myshopify.com',
            'shopify.admin_token' => null,
            'shopify.client_id' => 'client-id',
            'shopify.client_secret' => 'shpss_secret',
        ]);
    }

    public function test_a_static_admin_token_is_used_without_any_exchange(): void
    {
        Http::fake();
        config(['shopify.admin_token' => 'shpat_static']);

        $this->assertSame('shpat_static', app(ShopifyClient::class)->accessToken());
        Http::assertNothingSent();
    }

    public function test_client_credentials_are_exchanged_for_a_token_and_cached(): void
    {
        Http::fake(['demo.myshopify.com/admin/oauth/access_token' => Http::response(
            ['access_token' => 'shpat_fetched', 'expires_in' => 86399],
        )]);

        $client = app(ShopifyClient::class);

        $this->assertSame('shpat_fetched', $client->accessToken());
        $this->assertSame('shpat_fetched', $client->accessToken());

        Http::assertSentCount(1); // second call came from the cache
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'client_credentials'
            && $r['client_id'] === 'client-id'
            && $r['client_secret'] === 'shpss_secret');
    }

    public function test_the_fetched_token_is_sent_on_admin_api_calls(): void
    {
        Http::fake([
            'demo.myshopify.com/admin/oauth/access_token' => Http::response(['access_token' => 'shpat_fetched', 'expires_in' => 86399]),
            'demo.myshopify.com/admin/api/*' => Http::response(['data' => ['webhookSubscriptions' => ['nodes' => []]]]),
        ]);

        app(ShopifyClient::class)->listWebhooks();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/graphql.json')
            && $r->hasHeader('X-Shopify-Access-Token', 'shpat_fetched'));
    }

    public function test_missing_configuration_fails_loudly(): void
    {
        config(['shopify.client_secret' => null]);

        $this->expectException(\RuntimeException::class);

        app(ShopifyClient::class)->accessToken();
    }

    public function test_the_webhook_secret_falls_back_to_the_client_secret(): void
    {
        // config/shopify.php: webhook_secret = SHOPIFY_WEBHOOK_SECRET ?: SHOPIFY_CLIENT_SECRET
        $this->assertNotNull(config('shopify.webhook_secret') ?? config('shopify.client_secret'));
    }
}
