<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('age')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('test_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('report_title');
            $table->timestamps();
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('reference_value')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_category_id')->constrained();
            $table->date('reported_at');
            $table->string('referred_by')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('lab_order_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('test_name');
            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->string('reference_value')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_order_results');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('test_categories');
        Schema::dropIfExists('patients');
    }
};
