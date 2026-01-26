<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('queue_probes', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default('mail');          // mail / job / etc.
            $table->string('status')->default('created');     // created / queued / running / sent / failed
            $table->string('to_email')->nullable();
            $table->string('mailer_default')->nullable();
            $table->string('mail_driver')->nullable();        // mail transport name
            $table->string('queue_connection')->nullable();
            $table->string('queue_name')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->text('meta')->nullable();                 // json-ish string
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_probes');
    }
};
