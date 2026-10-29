<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->cascadeOnUpdate();
        });
    }
};