<?php

namespace App\Console\Commands;

use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShopifySimulateWebhook extends Command
{
    protected $signature = 'shopify:simulate
                            {topic=orders/paid : orders/paid | orders/cancelled | refunds/create | orders/create}
                            {--school= : School ulid. Defaults to the first approved school. Use "none" for no school}
                            {--order= : Shopify order id. Reuse it to replay or to send a follow-up (refund, cancel)}
                            {--items=2 : Number of line items}
                            {--refund-line= : For refunds/create: the line item id to refund fully}
                            {--url= : Base URL. Defaults to APP_URL}';

    protected $description = 'Send a signed, fake Shopify webhook to this app (for testing without a store)';

    public function handle(): int
    {
        $topic = $this->argument('topic');
        $secret = config('shopify.webhook_secret');

        if (! $secret) {
            $this->error('Set SHOPIFY_WEBHOOK_SECRET in .env first.');

            return self::FAILURE;
        }

        $orderId = $this->option('order') ? (int) $this->option('order') : random_int(5_000_000_000_000, 5_999_999_999_999);
        $payload = $this->payloadFor($topic, $orderId);

        if ($payload === null) {
            return self::FAILURE;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $url = rtrim($this->option('url') ?: config('app.url'), '/').'/api/v1/webhooks/shopify';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Shopify-Topic' => $topic,
            'X-Shopify-Shop-Domain' => config('shopify.store_domain') ?: 'dummy.myshopify.com',
            'X-Shopify-Event-Id' => (string) \Illuminate\Support\Str::uuid(),
            'X-Shopify-Hmac-Sha256' => FakeShopifyPayload::sign($body, $secret),
        ])->withBody($body, 'application/json')->post($url);

        $this->line("POST {$url}");
        $this->line("Topic {$topic}, order {$orderId}");
        $this->line("HTTP {$response->status()} {$response->body()}");

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed>|null */
    private function payloadFor(string $topic, int $orderId): ?array
    {
        if ($topic === 'refunds/create') {
            $line = $this->option('refund-line');
            if (! $line) {
                $this->error('refunds/create needs --refund-line=<line item id> (line item ids are shown when you run orders/paid, or see shopify_order_items).');

                return null;
            }

            $item = \App\Models\ShopifyOrderItem::where('shopify_line_item_id', $line)->first();
            $subtotal = number_format(($item?->net_eligible_minor ?? 0) / 100, 2, '.', '');

            return FakeShopifyPayload::refund($orderId, random_int(100_000, 999_999), [
                ['line_item_id' => (int) $line, 'subtotal' => $subtotal],
            ]);
        }

        $school = $this->option('school');
        if ($school === null) {
            $school = School::approved()->orderBy('id')->value('ulid');
        } elseif ($school === 'none') {
            $school = null;
        }

        $items = [];
        for ($i = 1; $i <= (int) $this->option('items'); $i++) {
            $items[] = ['title' => "Demo Item {$i}", 'price' => number_format(25 * $i, 2, '.', ''), 'quantity' => 1];
        }

        return FakeShopifyPayload::order([
            'id' => $orderId,
            'school' => $school,
            'items' => $items,
            'financial_status' => $topic === 'orders/create' ? 'pending' : 'paid',
            'cancelled_at' => $topic === 'orders/cancelled' ? now()->toIso8601String() : null,
        ]);
    }
}
