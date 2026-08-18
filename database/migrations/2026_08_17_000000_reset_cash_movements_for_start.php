<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_movements')) {
            return;
        }

        DB::table('cash_movements')->delete();

        if (Schema::hasTable('audit_events')) {
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(),
                'action' => 'cash_reset_for_start',
                'subject_type' => 'cash',
                'subject_id' => null,
                'user_name' => 'Sistema',
                'user_email' => null,
                'user_role' => null,
                'ip' => null,
                'meta' => json_encode(['reason' => 'Reinicio solicitado para comenzar caja en Q 0.00'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
