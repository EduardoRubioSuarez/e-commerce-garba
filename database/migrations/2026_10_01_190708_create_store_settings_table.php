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
        Schema::create('store_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->default(1)->primary();
            $table->unsignedInteger('public_markup_basis_points');
            $table->unsignedInteger('installer_markup_basis_points');
            $table->unsignedInteger('tax_basis_points');
            $table->string('currency');
            $table->string('country');
            $table->string('timezone');
            $table->string('pickup_name');
            $table->string('pickup_address');
            $table->string('pickup_hours');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->boolean('is_demo');
            $table->unsignedBigInteger('version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
