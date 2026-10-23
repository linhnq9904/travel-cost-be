<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnUpdate();
            $table->foreignUuid('expense_id')->nullable()->constrained('expenses')->cascadeOnUpdate();
            $table->uuid('key');
            $table->string('request_hash', 65);
            $table->timestamps();
            $table->unique(
                ['user_id', 'trip_id', 'key'],
                'idempotency_user_trip_key_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
