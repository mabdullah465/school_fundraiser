<?php

namespace Database\Factories;

use App\Enums\RateStatus;
use App\Models\FundraisingRateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundraisingRateVersion>
 */
class FundraisingRateVersionFactory extends Factory
{
    protected $model = FundraisingRateVersion::class;

    public function definition(): array
    {
        return [
            'version' => 'donation-v'.fake()->unique()->numberBetween(1, 100000),
            'school_bps' => 3000,
            'calculation_basis' => config('donations.calculation_basis'),
            'included_in_price' => true,
            'status' => RateStatus::Active,
            'active_slot' => 1,
            'effective_at' => now()->subDay(),
        ];
    }

    public function schoolBps(int $bps): static
    {
        return $this->state(fn () => ['school_bps' => $bps]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => RateStatus::Closed,
            'active_slot' => null,
            'closed_at' => now(),
        ]);
    }
}
