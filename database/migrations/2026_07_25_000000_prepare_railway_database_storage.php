<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_exam_templates')) {
            Schema::create('custom_exam_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('title');
                $table->json('tests')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('saved_lab_results')) {
            Schema::create('saved_lab_results', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('patient_name');
                $table->string('age')->nullable();
                $table->string('referred_by')->nullable();
                $table->date('date');
                $table->string('category_slug');
                $table->string('category_name');
                $table->string('category_title');
                $table->json('tests')->nullable();
                $table->json('results')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('deleted_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_events')) {
            Schema::create('audit_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('action')->index();
                $table->string('subject_type')->index();
                $table->string('subject_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('user_email')->nullable();
                $table->string('user_role')->nullable();
                $table->string('ip')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::table('operational_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('operational_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            }
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'source')) {
                $table->string('source')->default('manual')->after('order_id');
            }
            if (! Schema::hasColumn('cash_movements', 'created_by')) {
                $table->string('created_by')->nullable()->after('voided_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('cash_movements', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::table('operational_orders', function (Blueprint $table) {
            if (Schema::hasColumn('operational_orders', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });

        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('saved_lab_results');
        Schema::dropIfExists('custom_exam_templates');
    }
};
