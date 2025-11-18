<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // Owner of the address (user can have many addresses)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Optional label to help user identify it
            $table->string('label')->nullable(); // e.g. "Home", "Work"

            // Contact details (can override user name if needed)
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();

            // Address lines
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code');
            $table->string('country', 2)->default('US'); // ISO country code

            // Optional phone on this address
            $table->string('phone')->nullable();

            // Primary flag (enforce “only one” in app logic)
            $table->boolean('is_primary')->default(false)->index();

            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
