<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessShopifyWebhook;
use App\Models\WebhookInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Shopify webhooks. HMAC is already verified by middleware.
 * Does the minimum (store, dedupe, enqueue) and answers fast; work happens on the queue.
 */
class ShopifyWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $topic = (string) $request->header('X-Shopify-Topic', '');

        if ($topic === '' || json_decode($body, true) === null) {
            return response()->json(['message' => 'Malformed webhook.'], 400);
        }

        $eventId = $request->header('X-Shopify-Event-Id')
            ?? $request->header('X-Shopify-Webhook-Id')
            ?? hash('sha256', $topic.$body);

        $inbox = WebhookInbox::firstOrCreate(
            ['provider' => 'shopify', 'event_id' => $eventId],
            [
                'topic' => $topic,
                'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
                'payload_hash' => hash('sha256', $body),
                'payload' => $body,
            ],
        );

        if (! $inbox->wasRecentlyCreated) {
            // A redelivery of something we stored but never finished (job lost or exhausted): try again.
            if (in_array($inbox->status, ['received', 'failed'], true)) {
                ProcessShopifyWebhook::dispatch($inbox->id);

                return response()->json(['status' => 'retrying']);
            }

            return response()->json(['status' => 'duplicate']);
        }

        ProcessShopifyWebhook::dispatch($inbox->id);

        return response()->json(['status' => 'received']);
    }
}
