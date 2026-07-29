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
        $examItems = $this->prepareExamItems($data['exam_items'] ?? null, $category, $data, $now);
        $subtotal = round(collect($examItems)->sum('price'), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $total = max(0, round($subtotal - $discount, 2));
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        $primary = $examItems[0];

        $record = [
            'id' => (string) Str::uuid(),
            'patient_name' => $data['patient_name'],
            'age' => $data['age'] ?? '',
            'phone' => $data['phone'] ?? '',
            'category_slug' => $primary['category_slug'],
            'category_name' => $primary['category_name'],
            'category_title' => $primary['category_title'],
            'date' => $data['date'] ?? now()->toDateString(),
            'referrer' => $data['referrer'] ?? '',
            'price' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'paid_amount' => $paid,
            'payment_method' => $data['payment_method'] ?? 'efectivo',
            'payment_timing' => $data['payment_timing'] ?? 'before',
            'payment_status' => $this->paymentStatus($total, $paid),
            'status' => 'pending_results',
            'tests' => $primary['tests'],
            'results' => [],
            'exam_items' => $examItems,
            'delivered_at' => null,
            'cancel_reason' => null,
            'cancelled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($this->usesDatabase()) {
            DB::table('operational_orders')->insert($this->toDatabase($record));
            $this->replaceExamItems($record['id'], $examItems);

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
            $examIndex = (int) ($data['exam_index'] ?? 0);
            $examItems = $this->examItems($order);
            $examItems[$examIndex] ??= $this->legacyExamItem($order);

            $tests = collect($data['tests'] ?? [])
                ->filter(fn (array $test, int $index) => filled($test['name'] ?? null) || filled($data['results'][$index] ?? null))
                ->map(fn (array $test) => [
                    'name' => $test['name'] ?? '',
                    'unit' => $test['unit'] ?? '',
                    'reference' => $test['reference'] ?? '',
                ])
                ->values()
                ->all();

            $examItems[$examIndex]['tests'] = $tests;
            $examItems[$examIndex]['results'] = array_values($data['results'] ?? []);
            $examItems[$examIndex]['status'] = ($data['status'] ?? 'ready') === 'ready' ? 'ready' : 'pending';
            $examItems[$examIndex]['completed_by'] = session('biolab_user.email');
            $examItems[$examIndex]['completed_at'] = $examItems[$examIndex]['status'] === 'ready' ? now()->toDateTimeString() : null;

            $order['exam_items'] = array_values($examItems);
            $order['tests'] = $order['exam_items'][0]['tests'] ?? $tests;
            $order['results'] = $order['exam_items'][0]['results'] ?? array_values($data['results'] ?? []);
            $order['status'] = $this->allExamItemsReady($order) ? 'ready' : 'pending_results';

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
            if (($order['status'] ?? null) === 'delivered') {
                throw ValidationException::withMessages(['delivery' => 'Esta orden ya fue entregada.']);
            }

            if (! $this->hasExamItems($order) || ! $this->allExamItemsReady($order)) {
                throw ValidationException::withMessages(['delivery' => 'No puede entregarse: existen examenes pendientes.']);
            }

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
            $this->replaceExamItems($id, $updated['exam_items'] ?? $this->examItems($updated));

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

    public function examItems(array $order): array
    {
        $items = $order['exam_items'] ?? [];

        if (is_array($items) && count($items) > 0) {
            return array_values($items);
        }

        return [$this->legacyExamItem($order)];
    }

    public function hasExamItems(array $order): bool
    {
        return count($this->examItems($order)) > 0;
    }

    public function allExamItemsReady(array $order): bool
    {
        $items = $this->examItems($order);

        return count($items) > 0 && collect($items)->every(fn (array $item) => ($item['status'] ?? 'pending') === 'ready');
    }

    public function orderTitle(array $order): string
    {
        $items = $this->examItems($order);

        if (count($items) === 1) {
            return $items[0]['category_title'] ?? $items[0]['category_name'] ?? $order['category_title'];
        }

        return count($items).' examenes';
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
            'exam_items' => $this->databaseExamItems((string) $row->id),
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

    private function prepareExamItems(?array $requestedItems, array $category, array $data, string $now): array
    {
        $items = collect($requestedItems ?: [[
            'category_slug' => $category['slug'],
            'category_name' => $category['name'],
            'category_title' => $category['title'],
            'price' => $data['price'] ?? 0,
            'tests' => $category['tests'],
        ]])
            ->map(function (array $item) use ($now) {
                $price = round((float) ($item['price'] ?? 0), 2);

                return [
                    'id' => $item['id'] ?? (string) Str::uuid(),
                    'category_slug' => $item['category_slug'],
                    'category_name' => $item['category_name'],
                    'category_title' => $item['category_title'],
                    'price' => $price,
                    'discount' => round((float) ($item['discount'] ?? 0), 2),
                    'total' => $price,
                    'status' => $item['status'] ?? 'pending',
                    'tests' => array_values($item['tests'] ?? []),
                    'results' => array_values($item['results'] ?? []),
                    'completed_by' => $item['completed_by'] ?? null,
                    'completed_at' => $item['completed_at'] ?? null,
                    'created_at' => $item['created_at'] ?? $now,
                    'updated_at' => $item['updated_at'] ?? $now,
                ];
            })
            ->values()
            ->all();

        return count($items) > 0 ? $items : [$this->legacyExamItem([
            'id' => '',
            'category_slug' => $category['slug'],
            'category_name' => $category['name'],
            'category_title' => $category['title'],
            'price' => $data['price'] ?? 0,
            'tests' => $category['tests'],
            'results' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ])];
    }

    private function legacyExamItem(array $order): array
    {
        return [
            'id' => $order['id'].'-legacy',
            'category_slug' => $order['category_slug'],
            'category_name' => $order['category_name'],
            'category_title' => $order['category_title'],
            'price' => round((float) ($order['price'] ?? 0), 2),
            'discount' => round((float) ($order['discount'] ?? 0), 2),
            'total' => round((float) (($order['price'] ?? 0) - ($order['discount'] ?? 0)), 2),
            'status' => in_array($order['status'] ?? null, ['ready', 'delivered'], true) ? 'ready' : 'pending',
            'tests' => array_values($order['tests'] ?? []),
            'results' => array_values($order['results'] ?? []),
            'completed_by' => null,
            'completed_at' => null,
            'created_at' => $order['created_at'] ?? '',
            'updated_at' => $order['updated_at'] ?? '',
        ];
    }

    private function replaceExamItems(string $orderId, array $items): void
    {
        DB::table('order_exam_items')->where('order_id', $orderId)->delete();

        foreach ($items as $item) {
            DB::table('order_exam_items')->insert($this->examItemToDatabase($orderId, $item));
        }
    }

    private function databaseExamItems(string $orderId): array
    {
        if (! $this->usesDatabase()) {
            return [];
        }

        return DB::table('order_exam_items')
            ->where('order_id', $orderId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'category_slug' => (string) $row->category_slug,
                'category_name' => (string) $row->category_name,
                'category_title' => (string) $row->category_title,
                'price' => (float) $row->price,
                'discount' => (float) $row->discount,
                'total' => (float) $row->total,
                'status' => (string) $row->status,
                'tests' => $this->decodeJson($row->tests),
                'results' => $this->decodeJson($row->results),
                'completed_by' => $row->completed_by ?? null,
                'completed_at' => $row->completed_at ?? null,
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ])
            ->values()
            ->all();
    }

    private function examItemToDatabase(string $orderId, array $item): array
    {
        return [
            'id' => $item['id'] ?? (string) Str::uuid(),
            'order_id' => $orderId,
            'category_slug' => $item['category_slug'],
            'category_name' => $item['category_name'],
            'category_title' => $item['category_title'],
            'price' => $item['price'],
            'discount' => $item['discount'] ?? 0,
            'total' => $item['total'] ?? $item['price'],
            'status' => $item['status'] ?? 'pending',
            'tests' => json_encode($item['tests'] ?? [], JSON_UNESCAPED_UNICODE),
            'results' => json_encode($item['results'] ?? [], JSON_UNESCAPED_UNICODE),
            'completed_by' => $item['completed_by'] ?? null,
            'completed_at' => $item['completed_at'] ?? null,
            'created_at' => $item['created_at'] ?? now(),
            'updated_at' => $item['updated_at'] ?? now(),
        ];
    }
}
