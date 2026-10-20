<?php

namespace App\Jobs;

use App\Integrations\Shopify\ShopifyClient;
use App\Models\ShopifyOrder;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the Shopify Payments processing fee for an order and stores it with the fee breakdown.
 *
 * The fee is not part of any order webhook. It lives on the order's transactions in the Admin API,
 * and only for Shopify Payments. It can show up a short while after the payment, so an empty
 * result is retried a few times before the order is marked "unavailable".
 */
class FetchOrderPaymentFees implements ShouldQueue
{
    use Queueable;

    private const RETRY_DELAY_MINUTES = [1, 5, 30];

    public int $tries = 3;

    public function __construct(public readonly int $orderId, public readonly int $attempt = 1)
    {
        $this->onQueue('default');
    }

    public function handle(ShopifyClient $shopify): void
    {
        $order = ShopifyOrder::find($this->orderId);

        if ($order === null || $order->fee_status === 'fetched') {
            return;
        }

        if ($order->payment_gateway !== 'shopify_payments') {
            // Third-party gateways (Stripe, PayPal, bogus) do not expose fees through Shopify.
            $order->update(['fee_status' => 'unavailable', 'fees_fetched_at' => now()]);

            return;
        }

        $fees = $this->collectFees($shopify->orderTransactions($order->shopify_order_id), $order->currency);

        if ($fees === []) {
            $this->retryOrGiveUp($order);

            return;
        }

        $order->update([
            'processing_fee_minor' => array_sum(array_column($fees, 'amount_minor')),
            'fee_details' => $fees,
            'fee_status' => 'fetched',
            'fees_fetched_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<array{type: string, rate: float|null, flat_fee_minor: int, amount_minor: int}>
     */
    private function collectFees(array $transactions, string $currency): array
    {
        $fees = [];

        foreach ($transactions as $transaction) {
            // Only money that actually moved in: a successful sale or capture.
            if (($transaction['status'] ?? null) !== 'SUCCESS' || ! in_array($transaction['kind'] ?? null, ['SALE', 'CAPTURE'], true)) {
                continue;
            }

            foreach ($transaction['fees'] ?? [] as $fee) {
                $fees[] = [
                    'type' => (string) ($fee['type'] ?? 'fee'),
                    'rate' => isset($fee['rate']) ? (float) $fee['rate'] : null,
                    'flat_fee_minor' => Money::toMinor($fee['flatFee']['amount'] ?? null, $currency),
                    'amount_minor' => Money::toMinor($fee['amount']['amount'] ?? null, $currency),
                ];
            }
        }

        return $fees;
    }

    private function retryOrGiveUp(ShopifyOrder $order): void
    {
        $delay = self::RETRY_DELAY_MINUTES[$this->attempt - 1] ?? null;

        if ($delay === null) {
            $order->update(['fee_status' => 'unavailable', 'fees_fetched_at' => now()]);

            return;
        }

        $order->update(['fee_status' => 'pending']);

        static::dispatch($order->id, $this->attempt + 1)->delay(now()->addMinutes($delay));
    }
}
