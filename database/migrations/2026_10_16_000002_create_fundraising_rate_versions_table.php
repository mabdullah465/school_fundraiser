<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundraising_rate_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique(); // e.g. donation-v12
            $table->unsignedSmallInteger('school_bps'); // 3000 = 30%. business share = 10000 - school_bps
            $table->string('calculation_basis');
            $table->boolean('included_in_price')->default(true);
            $table->string('status', 20)->default('active');
            // 1 while active, NULL once closed. A unique index over a nullable column
            // allows many NULLs, so this enforces "at most one active row" on MySQL and SQLite.
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->timestamp('effective_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_rate_versions');
    }
};
