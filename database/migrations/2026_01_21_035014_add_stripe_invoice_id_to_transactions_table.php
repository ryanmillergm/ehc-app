<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->index()->after('subscription_id');

            // Hard “no duplicates” guard for subscription-land.
            $table->unique(['pledge_id', 'stripe_invoice_id'], 'tx_unique_pledge_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('tx_unique_pledge_invoice');
            $table->dropColumn('stripe_invoice_id');
        });
    }
};
