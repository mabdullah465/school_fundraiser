<?php

namespace App\Domain\Donations\Actions;

use App\Enums\AllocationStatus;
use App\Enums\LedgerEntryType;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\DB;

/**
 * An order was cancelled: take the whole remaining donation back with a reversing entry.
 */
class CancelDonation
{
    public function handle(ShopifyOrder $order): ?DonationAllocation
    {
        return DB::transaction(function () use ($order) {
            $allocation = DonationAllocation::where('shopify_order_id', $order->id)->lockForUpdate()->first();

            if ($allocation === null || $allocation->status === AllocationStatus::Cancelled) {
                return $allocation;
            }

            if ($allocation->amount_minor !== 0) {
                DonationLedgerEntry::create([
                    'allocation_id' => $allocation->id,
                    'school_id' => $allocation->school_id,
                    'type' => LedgerEntryType::CancellationReversal,
                    'amount_minor' => -$allocation->amount_minor,
                    'currency' => $allocation->currency,
                    'reference_type' => 'shopify_order',
                    'reference_id' => (string) $order->shopify_order_id,
                ]);
            }

            $allocation->update([
                'amount_minor' => 0,
                'base_minor' => 0,
                'status' => AllocationStatus::Cancelled,
            ]);

            return $allocation->refresh();
        });
    }
}
