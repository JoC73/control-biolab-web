<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashStore extends JsonStore
{
    public function search(array $filters = []): Collection
    {
        if ($this->usesDatabase()) {
            $query = DB::table('cash_movements')->orderByDesc('created_at');

            if ($filters['date'] ?? null) {
                $query->where('date', $filters['date']);
            }

            if ($filters['type'] ?? null) {
                $query->where('type', $filters['type']);
            }

            return collect($query->get())->map(fn ($row) => $this->fromRow($row))->values();
        }

        return $this->all()
            ->sortByDesc('created_at')
            ->when($filters['date'] ?? null, fn (Collection $rows, string $date) => $rows->where('date', $date))
            ->when($filters['type'] ?? null, fn (Collection $rows, string $type) => $rows->where('type', $type))
            ->values();
    }

    public function totals(?string $date = null): array
    {
        $rows = $this->search($date ? ['date' => $date] : []);
        $valid = $rows->where('status', 'active');

        $income = (float) $valid->where('type', 'income')->sum('amount');
        $expense = (float) $valid->where('type', 'expense')->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'voided' => $rows->where('status', 'voided')->count(),
        ];
    }

    public function create(array $data): array
    {
        if ($this->usesDatabase()) {
            $record = [
                'id' => (string) Str::uuid(),
                'type' => $data['type'],
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? 'efectivo',
                'description' => $data['description'] ?? '',
                'order_id' => $data['order_id'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'status' => 'active',
                'void_reason' => null,
                'voided_at' => null,
                'created_by' => session('biolab_user.name'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('cash_movements')->insert($record);

            return $this->fromRow((object) $record);
        }

        $records = $this->read();
        $record = [
            'id' => (string) Str::uuid(),
            'type' => $data['type'],
            'date' => $data['date'] ?? now()->toDateString(),
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'] ?? 'efectivo',
            'description' => $data['description'] ?? '',
            'order_id' => $data['order_id'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'status' => 'active',
            'void_reason' => null,
            'voided_at' => null,
            'created_by' => session('biolab_user.name'),
            'created_at' => now()->toDateTimeString(),
        ];

        $records[] = $record;
        $this->write($records);

        return $record;
    }

    public function void(string $id, string $reason): ?array
    {
        if ($this->usesDatabase()) {
            $record = DB::table('cash_movements')->where('id', $id)->first();

            if (! $record) {
                return null;
            }

            if (($record->status ?? null) === 'voided') {
                return $this->fromRow($record);
            }

            DB::table('cash_movements')->where('id', $id)->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
                'updated_at' => now(),
            ]);

            $updated = DB::table('cash_movements')->where('id', $id)->first();

            return $updated ? $this->fromRow($updated) : null;
        }

        $records = $this->read();
        $updated = null;

        foreach ($records as $index => $record) {
            if (($record['id'] ?? null) !== $id) {
                continue;
            }

            if (($record['status'] ?? null) === 'voided') {
                $updated = $record;
                break;
            }

            $record['status'] = 'voided';
            $record['void_reason'] = $reason;
            $record['voided_at'] = now()->toDateTimeString();
            $records[$index] = $updated = $record;
            break;
        }

        if ($updated) {
            $this->write($records);
        }

        return $updated;
    }

    public function hasOrderReversal(string $orderId): bool
    {
        if ($this->usesDatabase()) {
            return DB::table('cash_movements')
                ->where('order_id', $orderId)
                ->where('source', 'order_cancel_reversal')
                ->exists();
        }

        return $this->all()
            ->contains(fn (array $movement) => ($movement['order_id'] ?? null) === $orderId
                && ($movement['source'] ?? null) === 'order_cancel_reversal');
    }

    protected function path(): string
    {
        return storage_path('app/cash/movements.json');
    }

    private function usesDatabase(): bool
    {
        return config('database.default') !== 'sqlite' && config('biolab.storage') === 'database';
    }

    private function fromRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'type' => (string) $row->type,
            'date' => (string) $row->date,
            'amount' => (float) $row->amount,
            'method' => (string) ($row->method ?? ''),
            'description' => (string) ($row->description ?? ''),
            'order_id' => $row->order_id ?? null,
            'source' => $row->source ?? 'manual',
            'status' => (string) $row->status,
            'void_reason' => $row->void_reason ?? null,
            'voided_at' => $row->voided_at ?? null,
            'created_by' => $row->created_by ?? null,
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }
}
