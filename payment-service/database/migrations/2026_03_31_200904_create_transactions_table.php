<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->enum('type', ['payment', 'refund']);
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('original_transaction_id')->nullable();
            $table->text('fraud_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('original_transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
