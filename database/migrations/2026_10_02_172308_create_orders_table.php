<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('contact_name', 150);
            $table->string('contact_email');
            $table->string('phone_original', 64);
            $table->string('phone_canonical', 16);
            $table->string('tier', 16);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('base_cents');
            $table->unsignedBigInteger('tax_cents');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('settings_version');
            $table->char('fingerprint', 64);
            $table->char('token_hash', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
