<?php

namespace App\Models;

use App\Enums\RateStatus;
use Database\Factories\FundraisingRateVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundraisingRateVersion extends Model
{
    /** @use HasFactory<FundraisingRateVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'version', 'school_bps', 'calculation_basis', 'included_in_price', 'status',
        'active_slot', 'effective_at', 'closed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'school_bps' => 'integer',
            'included_in_price' => 'boolean',
            'status' => RateStatus::class,
            'effective_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RateStatus::Active->value);
    }

    public static function current(): ?self
    {
        return static::active()->latest('effective_at')->first();
    }

    public function getBusinessBpsAttribute(): int
    {
        return 10000 - $this->school_bps;
    }
}
