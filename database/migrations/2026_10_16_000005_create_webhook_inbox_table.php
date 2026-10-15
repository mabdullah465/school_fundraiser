<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id');
            $table->string('topic');
            $table->string('shop_domain')->nullable();
            $table->char('payload_hash', 64);
            $table->longText('payload');
            $table->string('status', 20)->default('received'); // received | processed | failed | ignored
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbox');
    }
};
