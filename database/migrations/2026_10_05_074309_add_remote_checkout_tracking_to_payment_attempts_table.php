<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->timestamp('first_sent_at')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->string('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['last_checked_at']);
            $table->dropColumn(['first_sent_at', 'checkout_url', 'last_checked_at', 'last_error']);
        });
    }
};
