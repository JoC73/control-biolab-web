<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_referrers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_prices', function (Blueprint $table) {
            $table->id();
            $table->string('category_slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('operational_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('patient_name');
            $table->string('age')->nullable();
            $table->string('phone')->nullable();
            $table->string('category_slug');
            $table->string('category_name');
            $table->string('category_title');
            $table->date('date');
            $table->string('referrer')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_timing')->default('before');
            $table->string('payment_status')->default('unpaid');
            $table->string('status')->default('pending_results');
            $table->json('tests')->nullable();
            $table->json('results')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->string('method')->nullable();
            $table->string('description');
            $table->uuid('order_id')->nullable();
            $table->string('status')->default('active');
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('operational_orders');
        Schema::dropIfExists('exam_prices');
        Schema::dropIfExists('medical_referrers');
    }
};
