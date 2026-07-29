<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_exam_items')) {
            Schema::create('order_exam_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->index();
                $table->string('category_slug');
                $table->string('category_name');
                $table->string('category_title');
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('discount', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('status')->default('pending');
                $table->json('tests')->nullable();
                $table->json('results')->nullable();
                $table->string('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('operational_orders')->cascadeOnDelete();
                $table->unique(['order_id', 'category_slug']);
            });
        }

        if (Schema::hasTable('saved_lab_results')) {
            Schema::table('saved_lab_results', function (Blueprint $table) {
                if (! Schema::hasColumn('saved_lab_results', 'exam_items')) {
                    $table->json('exam_items')->nullable()->after('results');
                }
                if (! Schema::hasColumn('saved_lab_results', 'order_total')) {
                    $table->decimal('order_total', 10, 2)->nullable()->after('exam_items');
                }
                if (! Schema::hasColumn('saved_lab_results', 'paid_amount')) {
                    $table->decimal('paid_amount', 10, 2)->nullable()->after('order_total');
                }
                if (! Schema::hasColumn('saved_lab_results', 'payment_status')) {
                    $table->string('payment_status')->nullable()->after('paid_amount');
                }
                if (! Schema::hasColumn('saved_lab_results', 'order_status')) {
                    $table->string('order_status')->nullable()->after('payment_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('saved_lab_results')) {
            Schema::table('saved_lab_results', function (Blueprint $table) {
                foreach (['order_status', 'payment_status', 'paid_amount', 'order_total', 'exam_items'] as $column) {
                    if (Schema::hasColumn('saved_lab_results', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('order_exam_items');
    }
};
