<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaign_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_campaign_id')
                ->constrained('email_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('email_subscriber_id')
                ->constrained('email_subscribers')
                ->cascadeOnDelete();

            // queued|sent|failed
            $table->string('status')->default('queued');

            $table->string('to_email');
            $table->string('to_name')->nullable();

            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();

            $table->string('subject');
            $table->longText('body_html')->nullable(); // rendered HTML at send time

            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->string('message_id')->nullable();

            $table->timestamps();

            $table->unique(
                ['email_campaign_id', 'email_subscriber_id'],
                'email_campaign_subscriber'
            );

            $table->index(['email_campaign_id', 'status'], 'ec_deliv_campaign_status_idx');
            $table->index(['to_email'], 'email_campaign_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_deliveries');
    }
};
