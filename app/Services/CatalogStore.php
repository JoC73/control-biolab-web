<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogStore extends JsonStore
{
    public function categories(): array
    {
        $baseCategories = collect(config('lab.categories'));
        $baseSlugs = $baseCategories->pluck('slug')->all();

        if ($this->usesDatabase()) {
            $saved = DB::table('custom_exam_templates')
                ->where('active', true)
                ->get()
                ->map(fn ($item) => [
                    'slug' => $item->slug,
                    'name' => $item->name,
                    'title' => $item->title,
                    'tests' => $this->decodeJson($item->tests),
                    'custom' => true,
                ])
                ->keyBy('slug');

            return $baseCategories
                ->map(function (array $category) use ($saved) {
                    $override = $saved->get($category['slug']);

                    return array_merge($category, [
                        'name' => $override['name'] ?? $category['name'],
                        'title' => $override['title'] ?? $category['title'],
                        'tests' => $this->normalizeTemplateForCategory($category['slug'], $override['tests'] ?? $category['tests']),
                        'custom' => false,
                    ]);
                })
                ->merge($saved->reject(fn (array $item) => in_array($item['slug'], $baseSlugs, true))->sortBy('name')->values())
                ->values()
                ->all();
        }

        $saved = $this->all()
            ->where('type', 'exam')
            ->where('active', true)
            ->map(fn (array $item) => [
                'slug' => $item['slug'],
                'name' => $item['name'],
                'title' => $item['title'] ?? $item['name'],
                'tests' => array_values($item['tests'] ?? []),
                'custom' => true,
            ])
            ->keyBy('slug');

        return $baseCategories
            ->map(function (array $category) use ($saved) {
                $override = $saved->get($category['slug']);

                return array_merge($category, [
                    'name' => $override['name'] ?? $category['name'],
                    'title' => $override['title'] ?? $category['title'],
                    'tests' => $this->normalizeTemplateForCategory($category['slug'], $override['tests'] ?? $category['tests']),
                    'custom' => false,
                ]);
            })
            ->merge($saved->reject(fn (array $item) => in_array($item['slug'], $baseSlugs, true))->sortBy('name')->values())
            ->values()
            ->all();
    }

    public function referrers(): array
    {
        if ($this->usesDatabase()) {
            $saved = DB::table('medical_referrers')
                ->where('active', true)
                ->pluck('name')
                ->all();

            return collect(config('lab.referrers'))
                ->merge($saved)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $saved = $this->all()->where('type', 'referrer')->where('active', true)->pluck('name')->all();

        return collect(config('lab.referrers'))
            ->merge($saved)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function prices(): array
    {
        $defaults = collect($this->categories())
            ->mapWithKeys(fn (array $category) => [$category['slug'] => $category['price'] ?? 0])
            ->all();

        if ($this->usesDatabase()) {
            $saved = DB::table('exam_prices')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->category_slug => (float) $item->price])
                ->all();

            return array_merge($defaults, $saved);
        }

        $saved = $this->all()
            ->where('type', 'price')
            ->mapWithKeys(fn (array $item) => [$item['slug'] => (float) ($item['price'] ?? 0)])
            ->all();

        return array_merge($defaults, $saved);
    }

    public function addReferrer(string $name): array
    {
        if ($this->usesDatabase()) {
            $name = Str::upper(trim($name));
            DB::table('medical_referrers')->updateOrInsert(
                ['name' => $name],
                ['active' => true, 'updated_at' => now(), 'created_at' => now()]
            );

            return ['name' => $name, 'active' => true];
        }

        $records = $this->read();
        $record = [
            'id' => (string) Str::uuid(),
            'type' => 'referrer',
            'name' => Str::upper(trim($name)),
            'active' => true,
            'created_at' => now()->toDateTimeString(),
        ];

        $records[] = $record;
        $this->write($records);

        return $record;
    }

    public function savePrice(string $slug, float $price): void
    {
        if ($this->usesDatabase()) {
            DB::table('exam_prices')->updateOrInsert(
                ['category_slug' => $slug],
                ['price' => round($price, 2), 'updated_at' => now(), 'created_at' => now()]
            );

            return;
        }

        $records = collect($this->read())
            ->reject(fn (array $item) => ($item['type'] ?? null) === 'price' && ($item['slug'] ?? null) === $slug)
            ->values()
            ->all();

        $records[] = [
            'id' => (string) Str::uuid(),
            'type' => 'price',
            'slug' => $slug,
            'price' => round($price, 2),
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->write($records);
    }

    public function saveExam(array $data): array
    {
        $slug = $this->uniqueSlug($data['name']);
        $tests = $this->normalizeTests($data['tests'] ?? []);

        if ($this->usesDatabase()) {
            $record = [
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'name' => trim($data['name']),
                'title' => trim(($data['title'] ?? '') ?: $data['name']),
                'tests' => json_encode($tests, JSON_UNESCAPED_UNICODE),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('custom_exam_templates')->insert($record);
            $this->savePrice($slug, (float) ($data['price'] ?? 0));

            return [
                'id' => $record['id'],
                'slug' => $slug,
                'name' => trim($data['name']),
                'title' => trim(($data['title'] ?? '') ?: $data['name']),
                'tests' => $tests,
                'active' => true,
            ];
        }

        $records = $this->read();
        $record = [
            'id' => (string) Str::uuid(),
            'type' => 'exam',
            'slug' => $slug,
            'name' => trim($data['name']),
            'title' => trim(($data['title'] ?? '') ?: $data['name']),
            'tests' => $tests,
            'active' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        $records[] = $record;
        $this->write($records);

        $this->savePrice($slug, (float) ($data['price'] ?? 0));

        return $record;
    }

    public function saveExamFields(string $slug, array $tests): ?array
    {
        $category = collect($this->categories())->firstWhere('slug', $slug);

        if (! $category) {
            return null;
        }

        $tests = $this->normalizeTests($tests);
        $name = trim($category['name']);
        $title = trim(($category['title'] ?? '') ?: $name);

        if ($this->usesDatabase()) {
            $existing = DB::table('custom_exam_templates')->where('slug', $slug)->first();
            $payload = [
                'name' => $name,
                'title' => $title,
                'tests' => json_encode($tests, JSON_UNESCAPED_UNICODE),
                'active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('custom_exam_templates')->where('slug', $slug)->update($payload);
            } else {
                DB::table('custom_exam_templates')->insert($payload + [
                    'id' => (string) Str::uuid(),
                    'slug' => $slug,
                    'created_at' => now(),
                ]);
            }

            return [
                'slug' => $slug,
                'name' => $name,
                'title' => $title,
                'tests' => $tests,
                'active' => true,
            ];
        }

        $records = collect($this->read())
            ->reject(fn (array $item) => ($item['type'] ?? null) === 'exam' && ($item['slug'] ?? null) === $slug)
            ->values()
            ->all();

        $record = [
            'id' => (string) Str::uuid(),
            'type' => 'exam',
            'slug' => $slug,
            'name' => $name,
            'title' => $title,
            'tests' => $tests,
            'active' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        $records[] = $record;
        $this->write($records);

        return $record;
    }

    public function deleteExam(string $slug): void
    {
        if ($this->usesDatabase()) {
            DB::table('custom_exam_templates')
                ->where('slug', $slug)
                ->update(['active' => false, 'updated_at' => now()]);

            return;
        }

        $records = collect($this->read())
            ->map(function (array $item) use ($slug) {
                if (($item['type'] ?? null) === 'exam' && ($item['slug'] ?? null) === $slug) {
                    $item['active'] = false;
                    $item['updated_at'] = now()->toDateTimeString();
                }

                return $item;
            })
            ->all();

        $this->write($records);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'examen';
        $existing = collect($this->categories())->pluck('slug')->all();
        $slug = $base;
        $counter = 2;

        while (in_array($slug, $existing, true)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function normalizeTests(array $tests): array
    {
        return collect($tests)
            ->filter(fn (array $test) => filled($test['name'] ?? null) || filled($test['unit'] ?? null) || filled($test['reference'] ?? null))
            ->map(fn (array $test) => [
                'name' => $this->upper(trim($test['name'] ?? '')),
                'unit' => trim($test['unit'] ?? ''),
                'reference' => trim($test['reference'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function normalizeTemplateForCategory(string $slug, array $tests): array
    {
        $tests = $this->normalizeTests($tests);

        return match ($slug) {
            'hematologia' => $this->ensureHematologySections($tests),
            'orina' => $this->ensureUrineSections($tests),
            'heces' => $this->ensureStoolSections($tests),
            default => $tests,
        };
    }

    private function ensureHematologySections(array $tests): array
    {
        if ($this->hasSectionName($tests, 'FORMULA DIFERENCIAL')) {
            return $tests;
        }

        return $this->insertAfterFirstMatch($tests, ['PLAQUETAS', 'RECUENTO DE PLAQUETAS'], [
            'name' => 'FORMULA DIFERENCIAL',
            'unit' => '',
            'reference' => '',
        ]);
    }

    private function ensureUrineSections(array $tests): array
    {
        $tests = $this->prependIfMissing($tests, 'EXAMEN FISICO');
        $tests = $this->insertBeforeFirstMatch($tests, 'EXAMEN QUIMICO', [
            'LEUCOCITOS',
            'NITRITOS',
            'PROTEINAS',
            'PROTEÍNAS',
            'GLUCOSA',
            'CETONAS',
            'BILIRRUBINAS',
            'UROBILINOGENO',
            'UROBILINÓGENO',
            'SANGRE',
            'HEMOGLOBINA',
        ]);

        return $this->insertBeforeFirstMatch($tests, 'EXAMEN MICROSCOPICO', [
            'CELULAS EPITELIALES',
            'CÉLULAS EPITELIALES',
            'ERITROCITOS',
            'BACTERIAS',
            'LEVADURAS',
            'MICELIO',
            'CRISTALES',
            'CILINDROS',
            'OTROS',
        ]);
    }

    private function ensureStoolSections(array $tests): array
    {
        $tests = $this->prependIfMissing($tests, 'EXAMEN MACROSCOPICO');
        $tests = $this->insertBeforeFirstMatch($tests, 'EXAMEN MICROSCOPICO', [
            'MOCO',
            'SANGRE OCULTA',
        ]);
        $tests = $this->insertBeforeFirstMatch($tests, 'PARASITOS', [
            'PARASITO',
            'PARASITOS',
            'PARÁSITO',
            'PARÁSITOS',
        ]);
        $tests = $this->appendSectionIfMissing($tests, 'HUEVOS');
        $tests = $this->appendSectionIfMissing($tests, 'QUISTES');
        $tests = $this->appendSectionIfMissing($tests, 'TROFOZOITOS');

        return $this->appendSectionIfMissing($tests, 'OTROS:');
    }

    private function prependIfMissing(array $tests, string $name): array
    {
        if ($this->hasSectionName($tests, $name)) {
            return $tests;
        }

        array_unshift($tests, ['name' => $name, 'unit' => '', 'reference' => '']);

        return $tests;
    }

    private function appendSectionIfMissing(array $tests, string $name): array
    {
        if (! $this->hasSectionName($tests, $name)) {
            $tests[] = ['name' => $name, 'unit' => '', 'reference' => ''];
        }

        return $tests;
    }

    private function insertAfterFirstMatch(array $tests, array $needles, array $row): array
    {
        $index = collect($tests)->search(fn (array $test) => in_array($this->upper($test['name'] ?? ''), $needles, true));

        if ($index === false) {
            $tests[] = $row;

            return $tests;
        }

        array_splice($tests, $index + 1, 0, [$row]);

        return $tests;
    }

    private function insertBeforeFirstMatch(array $tests, string $section, array $needles): array
    {
        if ($this->hasSectionName($tests, $section)) {
            return $tests;
        }

        $index = collect($tests)->search(fn (array $test) => in_array($this->upper($test['name'] ?? ''), $needles, true));

        if ($index === false) {
            $tests[] = ['name' => $section, 'unit' => '', 'reference' => ''];

            return $tests;
        }

        array_splice($tests, $index, 0, [['name' => $section, 'unit' => '', 'reference' => '']]);

        return $tests;
    }

    private function hasTestName(array $tests, string $name): bool
    {
        return collect($tests)->contains(fn (array $test) => $this->upper($test['name'] ?? '') === $name);
    }

    private function hasSectionName(array $tests, string $name): bool
    {
        return collect($tests)->contains(fn (array $test) => $this->upper($test['name'] ?? '') === $name
            && blank($test['unit'] ?? null)
            && blank($test['reference'] ?? null));
    }

    private function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
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

    protected function path(): string
    {
        return storage_path('app/catalog/catalog.json');
    }
}
