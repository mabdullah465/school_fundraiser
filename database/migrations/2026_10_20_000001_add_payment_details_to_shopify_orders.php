<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->string('payment_gateway')->nullable()->after('fulfillment_status');
            $table->json('tax_lines')->nullable()->after('tax_minor');          // [{title, rate, amount_minor}]
            $table->bigInteger('processing_fee_minor')->nullable()->after('total_minor'); // null = not known (yet)
            $table->json('fee_details')->nullable()->after('processing_fee_minor');       // [{type, rate, flat_fee_minor, amount_minor}]
            $table->string('fee_status', 20)->nullable()->after('fee_details');            // fetched | pending | unavailable | failed
            $table->timestamp('fees_fetched_at')->nullable()->after('fee_status');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_gateway', 'tax_lines', 'processing_fee_minor', 'fee_details', 'fee_status', 'fees_fetched_at']);
        });
    }
};
