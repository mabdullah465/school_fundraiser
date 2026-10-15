<?php

namespace App\Domain\Donations\Actions;

use App\Domain\Donations\DonationCalculator;
use App\Enums\AllocationStatus;
use App\Enums\LedgerEntryType;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\DB;

/**
 * Reduces an order's donation when part of the order is refunded.
 * The original credit is never edited: the difference is written as a new negative ledger entry.
 */
class AdjustDonationForRefund
{
    public function __construct(private readonly DonationCalculator $calculator) {}

    /**
     * @param  int  $refundedEligibleMinor  Eligible merchandise value refunded (before tax), in minor units.
     * @param  string  $refundId  Shopify refund id. Makes the call idempotent.
     */
    public function handle(ShopifyOrder $order, int $refundedEligibleMinor, string $refundId): ?DonationAllocation
    {
        return DB::transaction(function () use ($order, $refundedEligibleMinor, $refundId) {
            $allocation = DonationAllocation::where('shopify_order_id', $order->id)->lockForUpdate()->first();

            if ($allocation === null) {
                return null; // nothing was credited, so nothing to take back
            }

            $alreadyApplied = DonationLedgerEntry::where('reference_type', 'shopify_refund')
                ->where('reference_id', $refundId)
                ->exists();

            if ($alreadyApplied) {
                return $allocation;
            }

            $newBase = max(0, $allocation->base_minor - $refundedEligibleMinor);
            $newAmount = $this->calculator->amount($newBase, $allocation->rate_bps_snapshot);
            $delta = $newAmount - $allocation->amount_minor; // zero or negative

            // A zero-amount entry is still written: it records that this refund was seen, so a replay stays a no-op.
            DonationLedgerEntry::create([
                'allocation_id' => $allocation->id,
                'school_id' => $allocation->school_id,
                'type' => LedgerEntryType::RefundAdjustment,
                'amount_minor' => $delta,
                'currency' => $allocation->currency,
                'reference_type' => 'shopify_refund',
                'reference_id' => $refundId,
            ]);

            $allocation->update([
                'base_minor' => $newBase,
                'amount_minor' => $newAmount,
                'status' => $newAmount === 0 ? AllocationStatus::Refunded : $allocation->status,
            ]);

            return $allocation->refresh();
        });
    }
}
