<?php

namespace App\Integrations\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Shopify Admin GraphQL API. Add methods here as the integration grows.
 */
class ShopifyClient
{
    private const TOKEN_CACHE_KEY = 'shopify.admin-token';

    /**
     * A static SHOPIFY_ADMIN_TOKEN wins. Otherwise exchange the app's client id and secret
     * for a short-lived token (client credentials grant) and cache it until shortly before it expires.
     */
    public function accessToken(): string
    {
        if ($static = config('shopify.admin_token')) {
            return $static;
        }

        $domain = config('shopify.store_domain');
        $id = config('shopify.client_id');
        $secret = config('shopify.client_secret');

        if (! $domain || ! $id || ! $secret) {
            throw new RuntimeException('Set SHOPIFY_STORE_DOMAIN plus either SHOPIFY_ADMIN_TOKEN or SHOPIFY_CLIENT_ID and SHOPIFY_CLIENT_SECRET.');
        }

        if ($cached = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(20)
            ->post("https://{$domain}/admin/oauth/access_token", [
                'grant_type' => 'client_credentials',
                'client_id' => $id,
                'client_secret' => $secret,
            ])->throw();

        $token = $response->json('access_token')
            ?? throw new RuntimeException('Shopify did not return an access token.');

        $ttl = max(60, (int) $response->json('expires_in', 86399) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function http(): PendingRequest
    {
        $domain = config('shopify.store_domain');

        if (! $domain) {
            throw new RuntimeException('Set SHOPIFY_STORE_DOMAIN first.');
        }

        return Http::baseUrl("https://{$domain}/admin/api/".config('shopify.api_version'))
            ->withHeaders(['X-Shopify-Access-Token' => $this->accessToken()])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = []): array
    {
        $response = $this->http()->post('/graphql.json', ['query' => $query, 'variables' => (object) $variables])->throw();

        $json = $response->json();

        if (! empty($json['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($json['errors']));
        }

        return $json['data'] ?? [];
    }

    /**
     * Subscribes a topic (e.g. "orders/paid") to an HTTPS callback URL.
     *
     * @return array<string, mixed>
     */
    public function subscribeWebhook(string $topic, string $callbackUrl): array
    {
        $enum = strtoupper(str_replace('/', '_', $topic)); // orders/paid -> ORDERS_PAID

        $data = $this->graphql(<<<'GQL'
            mutation Subscribe($topic: WebhookSubscriptionTopic!, $input: WebhookSubscriptionInput!) {
              webhookSubscriptionCreate(topic: $topic, webhookSubscription: $input) {
                webhookSubscription { id }
                userErrors { field message }
              }
            }
            GQL, [
            'topic' => $enum,
            'input' => ['callbackUrl' => $callbackUrl, 'format' => 'JSON'],
        ]);

        return $data['webhookSubscriptionCreate'] ?? [];
    }

    /**
     * @return list<array{id: string, topic: string, callbackUrl: string|null}>
     */
    public function listWebhooks(): array
    {
        $data = $this->graphql(<<<'GQL'
            { webhookSubscriptions(first: 100) { nodes { id topic endpoint { __typename ... on WebhookHttpEndpoint { callbackUrl } } } } }
            GQL);

        return array_map(fn ($n) => [
            'id' => $n['id'],
            'topic' => $n['topic'],
            'callbackUrl' => $n['endpoint']['callbackUrl'] ?? null,
        ], $data['webhookSubscriptions']['nodes'] ?? []);
    }
}
