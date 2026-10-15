<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('public_ref')->unique(); // TXN-584721
            // UNIQUE: exactly one allocation per order. This is the idempotency guarantee.
            $table->foreignId('shopify_order_id')->unique()->constrained('shopify_orders')->restrictOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('rate_version_id')->constrained('fundraising_rate_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('rate_bps_snapshot');
            $table->bigInteger('base_minor');            // eligible subtotal the rate was applied to (current, after refunds)
            $table->bigInteger('original_amount_minor'); // amount at allocation time
            $table->bigInteger('amount_minor');          // current amount after refund adjustments
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->timestamp('payable_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        // Append-only. Corrections are new rows, never updates.
        Schema::create('donation_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->nullable()->constrained('donation_allocations')->restrictOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('type', 40);
            $table->bigInteger('amount_minor'); // signed
            $table->char('currency', 3);
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_ledger_entries');
        Schema::dropIfExists('donation_allocations');
    }
};
