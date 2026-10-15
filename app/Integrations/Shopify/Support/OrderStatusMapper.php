<?php

namespace App\Integrations\Shopify\Support;

use App\Enums\OrderDisplayStatus;

/**
 * Maps Shopify's financial/fulfillment state to the admin-facing
 * Placed / In Process / Shipped / Received timeline.
 */
final class OrderStatusMapper
{
    public static function map(
        ?string $financialStatus,
        ?string $fulfillmentStatus,
        bool $cancelled,
        ?OrderDisplayStatus $current = null,
    ): OrderDisplayStatus {
        if ($cancelled) {
            return OrderDisplayStatus::Cancelled;
        }

        if ($financialStatus === 'refunded') {
            return OrderDisplayStatus::Refunded;
        }

        if (! in_array($financialStatus, ['paid', 'partially_refunded', 'partially_paid'], true)) {
            return OrderDisplayStatus::Pending;
        }

        return match ($fulfillmentStatus) {
            'fulfilled' => $current === OrderDisplayStatus::Received
                ? OrderDisplayStatus::Received
                : OrderDisplayStatus::Shipped,
            'partial', 'in_progress' => OrderDisplayStatus::InProcess,
            default => OrderDisplayStatus::Placed,
        };
    }
}
