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
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_base_cents');
            $table->string('price_source', 16);
            $table->unsignedBigInteger('base_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('discounted_base_cents');
            $table->unsignedBigInteger('tax_basis_points');
            $table->unsignedBigInteger('tax_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->unique(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
