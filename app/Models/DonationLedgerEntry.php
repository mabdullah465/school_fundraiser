<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only. History is never rewritten: a correction is a new, signed entry.
 */
class DonationLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(DonationAllocation::class, 'allocation_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
