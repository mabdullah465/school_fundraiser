<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'net_eligible_minor' => 'integer',
            'is_eligible' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopifyOrder::class, 'order_id');
    }
}
