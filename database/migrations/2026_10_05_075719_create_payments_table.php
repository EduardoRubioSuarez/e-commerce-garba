<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->string('stripe_charge_id')->unique();
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('account_id');
            $table->boolean('livemode');
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('source');
            $table->string('evidence_id');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
