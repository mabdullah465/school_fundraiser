<?php

namespace App\Models;

use App\Enums\IngestionStatus;
use App\Enums\OrderDisplayStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopifyOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shopify_order_id' => 'integer',
            'shipping_address' => 'array',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'shipping_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'display_status' => OrderDisplayStatus::class,
            'ingestion_status' => IngestionStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopifyOrderItem::class, 'order_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'selected_school_id');
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(DonationAllocation::class, 'shopify_order_id');
    }
}
