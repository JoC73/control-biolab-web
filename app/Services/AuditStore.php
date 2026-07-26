<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditStore extends JsonStore
{
    public function all(): Collection
    {
        if ($this->usesDatabase()) {
            return collect(DB::table('audit_events')->orderByDesc('created_at')->get())
                ->map(fn ($row) => [
                    'id' => (string) $row->id,
                    'action' => (string) $row->action,
                    'subject_type' => (string) $row->subject_type,
                    'subject_id' => $row->subject_id,
                    'user_name' => $row->user_name ?? 'Sistema',
                    'user_email' => $row->user_email,
                    'user_role' => $row->user_role,
                    'ip' => $row->ip,
                    'meta' => $this->decodeJson($row->meta),
                    'created_at' => (string) $row->created_at,
                ])
                ->values();
        }

        return parent::all();
    }

    public function record(string $action, string $subjectType, ?string $subjectId = null, array $meta = []): array
    {
        $user = session('biolab_user');
        $record = [
            'id' => (string) Str::uuid(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_name' => $user['name'] ?? 'Sistema',
            'user_email' => $user['email'] ?? null,
            'user_role' => $user['role'] ?? null,
            'ip' => request()?->ip(),
            'meta' => $meta,
            'created_at' => now()->toDateTimeString(),
        ];

        if ($this->usesDatabase()) {
            $dbRecord = $record;
            $dbRecord['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            DB::table('audit_events')->insert($dbRecord);

            return $record;
        }

        $records = $this->read();
        $records[] = $record;
        $this->write($records);

        return $record;
    }

    protected function path(): string
    {
        return storage_path('app/audit/audit.json');
    }

    private function usesDatabase(): bool
    {
        return config('database.default') !== 'sqlite' && config('biolab.storage') === 'database';
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
