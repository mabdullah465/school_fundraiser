<?php

namespace Tests\Feature;

use App\Models\FundraisingRateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DonationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_the_active_rate(): void
    {
        FundraisingRateVersion::factory()->closed()->schoolBps(5000)->create(['version' => 'donation-v1']);
        FundraisingRateVersion::factory()->schoolBps(3000)->create(['version' => 'donation-v2']);

        $this->getJson('/api/v1/storefront/donation-settings')
            ->assertOk()
            ->assertExactJson([
                'donation_enabled' => true,
                'percentage_basis_points' => 3000,
                'calculation_basis' => 'discounted_eligible_merchandise_subtotal_excluding_tax_shipping',
                'included_in_price' => true,
                'settings_version' => 'donation-v2',
                'disclaimer' => '30% of your eligible merchandise purchase supports your selected school at no additional cost.',
            ]);
    }

    public function test_fractional_percentages_read_naturally(): void
    {
        FundraisingRateVersion::factory()->schoolBps(1250)->create();

        $this->getJson('/api/v1/storefront/donation-settings')
            ->assertJsonPath('disclaimer', '12.5% of your eligible merchandise purchase supports your selected school at no additional cost.');
    }

    public function test_reports_disabled_when_no_rate_is_configured(): void
    {
        $this->getJson('/api/v1/storefront/donation-settings')
            ->assertOk()
            ->assertJsonPath('donation_enabled', false)
            ->assertJsonPath('settings_version', null)
            ->assertJsonPath('disclaimer', null);
    }

    public function test_only_one_rate_can_be_active(): void
    {
        FundraisingRateVersion::factory()->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        FundraisingRateVersion::factory()->create();
    }
}
