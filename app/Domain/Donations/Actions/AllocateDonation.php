<?php

namespace App\Domain\Donations\Actions;

use App\Domain\Donations\DonationCalculator;
use App\Domain\Donations\Exceptions\DonationNotAllocatable;
use App\Enums\AllocationStatus;
use App\Enums\IngestionStatus;
use App\Enums\LedgerEntryType;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\FundraisingRateVersion;
use App\Models\School;
use App\Models\ShopifyOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates the single donation allocation for a paid order, plus its ledger credit.
 * Idempotent: a second call for the same order returns the existing allocation.
 */
class AllocateDonation
{
    public function __construct(private readonly DonationCalculator $calculator) {}

    /**
     * @throws DonationNotAllocatable
     */
    public function handle(ShopifyOrder $order, ?School $school): DonationAllocation
    {
        if ($existing = $order->allocation()->first()) {
            return $existing;
        }

        if ($school === null) {
            throw DonationNotAllocatable::missingSchool();
        }

        if (! $school->isApproved()) {
            throw DonationNotAllocatable::schoolNotApproved();
        }

        // The rate is read here, on the server, at payment time. It is never taken from the client.
        $rate = FundraisingRateVersion::current() ?? throw DonationNotAllocatable::noActiveRate();

        $base = (int) $order->items()->where('is_eligible', true)->sum('net_eligible_minor');
        $amount = $this->calculator->amount($base, $rate->school_bps);
        $paidAt = $order->paid_at ?? now();

        try {
            return DB::transaction(function () use ($order, $school, $rate, $base, $amount, $paidAt) {
                $allocation = DonationAllocation::create([
                    'public_ref' => $this->newPublicRef(),
                    'shopify_order_id' => $order->id,
                    'school_id' => $school->id,
                    'rate_version_id' => $rate->id,
                    'rate_bps_snapshot' => $rate->school_bps,
                    'base_minor' => $base,
                    'original_amount_minor' => $amount,
                    'amount_minor' => $amount,
                    'currency' => $order->currency,
                    'status' => AllocationStatus::Pending,
                    'payable_at' => $paidAt->copy()->addDays(config('donations.hold_days')),
                ]);

                DonationLedgerEntry::create([
                    'allocation_id' => $allocation->id,
                    'school_id' => $school->id,
                    'type' => LedgerEntryType::DonationCredit,
                    'amount_minor' => $amount,
                    'currency' => $order->currency,
                    'reference_type' => 'shopify_order',
                    'reference_id' => (string) $order->shopify_order_id,
                ]);

                $order->update([
                    'ingestion_status' => IngestionStatus::Ok,
                    'quarantine_reason' => null,
                ]);

                return $allocation;
            });
        } catch (UniqueConstraintViolationException) {
            // Two workers raced on the same order. The unique index on shopify_order_id decided.
            return $order->allocation()->firstOrFail();
        }
    }

    private function newPublicRef(): string
    {
        do {
            $ref = 'TXN-'.random_int(100000, 999999);
        } while (DonationAllocation::where('public_ref', $ref)->exists());

        return $ref;
    }
}
