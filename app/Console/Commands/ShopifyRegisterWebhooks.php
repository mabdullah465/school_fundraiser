<?php

namespace App\Console\Commands;

use App\Integrations\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class ShopifyRegisterWebhooks extends Command
{
    protected $signature = 'shopify:register-webhooks
                            {--url= : Public base URL of this app (e.g. your tunnel URL). Defaults to APP_URL}';

    protected $description = 'Subscribe the configured Shopify webhook topics to this app';

    public function handle(ShopifyClient $shopify): int
    {
        $base = rtrim($this->option('url') ?: config('app.url'), '/');

        if (! str_starts_with($base, 'https://')) {
            $this->error("Shopify only delivers webhooks to HTTPS URLs. Got: {$base}");
            $this->line('Use a tunnel (ngrok / cloudflared) and pass --url=https://your-tunnel.example');

            return self::FAILURE;
        }

        $callback = $base.'/api/v1/webhooks/shopify';
        $existing = collect($shopify->listWebhooks())->pluck('callbackUrl', 'topic');
        $failed = false;

        $topics = array_diff(array_keys(config('shopify.handlers')), config('shopify.unregistered', []));

        foreach ($topics as $topic) {
            $enum = strtoupper(str_replace('/', '_', $topic));

            if ($existing->get($enum) === $callback) {
                $this->line("  = {$topic} (already subscribed)");

                continue;
            }

            $result = $shopify->subscribeWebhook($topic, $callback);
            $errors = $result['userErrors'] ?? [];

            if ($errors !== []) {
                $failed = true;
                $this->error("  x {$topic}: ".json_encode($errors));
            } else {
                $this->info("  + {$topic}");
            }
        }

        $this->newLine();
        $this->line("Callback: {$callback}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
