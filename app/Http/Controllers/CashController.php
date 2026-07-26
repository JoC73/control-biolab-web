<?php

namespace App\Http\Controllers;

use App\Services\CashStore;
use App\Services\AuditStore;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function __construct(
        private readonly CashStore $cash,
        private readonly AuditStore $audit,
    )
    {
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        return view('cash.index', [
            'movements' => $this->cash->search($request->only('date', 'type')),
            'totals' => $this->cash->totals($date),
            'filters' => ['date' => $date, 'type' => $request->input('type')],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:240'],
        ]);

        $movement = $this->cash->create($data + ['source' => 'manual']);
        $this->audit->record('cash_manual_created', 'cash', $movement['id'], [
            'type' => $movement['type'],
            'amount' => $movement['amount'],
        ]);

        return redirect()->route('cash.index', ['date' => $data['date']])->with('status', 'Movimiento registrado.');
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
}
