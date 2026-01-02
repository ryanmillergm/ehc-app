<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_list_id')
                ->constrained('email_lists')
                ->cascadeOnDelete();

            $table->string('subject');
            $table->longText('body_html');

            // draft|sending|sent|failed
            $table->string('status')->default('draft');

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('pending_chunks')->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['email_list_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
