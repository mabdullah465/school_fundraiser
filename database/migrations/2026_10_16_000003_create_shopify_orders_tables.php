<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_order_id')->unique();
            $table->string('order_number')->nullable(); // "#1001"
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('shipping_address')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('shipping_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('financial_status', 40)->nullable();
            $table->string('fulfillment_status', 40)->nullable();
            $table->string('display_status', 20)->default('pending');
            $table->foreignId('selected_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('selected_school_raw')->nullable(); // value of the cart attribute as received, for audit
            $table->string('ingestion_status', 20)->default('pending');
            $table->string('quarantine_reason')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['ingestion_status', 'created_at']);
        });

        Schema::create('shopify_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shopify_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_line_item_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('title');
            $table->string('variant_title')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('net_eligible_minor')->default(0);
            $table->boolean('is_eligible')->default(true);
            $table->timestamps();

            $table->unique(['order_id', 'shopify_line_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_order_items');
        Schema::dropIfExists('shopify_orders');
    }
};
