<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->string('status', 16)->default('pending')->index();
            $table->json('payload');
            $table->longText('raw_body');
            $table->char('body_hash', 64);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
