<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignUuid('from_user')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('to_user')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['Pending', 'Paid'])->default('Pending');
            $table->dateTime('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
