<?php

namespace Tests\Feature;

use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use App\Jobs\FetchOrderPaymentFees;
use App\Models\FundraisingRateVersion;
use App\Models\School;
use App\Models\ShopifyOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SendsShopifyWebhooks;
use Tests\TestCase;

class PaymentDetailsTest extends TestCase
{
    use RefreshDatabase;
    use SendsShopifyWebhooks;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->useWebhookSecret();
        FundraisingRateVersion::factory()->schoolBps(3000)->create();
        config([
            'shopify.store_domain' => 'demo.myshopify.com',
            'shopify.admin_token' => 'shpat_test',
        ]);
    }

    private function paidOrder(array $options = []): ShopifyOrder
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order($options + ['school' => $school->ulid, 'items' => [['price' => '100.00']], 'tax' => '8.25']);
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        return ShopifyOrder::sole();
    }

    private function fakeTransactions(array $transactions): void
    {
        Http::fake(['demo.myshopify.com/admin/api/*' => Http::response(['data' => ['order' => ['transactions' => $transactions]]])]);
    }

    // --- sales tax: already in the webhook ----------------------------------------

    public function test_sales_tax_and_its_breakdown_are_stored_from_the_webhook(): void
    {
        $order = $this->paidOrder();

        $this->assertSame(825, $order->tax_minor);
        $this->assertSame([['title' => 'State Tax', 'rate' => 0.0825, 'amount_minor' => 825]], $order->tax_lines);
        $this->assertSame('shopify_payments', $order->payment_gateway);
    }

    public function test_tax_does_not_change_the_donation(): void
    {
        $this->paidOrder();

        $this->assertSame(3000, ShopifyOrder::sole()->allocation->amount_minor); // 30% of 100.00, tax excluded
    }

    // --- processing fee: second lookup ----------------------------------------------

    public function test_a_paid_order_queues_the_fee_lookup(): void
    {
        Bus::fake([FetchOrderPaymentFees::class]);
        config(['shopify.fetch_fees' => true]);

        $this->paidOrder();

        Bus::assertDispatched(FetchOrderPaymentFees::class);
    }

    public function test_no_lookup_when_fees_are_switched_off(): void
    {
        Bus::fake([FetchOrderPaymentFees::class]);
        config(['shopify.fetch_fees' => false]);

        $this->paidOrder();

        Bus::assertNotDispatched(FetchOrderPaymentFees::class);
    }

    public function test_shopify_payments_fees_are_stored_with_their_breakdown(): void
    {
        $order = $this->paidOrder();
        $this->fakeTransactions([[
            'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments',
            'fees' => [
                ['type' => 'card_processing', 'rate' => '0.029', 'flatFee' => ['amount' => '0.30'], 'amount' => ['amount' => '3.46', 'currencyCode' => 'USD']],
            ],
        ]]);

        (new FetchOrderPaymentFees($order->id))->handle(app(\App\Integrations\Shopify\ShopifyClient::class));

        $order->refresh();
        $this->assertSame(346, $order->processing_fee_minor);
        $this->assertSame('fetched', $order->fee_status);
        $this->assertSame([['type' => 'card_processing', 'rate' => 0.029, 'flat_fee_minor' => 30, 'amount_minor' => 346]], $order->fee_details);

        Http::assertSent(fn ($r) => str_contains($r->data()['query'] ?? '', 'fees')
            && (((array) $r->data()['variables'])['id'] ?? null) === "gid://shopify/Order/{$order->shopify_order_id}");
    }

    public function test_only_successful_sales_and_captures_count(): void
    {
        $order = $this->paidOrder();
        $fee = fn ($amount) => [['type' => 'card_processing', 'rate' => '0.029', 'flatFee' => ['amount' => '0.30'], 'amount' => ['amount' => $amount]]];
        $this->fakeTransactions([
            ['kind' => 'SALE', 'status' => 'SUCCESS', 'fees' => $fee('3.46')],
            ['kind' => 'SALE', 'status' => 'FAILURE', 'fees' => $fee('9.99')],
            ['kind' => 'REFUND', 'status' => 'SUCCESS', 'fees' => $fee('1.11')],
        ]);

        (new FetchOrderPaymentFees($order->id))->handle(app(\App\Integrations\Shopify\ShopifyClient::class));

        $this->assertSame(346, $order->refresh()->processing_fee_minor);
    }

    public function test_other_gateways_are_marked_unavailable_without_calling_shopify(): void
    {
        Http::fake();
        $order = $this->paidOrder(['gateway' => 'stripe']);

        (new FetchOrderPaymentFees($order->id))->handle(app(\App\Integrations\Shopify\ShopifyClient::class));

        $order->refresh();
        $this->assertNull($order->processing_fee_minor);
        $this->assertSame('unavailable', $order->fee_status);
        Http::assertNothingSent();
    }

    public function test_fees_that_have_not_appeared_yet_are_retried_later(): void
    {
        Bus::fake([FetchOrderPaymentFees::class]);
        $order = $this->paidOrder();
        $this->fakeTransactions([['kind' => 'SALE', 'status' => 'SUCCESS', 'fees' => []]]);

        (new FetchOrderPaymentFees($order->id))->handle(app(\App\Integrations\Shopify\ShopifyClient::class));

        $this->assertSame('pending', $order->refresh()->fee_status);
        Bus::assertDispatched(FetchOrderPaymentFees::class, fn ($job) => $job->attempt === 2);
    }

    public function test_it_gives_up_after_the_last_retry(): void
    {
        $order = $this->paidOrder();
        $this->fakeTransactions([]);

        (new FetchOrderPaymentFees($order->id, attempt: 4))->handle(app(\App\Integrations\Shopify\ShopifyClient::class));

        $this->assertSame('unavailable', $order->refresh()->fee_status);
    }

    public function test_an_api_failure_never_fails_the_order_webhook(): void
    {
        config(['shopify.fetch_fees' => true]);
        Http::fake(['demo.myshopify.com/*' => Http::response('boom', 500)]);

        $school = School::factory()->create();
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid]))->assertOk();

        $this->assertSame(1, ShopifyOrder::count());
        $this->assertSame(1, ShopifyOrder::sole()->allocation()->count(), 'the donation is recorded regardless');
    }
}
