<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Donations\Actions\AllocateDonation;
use App\Domain\Donations\Exceptions\DonationNotAllocatable;
use App\Enums\IngestionStatus;
use App\Integrations\Shopify\Support\NormalizedOrder;
use App\Integrations\Shopify\Support\OrderStatusMapper;
use App\Models\School;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Upserts a Shopify order and, once it is paid, allocates the donation exactly once.
 * Safe to run any number of times, in any order, for the same order.
 */
class IngestShopifyOrder
{
    public function __construct(private readonly AllocateDonation $allocate) {}

    public function handle(NormalizedOrder $normalized): ShopifyOrder
    {
        return DB::transaction(function () use ($normalized) {
            $order = ShopifyOrder::where('shopify_order_id', $normalized->shopifyOrderId)
                ->lockForUpdate()
                ->first() ?? new ShopifyOrder(['shopify_order_id' => $normalized->shopifyOrderId]);

            $school = $this->resolveSchool($normalized->schoolAttribute);

            $order->fill([
                'order_number' => $normalized->orderNumber,
                'customer_name' => $normalized->customerName,
                'customer_email' => $normalized->customerEmail,
                'shipping_address' => $normalized->shippingAddress,
                'currency' => $normalized->currency,
                'subtotal_minor' => $normalized->subtotalMinor,
                'discount_minor' => $normalized->discountMinor,
                'shipping_minor' => $normalized->shippingMinor,
                'tax_minor' => $normalized->taxMinor,
                'total_minor' => $normalized->totalMinor,
                'financial_status' => $normalized->financialStatus,
                'fulfillment_status' => $normalized->fulfillmentStatus,
                'display_status' => OrderStatusMapper::map(
                    $normalized->financialStatus,
                    $normalized->fulfillmentStatus,
                    $normalized->cancelledAt !== null,
                    $order->display_status,
                ),
                'selected_school_id' => $school?->id,
                'selected_school_raw' => $normalized->schoolAttribute,
                'placed_at' => $normalized->placedAt,
                'cancelled_at' => $normalized->cancelledAt,
            ]);

            if ($normalized->isPaid() && $order->paid_at === null) {
                $order->paid_at = now();
            }

            $order->save();

            $this->syncItems($order, $normalized);

            // A new order is not proof of payment. Only a paid order creates a donation.
            if ($normalized->isPaid() && $normalized->cancelledAt === null) {
                $this->allocateOrQuarantine($order, $school);
            }

            return $order->refresh();
        });
    }

    private function resolveSchool(?string $ulid): ?School
    {
        return $ulid === null ? null : School::where('ulid', $ulid)->first();
    }

    private function syncItems(ShopifyOrder $order, NormalizedOrder $normalized): void
    {
        $seen = [];

        foreach ($normalized->items as $item) {
            $seen[] = $item->lineItemId;

            $order->items()->updateOrCreate(
                ['shopify_line_item_id' => $item->lineItemId],
                [
                    'product_id' => $item->productId,
                    'variant_id' => $item->variantId,
                    'title' => $item->title,
                    'variant_title' => $item->variantTitle,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_minor' => $item->unitPriceMinor,
                    'discount_minor' => $item->discountMinor,
                    'net_eligible_minor' => $item->isEligible ? $item->netMinor() : 0,
                    'is_eligible' => $item->isEligible,
                ],
            );
        }

        $order->items()->whereNotIn('shopify_line_item_id', $seen)->delete();
    }

    private function allocateOrQuarantine(ShopifyOrder $order, ?School $school): void
    {
        try {
            $this->allocate->handle($order, $school);
        } catch (DonationNotAllocatable $e) {
            $order->update([
                'ingestion_status' => IngestionStatus::Quarantined,
                'quarantine_reason' => $e->reason,
            ]);

            // Never guess a school. An admin resolves this order.
            Log::warning('Paid Shopify order quarantined', [
                'shopify_order_id' => $order->shopify_order_id,
                'reason' => $e->reason,
                'school_attribute' => $order->selected_school_raw,
            ]);
        }
    }
}
