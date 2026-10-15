<?php

namespace App\Integrations\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Shopify Admin GraphQL API. Add methods here as the integration grows.
 */
class ShopifyClient
{
    private function http(): PendingRequest
    {
        $domain = config('shopify.store_domain');
        $token = config('shopify.admin_token');

        if (! $domain || ! $token) {
            throw new RuntimeException('Set SHOPIFY_STORE_DOMAIN and SHOPIFY_ADMIN_TOKEN first.');
        }

        return Http::baseUrl("https://{$domain}/admin/api/".config('shopify.api_version'))
            ->withHeaders(['X-Shopify-Access-Token' => $token])
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
        $response = $this->http()->post('/graphql.json', ['query' => $query, 'variables' => $variables])->throw();

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
