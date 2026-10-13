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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('coupon_use_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('coupon_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_use_id');
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_snapshot');
        });
    }
};
