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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('kind', 20);
            $table->unsignedBigInteger('value');
            $table->string('currency', 3);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('minimum_base_minor')->default(0);
            $table->unsignedInteger('max_uses');
            $table->boolean('applies_to_installers')->default(false);
            $table->boolean('applies_to_manual_prices')->default(false);
            $table->string('scope', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
