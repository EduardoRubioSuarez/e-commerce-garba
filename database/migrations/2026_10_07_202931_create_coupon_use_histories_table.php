<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_use_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_use_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('evidence_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_use_histories');
    }
};
