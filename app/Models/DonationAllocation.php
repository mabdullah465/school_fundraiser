<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonationAllocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_bps_snapshot' => 'integer',
            'base_minor' => 'integer',
            'original_amount_minor' => 'integer',
            'amount_minor' => 'integer',
            'status' => AllocationStatus::class,
            'payable_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopifyOrder::class, 'shopify_order_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function rateVersion(): BelongsTo
    {
        return $this->belongsTo(FundraisingRateVersion::class, 'rate_version_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(DonationLedgerEntry::class, 'allocation_id');
    }
}
