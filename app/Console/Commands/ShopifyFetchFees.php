<?php

namespace App\Console\Commands;

use App\Jobs\FetchOrderPaymentFees;
use App\Models\ShopifyOrder;
use Illuminate\Console\Command;

class ShopifyFetchFees extends Command
{
    protected $signature = 'shopify:fetch-fees {--retry-unavailable : Also retry orders previously marked unavailable}';

    protected $description = 'Look up processing fees for paid orders that do not have them yet';

    public function handle(): int
    {
        $statuses = $this->option('retry-unavailable') ? ['unavailable', 'pending', 'failed'] : ['pending', 'failed'];

        $orders = ShopifyOrder::whereNotNull('paid_at')
            ->where(fn ($q) => $q->whereNull('fee_status')->orWhereIn('fee_status', $statuses))
            ->get();

        foreach ($orders as $order) {
            $order->update(['fee_status' => null]);
            FetchOrderPaymentFees::dispatch($order->id);
        }

        $this->info("Queued {$orders->count()} order(s).");

        return self::SUCCESS;
    }
}
