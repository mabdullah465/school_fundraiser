<?php

namespace App\Models;

use App\Enums\SchoolStatus;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'user_id', 'name', 'email', 'phone', 'address', 'city', 'state', 'country',
        'avatar_path', 'status', 'rejection_reason', 'approved_at', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'approved_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->ulid ??= (string) Str::ulid();
        });
    }

    /** Only approved schools are ever visible to the storefront. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SchoolStatus::Approved->value);
    }

    public function isApproved(): bool
    {
        return $this->status === SchoolStatus::Approved;
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DonationAllocation::class);
    }
}
