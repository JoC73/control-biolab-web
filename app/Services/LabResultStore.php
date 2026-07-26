<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LabResultStore
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/lab-results/results.json');
    }

    public function all(): Collection
    {
        if ($this->usesDatabase()) {
            return collect(DB::table('saved_lab_results')->whereNull('deleted_at')->orderByDesc('created_at')->get())
                ->map(fn ($row) => $this->fromRow($row))
                ->values();
        }

        return collect($this->read())
            ->where('deleted_at', null)
            ->sortByDesc(fn (array $result) => $result['created_at'] ?? '')
            ->values();
    }

    public function search(array $filters): Collection
    {
        return $this->all()
            ->when($filters['q'] ?? null, function (Collection $results, string $query) {
                $query = Str::lower($query);

                return $results->filter(function (array $result) use ($query) {
                    return Str::contains(Str::lower($result['patient_name'] ?? ''), $query)
                        || Str::contains(Str::lower($result['category_name'] ?? ''), $query)
                        || Str::contains(Str::lower($result['referred_by'] ?? ''), $query);
                });
            })
            ->when($filters['date_from'] ?? null, fn (Collection $results, string $date) => $results->where('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Collection $results, string $date) => $results->where('date', '<=', $date))
            ->values();
    }

    public function find(string $id): ?array
    {
        if ($this->usesDatabase()) {
            $row = DB::table('saved_lab_results')->where('id', $id)->whereNull('deleted_at')->first();

            return $row ? $this->fromRow($row) : null;
        }

        return $this->all()->firstWhere('id', $id);
    }

    public function save(array $payload): array
    {
        $now = now()->toDateTimeString();

        $record = [
            'id' => (string) Str::uuid(),
            'patient_name' => $payload['result']['patient_name'],
            'age' => $payload['result']['age'] ?? '',
            'referred_by' => $payload['result']['referred_by'] ?? '',
            'date' => $payload['result']['date'],
            'category_slug' => $payload['category']['slug'],
            'category_name' => $payload['category']['name'],
            'category_title' => $payload['category']['title'],
            'tests' => $payload['tests'],
            'results' => $payload['result']['results'] ?? [],
            'deleted_at' => null,
            'deleted_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->usesDatabase()) {
            DB::table('saved_lab_results')->insert($this->toDatabase($record));

            return $record;
        }

        $records = $this->read();
        $records[] = $record;
        $this->write($records);

        return $record;
    }

    public function update(string $id, array $payload): ?array
    {
        if ($this->usesDatabase()) {
            $current = $this->find($id);

            if (! $current) {
                return null;
            }

            $updated = array_merge($current, [
                'patient_name' => $payload['result']['patient_name'],
                'age' => $payload['result']['age'] ?? '',
                'referred_by' => $payload['result']['referred_by'] ?? '',
                'date' => $payload['result']['date'],
                'category_slug' => $payload['category']['slug'],
                'category_name' => $payload['category']['name'],
                'category_title' => $payload['category']['title'],
                'tests' => $payload['tests'],
                'results' => $payload['result']['results'] ?? [],
                'updated_at' => now()->toDateTimeString(),
            ]);

            DB::table('saved_lab_results')->where('id', $id)->update($this->toDatabase($updated, false));

            return $updated;
        }

        $records = $this->read();
        $now = now()->toDateTimeString();
        $updated = null;

        foreach ($records as $index => $record) {
            if (($record['id'] ?? null) !== $id) {
                continue;
            }

            $updated = array_merge($record, [
                'patient_name' => $payload['result']['patient_name'],
                'age' => $payload['result']['age'] ?? '',
                'referred_by' => $payload['result']['referred_by'] ?? '',
                'date' => $payload['result']['date'],
                'category_slug' => $payload['category']['slug'],
                'category_name' => $payload['category']['name'],
                'category_title' => $payload['category']['title'],
                'tests' => $payload['tests'],
                'results' => $payload['result']['results'] ?? [],
                'updated_at' => $now,
            ]);

            $records[$index] = $updated;
            break;
        }

        if ($updated !== null) {
            $this->write($records);
        }

        return $updated;
    }

    public function delete(string $id): void
    {
        if ($this->usesDatabase()) {
            DB::table('saved_lab_results')->where('id', $id)->update([
                'deleted_at' => now(),
                'deleted_by' => session('biolab_user.email'),
                'updated_at' => now(),
            ]);

            return;
        }

        $records = collect($this->read())
            ->map(function (array $result) use ($id) {
                if (($result['id'] ?? null) === $id) {
                    $result['deleted_at'] = now()->toDateTimeString();
                    $result['deleted_by'] = session('biolab_user.email');
                    $result['updated_at'] = now()->toDateTimeString();
                }

                return $result;
            })
            ->values()
            ->all();

        $this->write($records);
    }

    private function read(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $json = File::get($this->path);
        $records = json_decode($json, true);

        return is_array($records) ? $records : [];
    }

    private function write(array $records): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function usesDatabase(): bool
    {
        return config('database.default') !== 'sqlite' && config('biolab.storage') === 'database';
    }

    private function fromRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'patient_name' => (string) $row->patient_name,
            'age' => (string) ($row->age ?? ''),
            'referred_by' => (string) ($row->referred_by ?? ''),
            'date' => (string) $row->date,
            'category_slug' => (string) $row->category_slug,
            'category_name' => (string) $row->category_name,
            'category_title' => (string) $row->category_title,
            'tests' => $this->decodeJson($row->tests),
            'results' => $this->decodeJson($row->results),
            'deleted_at' => $row->deleted_at ?? null,
            'deleted_by' => $row->deleted_by ?? null,
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function toDatabase(array $record, bool $includeId = true): array
    {
        $data = [
            'patient_name' => $record['patient_name'],
            'age' => $record['age'] ?? null,
            'referred_by' => $record['referred_by'] ?? null,
            'date' => $record['date'],
            'category_slug' => $record['category_slug'],
            'category_name' => $record['category_name'],
            'category_title' => $record['category_title'],
            'tests' => json_encode($record['tests'] ?? [], JSON_UNESCAPED_UNICODE),
            'results' => json_encode($record['results'] ?? [], JSON_UNESCAPED_UNICODE),
            'deleted_at' => $record['deleted_at'] ?? null,
            'deleted_by' => $record['deleted_by'] ?? null,
            'created_at' => $record['created_at'] ?? now(),
            'updated_at' => $record['updated_at'] ?? now(),
        ];

        if ($includeId) {
            $data['id'] = $record['id'];
        }

        return $data;
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
