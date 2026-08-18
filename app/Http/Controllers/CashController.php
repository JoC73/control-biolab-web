<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use App\Services\CashStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashController extends Controller
{
    public function __construct(
        private readonly CashStore $cash,
        private readonly AuditStore $audit,
    ) {}

    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? $request->input('month')
            : now()->format('Y-m');
        $period = in_array($request->input('period'), ['day', 'week', 'month', 'year'], true)
            ? $request->input('period')
            : 'day';
        [$dateFrom, $dateTo] = $this->periodRange($period, $date, $month);

        return view('cash.index', [
            'movements' => $this->cash->search([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'type' => $request->input('type'),
            ]),
            'totals' => $this->cash->periodTotals($dateFrom, $dateTo),
            'dailyTotals' => $this->cash->totals($date),
            'monthlyTotals' => $this->cash->monthlyTotals($month),
            'filters' => [
                'date' => $date,
                'month' => $month,
                'period' => $period,
                'type' => $request->input('type'),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:expense'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:240'],
        ]);

        $available = round((float) $this->cash->totals($data['date'])['balance'], 2);
        $amount = round((float) $data['amount'], 2);

        if ($amount > $available) {
            return back()
                ->withErrors(['amount' => 'El egreso no puede exceder el saldo disponible del dia: Q '.number_format($available, 2).'.'])
                ->withInput();
        }

        $movement = $this->cash->create($data + ['source' => 'manual']);
        $this->audit->record('cash_expense_created', 'cash', $movement['id'], [
            'type' => $movement['type'],
            'amount' => $movement['amount'],
        ]);

        return redirect()->route('cash.index', ['date' => $data['date']])->with('status', 'Egreso registrado.');
    }

    public function void(Request $request, string $id)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);
        $movement = $this->cash->void($id, $data['reason']);
        $this->audit->record('cash_voided', 'cash', $id, [
            'reason' => $data['reason'],
            'amount' => $movement['amount'] ?? null,
        ]);

        return redirect()->route('cash.index')->with('status', 'Movimiento anulado.');
    }

    private function periodRange(string $period, string $date, string $month): array
    {
        $baseDate = Carbon::parse($date);

        return match ($period) {
            'week' => [$baseDate->copy()->startOfWeek()->toDateString(), $baseDate->copy()->endOfWeek()->toDateString()],
            'month' => [Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString(), Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString()],
            'year' => [$baseDate->copy()->startOfYear()->toDateString(), $baseDate->copy()->endOfYear()->toDateString()],
            default => [$baseDate->toDateString(), $baseDate->toDateString()],
        };
    }
}
