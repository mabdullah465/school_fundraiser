<?php

namespace App\Jobs;

use App\Integrations\Shopify\Handlers\ShopifyWebhookHandler;
use App\Models\WebhookInbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessShopifyWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function __construct(public readonly int $inboxId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $inbox = WebhookInbox::findOrFail($this->inboxId);

        if ($inbox->status === 'processed') {
            return;
        }

        $handlerClass = config("shopify.handlers.{$inbox->topic}");

        if ($handlerClass === null) {
            $inbox->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        $inbox->increment('attempts');

        try {
            /** @var ShopifyWebhookHandler $handler */
            $handler = app($handlerClass);
            $handler->handle($inbox->topic, $inbox->decodedPayload());
        } catch (Throwable $e) {
            $inbox->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e; // let the queue retry with backoff
        }

        $inbox->update(['status' => 'processed', 'error' => null, 'processed_at' => now()]);
    }
}
