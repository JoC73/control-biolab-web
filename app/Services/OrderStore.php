<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderStore extends JsonStore
{
    public function search(array $filters = []): Collection
    {
        if ($this->usesDatabase()) {
            $query = DB::table('operational_orders')->orderByDesc('created_at');

            if ($filters['q'] ?? null) {
                $q = Str::lower($filters['q']);
                $query->where(function ($query) use ($q) {
                    $query->whereRaw('LOWER(patient_name) LIKE ?', ['%'.$q.'%'])
                        ->orWhereRaw('LOWER(category_name) LIKE ?', ['%'.$q.'%'])
                        ->orWhereRaw('LOWER(referrer) LIKE ?', ['%'.$q.'%']);
                });
            }

            if ($filters['status'] ?? null) {
                $query->where('status', $filters['status']);
            }

            if ($filters['payment_status'] ?? null) {
                $query->where('payment_status', $filters['payment_status']);
            }

            return collect($query->get())->map(fn ($row) => $this->fromRow($row))->values();
        }

        return $this->all()
            ->sortByDesc('created_at')
            ->when($filters['q'] ?? null, function (Collection $orders, string $query) {
                $query = Str::lower($query);

                return $orders->filter(fn (array $order) => Str::contains(Str::lower($order['patient_name'] ?? ''), $query)
                    || Str::contains(Str::lower($order['category_name'] ?? ''), $query)
                    || Str::contains(Str::lower($order['referrer'] ?? ''), $query));
            })
            ->when($filters['status'] ?? null, fn (Collection $orders, string $status) => $orders->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Collection $orders, string $status) => $orders->where('payment_status', $status))
            ->values();
    }

    public function find(string $id): ?array
    {
        if ($this->usesDatabase()) {
            $row = DB::table('operational_orders')->where('id', $id)->first();

            return $row ? $this->fromRow($row) : null;
        }

        return $this->all()->firstWhere('id', $id);
    }

    public function create(array $data, array $category): array
    {
        $now = now()->toDateTimeString();
        $total = $this->total($data);
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);

        $record = [
            'id' => (string) Str::uuid(),
            'patient_name' => $data['patient_name'],
            'age' => $data['age'] ?? '',
            'phone' => $data['phone'] ?? '',
            'category_slug' => $category['slug'],
            'category_name' => $category['name'],
            'category_title' => $category['title'],
            'date' => $data['date'] ?? now()->toDateString(),
            'referrer' => $data['referrer'] ?? '',
            'price' => round((float) ($data['price'] ?? 0), 2),
            'discount' => round((float) ($data['discount'] ?? 0), 2),
            'total' => $total,
            'paid_amount' => $paid,
            'payment_method' => $data['payment_method'] ?? 'efectivo',
            'payment_timing' => $data['payment_timing'] ?? 'before',
            'payment_status' => $this->paymentStatus($total, $paid),
            'status' => 'pending_results',
            'tests' => $category['tests'],
            'results' => [],
            'delivered_at' => null,
            'cancel_reason' => null,
            'cancelled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->usesDatabase()) {
            DB::table('operational_orders')->insert($this->toDatabase($record));

            return $record;
        }

        $records = $this->read();
        $records[] = $record;
        $this->write($records);

        return $record;
    }

    public function updateResults(string $id, array $data): ?array
    {
        return $this->update($id, function (array $order) use ($data) {
            $tests = collect($data['tests'] ?? [])
                ->filter(fn (array $test, int $index) => filled($test['name'] ?? null) || filled($data['results'][$index] ?? null))
                ->map(fn (array $test) => [
                    'name' => $test['name'] ?? '',
                    'unit' => $test['unit'] ?? '',
                    'reference' => $test['reference'] ?? '',
                ])
                ->values()
                ->all();

            $order['tests'] = $tests;
            $order['results'] = array_values($data['results'] ?? []);
            $order['status'] = $data['status'] ?? 'ready';

            return $order;
        });
    }

    public function addPayment(string $id, float $amount, string $method): ?array
    {
        return $this->update($id, function (array $order) use ($amount, $method) {
            if (($order['status'] ?? null) === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'No se puede cobrar una orden anulada.']);
            }

            $balance = max(0, round((float) $order['total'] - (float) $order['paid_amount'], 2));
            $amount = round($amount, 2);

            if ($balance <= 0) {
                throw ValidationException::withMessages(['amount' => 'Esta orden ya esta pagada.']);
            }

            if ($amount !== $balance) {
                throw ValidationException::withMessages(['amount' => 'El cobro debe ser exactamente el saldo pendiente de Q '.number_format($balance, 2).'.']);
            }

            $order['paid_amount'] = round((float) ($order['paid_amount'] ?? 0) + $amount, 2);
            $order['payment_method'] = $method;
            $order['payment_status'] = $this->paymentStatus((float) $order['total'], (float) $order['paid_amount']);

            return $order;
        });
    }

    public function markDelivered(string $id): ?array
    {
        return $this->update($id, function (array $order) {
            $order['status'] = 'delivered';
            $order['delivered_at'] = now()->toDateTimeString();

            return $order;
        });
    }

    public function cancel(string $id, string $reason): ?array
    {
        return $this->update($id, function (array $order) use ($reason) {
            if (($order['status'] ?? null) === 'cancelled') {
                throw ValidationException::withMessages(['reason' => 'Esta orden ya fue anulada.']);
            }

            $order['status'] = 'cancelled';
            $order['cancel_reason'] = $reason;
            $order['cancelled_at'] = now()->toDateTimeString();

            return $order;
        });
    }

    protected function path(): string
    {
        return storage_path('app/orders/orders.json');
    }

    private function update(string $id, callable $callback): ?array
    {
        if ($this->usesDatabase()) {
            $row = DB::table('operational_orders')->where('id', $id)->lockForUpdate()->first();
            $current = $row ? $this->fromRow($row) : null;

            if (! $current) {
                return null;
            }

            $updated = $callback($current);
            $updated['updated_at'] = now()->toDateTimeString();

            DB::table('operational_orders')->where('id', $id)->update($this->toDatabase($updated, false));

            return $updated;
        }

        $records = $this->read();
        $updated = null;

        foreach ($records as $index => $record) {
            if (($record['id'] ?? null) !== $id) {
                continue;
            }

            $record = $callback($record);
            $record['updated_at'] = now()->toDateTimeString();
            $records[$index] = $updated = $record;
            break;
        }

        if ($updated) {
            $this->write($records);
        }

        return $updated;
    }

    private function total(array $data): float
    {
        return max(0, round((float) ($data['price'] ?? 0) - (float) ($data['discount'] ?? 0), 2));
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    public function usesDatabaseStorage(): bool
    {
        return $this->usesDatabase();
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
            'phone' => (string) ($row->phone ?? ''),
            'category_slug' => (string) $row->category_slug,
            'category_name' => (string) $row->category_name,
            'category_title' => (string) $row->category_title,
            'date' => (string) $row->date,
            'referrer' => (string) ($row->referrer ?? ''),
            'price' => (float) $row->price,
            'discount' => (float) $row->discount,
            'total' => (float) $row->total,
            'paid_amount' => (float) $row->paid_amount,
            'payment_method' => (string) ($row->payment_method ?? ''),
            'payment_timing' => (string) $row->payment_timing,
            'payment_status' => (string) $row->payment_status,
            'status' => (string) $row->status,
            'tests' => $this->decodeJson($row->tests),
            'results' => $this->decodeJson($row->results),
            'delivered_at' => $row->delivered_at ?? null,
            'cancel_reason' => $row->cancel_reason ?? null,
            'cancelled_at' => $row->cancelled_at ?? null,
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }

    private function toDatabase(array $record, bool $includeId = true): array
    {
        $data = [
            'patient_name' => $record['patient_name'],
            'age' => $record['age'] ?? null,
            'phone' => $record['phone'] ?? null,
            'category_slug' => $record['category_slug'],
            'category_name' => $record['category_name'],
            'category_title' => $record['category_title'],
            'date' => $record['date'],
            'referrer' => $record['referrer'] ?? null,
            'price' => $record['price'],
            'discount' => $record['discount'],
            'total' => $record['total'],
            'paid_amount' => $record['paid_amount'],
            'payment_method' => $record['payment_method'] ?? null,
            'payment_timing' => $record['payment_timing'] ?? 'before',
            'payment_status' => $record['payment_status'],
            'status' => $record['status'],
            'tests' => json_encode($record['tests'] ?? [], JSON_UNESCAPED_UNICODE),
            'results' => json_encode($record['results'] ?? [], JSON_UNESCAPED_UNICODE),
            'delivered_at' => $record['delivered_at'] ?? null,
            'cancel_reason' => $record['cancel_reason'] ?? null,
            'cancelled_at' => $record['cancelled_at'] ?? null,
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
