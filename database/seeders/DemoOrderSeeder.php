<?php

namespace Database\Seeders;

use App\Domain\Orders\Actions\IngestShopifyOrder;
use App\Integrations\Shopify\Fixtures\FakeShopifyPayload;
use App\Integrations\Shopify\Support\NormalizedOrder;
use Illuminate\Database\Seeder;

/**
 * A handful of dummy paid orders pushed through the real ingestion code
 * (not inserted directly), so the demo data exercises the same path Shopify will.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(IngestShopifyOrder $ingest): void
    {
        $orders = [
            // Single item, Lincoln.
            ['id' => 5100000000001, 'number' => 1001, 'school' => SchoolSeeder::ulid(1),
                'items' => [['title' => 'Team T-Shirt', 'price' => '50.00']]],
            // Several items in one order still produce one allocation.
            ['id' => 5100000000002, 'number' => 1002, 'school' => SchoolSeeder::ulid(2),
                'items' => [
                    ['title' => 'T-Shirt', 'price' => '50.00'],
                    ['title' => 'Shoes', 'price' => '100.00'],
                    ['title' => 'Backpack', 'price' => '50.00'],
                ]],
            // Discounted: donation is on the discounted subtotal.
            ['id' => 5100000000003, 'number' => 1003, 'school' => SchoolSeeder::ulid(5),
                'items' => [['title' => 'Hoodie', 'price' => '80.00', 'quantity' => 2, 'discount' => '20.00']],
                'fulfillment_status' => 'fulfilled'],
            ['id' => 5100000000004, 'number' => 1004, 'school' => SchoolSeeder::ulid(8),
                'items' => [['title' => 'Water Bottle', 'price' => '24.99']]],
            // No school selected: quarantined, nobody is credited.
            ['id' => 5100000000005, 'number' => 1005, 'school' => null,
                'items' => [['title' => 'Cap', 'price' => '20.00']]],
            // School that is not approved: quarantined.
            ['id' => 5100000000006, 'number' => 1006, 'school' => SchoolSeeder::ulid(4),
                'items' => [['title' => 'Cap', 'price' => '20.00']]],
        ];

        foreach ($orders as $options) {
            $ingest->handle(NormalizedOrder::fromPayload(FakeShopifyPayload::order($options)));
        }
    }
}
