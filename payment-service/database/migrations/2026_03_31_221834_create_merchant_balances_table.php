<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['merchant_id', 'currency']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->decimal('balance', 18, 2)->default(0);
        });

        Schema::dropIfExists('merchant_balances');
    }
};
