<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->string('phone_canonical', 32);
            $table->string('status', 16)->default('free');
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->unique(['coupon_id', 'phone_canonical']);
            $table->index(['coupon_id', 'status']);
            $table->unique('payment_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');
    }
};
