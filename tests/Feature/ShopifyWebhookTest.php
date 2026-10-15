<?php

namespace Tests\Feature;

use App\Enums\AllocationStatus;
use App\Enums\IngestionStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderDisplayStatus;
use App\Enums\SchoolStatus;
use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\FundraisingRateVersion;
use App\Models\School;
use App\Models\ShopifyOrder;
use App\Models\WebhookInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SendsShopifyWebhooks;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SendsShopifyWebhooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useWebhookSecret();
        FundraisingRateVersion::factory()->schoolBps(3000)->create(); // 30%
    }

    // --- security ---------------------------------------------------------------

    public function test_rejects_a_bad_signature(): void
    {
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(), signWith: 'wrong-secret')
            ->assertStatus(401);

        $this->assertSame(0, WebhookInbox::count());
        $this->assertSame(0, ShopifyOrder::count());
    }

    public function test_rejects_everything_when_no_secret_is_configured(): void
    {
        config(['shopify.webhook_secret' => null]);

        $this->sendWebhook('orders/paid', FakeShopifyPayload::order())->assertStatus(503);
    }

    public function test_rejects_a_missing_signature(): void
    {
        $this->call('POST', '/api/v1/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
        ], json_encode(FakeShopifyPayload::order()))->assertStatus(401);
    }

    // --- donation allocation ----------------------------------------------------

    public function test_a_paid_order_credits_the_selected_school(): void
    {
        $school = School::factory()->create();

        $payload = FakeShopifyPayload::order([
            'school' => $school->ulid,
            'items' => [['title' => 'Team T-Shirt', 'price' => '200.00']],
            'shipping' => '10.00',
            'tax' => '16.00',
        ]);

        $this->sendWebhook('orders/paid', $payload)->assertOk()->assertJson(['status' => 'received']);

        $allocation = DonationAllocation::sole();
        $this->assertSame($school->id, $allocation->school_id);
        $this->assertSame(20000, $allocation->base_minor, 'shipping and tax are excluded from the base');
        $this->assertSame(6000, $allocation->amount_minor); // 30% of $200.00
        $this->assertSame(3000, $allocation->rate_bps_snapshot);
        $this->assertSame(AllocationStatus::Pending, $allocation->status);
        $this->assertStringStartsWith('TXN-', $allocation->public_ref);

        $entry = DonationLedgerEntry::sole();
        $this->assertSame(LedgerEntryType::DonationCredit, $entry->type);
        $this->assertSame(6000, $entry->amount_minor);

        $order = ShopifyOrder::sole();
        $this->assertSame(IngestionStatus::Ok, $order->ingestion_status);
        $this->assertSame(OrderDisplayStatus::Placed, $order->display_status);
    }

    public function test_ten_items_still_produce_exactly_one_allocation(): void
    {
        $school = School::factory()->create();
        $items = array_fill(0, 10, ['title' => 'Pen', 'price' => '10.00']);

        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid, 'items' => $items]))->assertOk();

        $this->assertSame(1, DonationAllocation::count());
        $this->assertSame(10, ShopifyOrder::sole()->items()->count());
        $this->assertSame(3000, DonationAllocation::sole()->amount_minor); // 30% of $100.00
    }

    public function test_the_donation_is_on_the_discounted_subtotal(): void
    {
        $school = School::factory()->create();

        $payload = FakeShopifyPayload::order([
            'school' => $school->ulid,
            'items' => [['title' => 'Hoodie', 'price' => '80.00', 'quantity' => 2, 'discount' => '20.00']],
        ]);

        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->assertSame(14000, DonationAllocation::sole()->base_minor); // 160 - 20
        $this->assertSame(4200, DonationAllocation::sole()->amount_minor);
    }

    public function test_gift_cards_and_excluded_products_do_not_count(): void
    {
        config(['donations.excluded_product_ids' => ['99']]);
        $school = School::factory()->create();

        $payload = FakeShopifyPayload::order([
            'school' => $school->ulid,
            'items' => [
                ['title' => 'Shirt', 'price' => '100.00'],
                ['title' => 'Gift card', 'price' => '50.00', 'gift_card' => true],
                ['title' => 'Excluded', 'price' => '50.00', 'product_id' => 99],
            ],
        ]);

        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->assertSame(10000, DonationAllocation::sole()->base_minor);
    }

    public function test_a_replayed_webhook_does_not_duplicate_anything(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid]);

        $this->sendWebhook('orders/paid', $payload, eventId: 'evt-1')->assertJson(['status' => 'received']);
        $this->sendWebhook('orders/paid', $payload, eventId: 'evt-1')->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookInbox::count());
        $this->assertSame(1, DonationAllocation::count());
        $this->assertSame(1, DonationLedgerEntry::count());
    }

    public function test_the_same_order_under_a_new_event_id_is_still_credited_once(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid]);

        $this->sendWebhook('orders/paid', $payload, eventId: 'evt-1')->assertOk();
        $this->sendWebhook('orders/paid', $payload, eventId: 'evt-2')->assertOk();

        $this->assertSame(1, DonationAllocation::count());
        $this->assertSame(1, DonationLedgerEntry::count());
    }

    public function test_created_then_paid_credits_only_on_paid(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid, 'financial_status' => 'pending']);

        $this->sendWebhook('orders/create', $payload)->assertOk();

        $this->assertSame(0, DonationAllocation::count(), 'an unpaid order is not a donation');
        $this->assertSame(IngestionStatus::Pending, ShopifyOrder::sole()->ingestion_status);

        $payload['financial_status'] = 'paid';
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->assertSame(1, DonationAllocation::count());
        $this->assertSame(1, ShopifyOrder::count());
    }

    public function test_updated_arriving_after_paid_does_not_double_credit(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid]);

        $this->sendWebhook('orders/paid', $payload)->assertOk();
        $this->sendWebhook('orders/updated', $payload)->assertOk();

        $this->assertSame(1, DonationAllocation::count());
    }

    // --- quarantine -------------------------------------------------------------

    public function test_a_paid_order_without_a_school_is_quarantined_not_credited(): void
    {
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => null]))->assertOk();

        $this->assertSame(0, DonationAllocation::count());
        $this->assertSame(0, DonationLedgerEntry::count());

        $order = ShopifyOrder::sole();
        $this->assertSame(IngestionStatus::Quarantined, $order->ingestion_status);
        $this->assertSame('missing_or_unknown_school', $order->quarantine_reason);
    }

    public function test_an_unknown_school_id_is_quarantined(): void
    {
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => '01JBNOSUCHSCHOOL0000000000']))->assertOk();

        $this->assertSame(0, DonationAllocation::count());
        $this->assertSame('missing_or_unknown_school', ShopifyOrder::sole()->quarantine_reason);
        $this->assertSame('01JBNOSUCHSCHOOL0000000000', ShopifyOrder::sole()->selected_school_raw);
    }

    public function test_a_school_that_is_no_longer_approved_is_quarantined(): void
    {
        $school = School::factory()->status(SchoolStatus::Deactivated)->create();

        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid]))->assertOk();

        $this->assertSame(0, DonationAllocation::count());
        $this->assertSame('school_not_approved', ShopifyOrder::sole()->quarantine_reason);
    }

    public function test_no_active_rate_quarantines_instead_of_guessing(): void
    {
        FundraisingRateVersion::query()->delete();
        $school = School::factory()->create();

        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid]))->assertOk();

        $this->assertSame('no_active_rate', ShopifyOrder::sole()->quarantine_reason);
    }

    public function test_a_client_supplied_percentage_is_ignored(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00']]]);
        $payload['note_attributes'][] = ['name' => 'donation_percentage', 'value' => '99'];
        $payload['note_attributes'][] = ['name' => 'donation_settings_version', 'value' => 'donation-v999'];

        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->assertSame(3000, DonationAllocation::sole()->amount_minor); // server rate, 30%
    }

    // --- rate changes -----------------------------------------------------------

    public function test_rate_changes_do_not_rewrite_existing_allocations(): void
    {
        $school = School::factory()->create();
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00']]]))->assertOk();

        $old = FundraisingRateVersion::current();
        $old->update(['status' => 'closed', 'active_slot' => null]);
        FundraisingRateVersion::factory()->schoolBps(5000)->create();

        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00']]]))->assertOk();

        $amounts = DonationAllocation::orderBy('id')->pluck('amount_minor')->all();
        $this->assertSame([3000, 5000], $amounts);
    }

    // --- refunds and cancellations -----------------------------------------------

    public function test_a_partial_refund_reduces_the_donation_with_a_new_ledger_entry(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order([
            'school' => $school->ulid,
            'items' => [['title' => 'A', 'price' => '100.00'], ['title' => 'B', 'price' => '100.00']],
        ]);
        $this->sendWebhook('orders/paid', $payload)->assertOk();
        $this->assertSame(6000, DonationAllocation::sole()->amount_minor);

        $lineId = $payload['line_items'][1]['id'];
        $refund = FakeShopifyPayload::refund($payload['id'], 777, [['line_item_id' => $lineId, 'subtotal' => '100.00']]);

        $this->sendWebhook('refunds/create', $refund)->assertOk();

        $allocation = DonationAllocation::sole();
        $this->assertSame(3000, $allocation->amount_minor);
        $this->assertSame(6000, $allocation->original_amount_minor, 'history is kept');

        $this->assertSame(
            [6000, -3000],
            DonationLedgerEntry::orderBy('id')->pluck('amount_minor')->all(),
        );
        $this->assertSame(3000, (int) DonationLedgerEntry::sum('amount_minor'));
    }

    public function test_a_replayed_refund_is_applied_once(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00'], ['price' => '100.00']]]);
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $refund = FakeShopifyPayload::refund($payload['id'], 777, [['line_item_id' => $payload['line_items'][0]['id'], 'subtotal' => '100.00']]);
        $this->sendWebhook('refunds/create', $refund, eventId: 'r1')->assertOk();
        $this->sendWebhook('refunds/create', $refund, eventId: 'r2')->assertOk(); // new event id, same refund

        $this->assertSame(3000, DonationAllocation::sole()->amount_minor);
    }

    public function test_a_full_refund_zeroes_the_donation(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00']]]);
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $refund = FakeShopifyPayload::refund($payload['id'], 1, [['line_item_id' => $payload['line_items'][0]['id'], 'subtotal' => '100.00']]);
        $this->sendWebhook('refunds/create', $refund)->assertOk();

        $this->assertSame(0, DonationAllocation::sole()->amount_minor);
        $this->assertSame(AllocationStatus::Refunded, DonationAllocation::sole()->status);
        $this->assertSame(0, (int) DonationLedgerEntry::sum('amount_minor'));
    }

    public function test_cancelling_an_order_reverses_the_donation(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid, 'items' => [['price' => '100.00']]]);
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $payload['cancelled_at'] = now()->toIso8601String();
        $this->sendWebhook('orders/cancelled', $payload)->assertOk();

        $this->assertSame(AllocationStatus::Cancelled, DonationAllocation::sole()->status);
        $this->assertSame(0, (int) DonationLedgerEntry::sum('amount_minor'));
        $this->assertSame(OrderDisplayStatus::Cancelled, ShopifyOrder::sole()->display_status);
        $this->assertSame(
            LedgerEntryType::CancellationReversal,
            DonationLedgerEntry::orderByDesc('id')->first()->type,
        );
    }

    // --- housekeeping ------------------------------------------------------------

    public function test_the_ledger_is_append_only(): void
    {
        $school = School::factory()->create();
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['school' => $school->ulid]))->assertOk();

        $entry = DonationLedgerEntry::sole();

        $this->expectException(\LogicException::class);
        $entry->update(['amount_minor' => 1]);
    }

    public function test_fulfillment_events_move_the_admin_status_forward(): void
    {
        $school = School::factory()->create();
        $payload = FakeShopifyPayload::order(['school' => $school->ulid]);
        $this->sendWebhook('orders/paid', $payload)->assertOk();

        $this->sendWebhook('fulfillments/create', ['order_id' => $payload['id'], 'status' => 'success'])->assertOk();
        $this->assertSame(OrderDisplayStatus::Shipped, ShopifyOrder::sole()->display_status);

        $this->sendWebhook('fulfillment_events/create', ['order_id' => $payload['id'], 'status' => 'delivered'])->assertOk();
        $this->assertSame(OrderDisplayStatus::Received, ShopifyOrder::sole()->display_status);
    }

    public function test_unsupported_topics_are_stored_and_ignored(): void
    {
        $this->sendWebhook('customers/create', ['id' => 1])->assertOk();

        $this->assertSame('ignored', WebhookInbox::sole()->status);
    }

    public function test_a_failed_job_is_retried_when_shopify_redelivers(): void
    {
        $this->sendWebhook('refunds/create', FakeShopifyPayload::refund(123, 1, []), eventId: 'evt-r')
            ->assertStatus(500); // refund for an order we have not seen yet

        $this->assertSame('failed', WebhookInbox::sole()->status);

        // The order shows up, then Shopify redelivers the same refund event.
        $school = School::factory()->create();
        $this->sendWebhook('orders/paid', FakeShopifyPayload::order(['id' => 123, 'school' => $school->ulid]))->assertOk();

        $this->sendWebhook('refunds/create', FakeShopifyPayload::refund(123, 1, []), eventId: 'evt-r')
            ->assertOk()
            ->assertJson(['status' => 'retrying']);

        $this->assertSame('processed', WebhookInbox::where('event_id', 'evt-r')->sole()->status);
    }
}
