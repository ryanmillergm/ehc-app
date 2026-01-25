<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // SetupIntent is the stable id used for the subscription checkout flow.
            // Nullable because one-time payments never create a SetupIntent.
            $table->string('setup_intent_id')->nullable()->after('subscription_id');

            // Fast lookups for "complete()" + webhook reconciliation.
            $table->index('setup_intent_id');

            // Optional, but typically helpful if you often query by attempt_id + setup_intent_id.
            // $table->index(['attempt_id', 'setup_intent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['setup_intent_id']);
            $table->dropColumn('setup_intent_id');
        });
    }
};
