<?php

namespace Tests\Feature;

use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use App\Models\FundraisingRateVersion;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SendsShopifyWebhooks;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use SendsShopifyWebhooks;

    public function test_the_page_is_open_when_no_password_is_set(): void
    {
        config(['dashboard.password' => null]);

        $this->get('/')->assertOk();
    }

    public function test_it_asks_for_credentials_when_a_password_is_set(): void
    {
        config(['dashboard.password' => 's3cret', 'dashboard.user' => 'admin']);

        $this->get('/')->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->get('/', ['Authorization' => 'Basic '.base64_encode('admin:wrong')])->assertStatus(401);
        $this->get('/', ['Authorization' => 'Basic '.base64_encode('admin:s3cret')])->assertOk();
    }

    public function test_it_shows_deliveries_orders_and_donations(): void
    {
        config(['dashboard.password' => 's3cret', 'dashboard.user' => 'admin']);
        $this->useWebhookSecret();
        FundraisingRateVersion::factory()->schoolBps(3000)->create();
        $school = School::factory()->create(['name' => 'Lincoln High School']);

        $paid = FakeShopifyPayload::order(['number' => 1042, 'school' => $school->ulid, 'items' => [['price' => '100.00']]]);
        $this->sendWebhook('orders/paid', $paid)->assertOk();
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['number' => 1043, 'school' => null]))->assertOk();

        $this->get('/', ['Authorization' => 'Basic '.base64_encode('admin:s3cret')])
            ->assertOk()
            ->assertSee('orders/paid')
            ->assertSee('#1042')
            ->assertSee('Lincoln High School')
            ->assertSee('30.00 USD')               // the donation
            ->assertSee('missing_or_unknown_school') // the quarantined order
            ->assertSee('TXN-', false)
            ->assertDontSee('allison.vetrovs@example.test'); // customer email is masked
    }

    public function test_payload_content_is_escaped(): void
    {
        config(['dashboard.password' => 's3cret']);
        $this->useWebhookSecret();

        $payload = FakeShopifyPayload::order(['school' => null]);
        $payload['customer']['first_name'] = '<script>alert(1)</script>';
        $payload['shipping_address']['name'] = '<script>alert(1)</script>';
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->get('/', ['Authorization' => 'Basic '.base64_encode('admin:s3cret')])
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
