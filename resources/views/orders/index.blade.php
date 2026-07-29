@extends('layouts.lab', ['title' => 'Ordenes'])

@section('body')
    @php
        $auth = app(\App\Services\AuthStore::class);
        $statusLabels = ['pending_results' => 'Pendiente resultado', 'ready' => 'Listo', 'delivered' => 'Entregado', 'cancelled' => 'Anulado'];
        $paymentLabels = ['unpaid' => 'Sin pago', 'partial' => 'Parcial', 'paid' => 'Pagado'];
    @endphp

    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Operacion</p>
                <h1>Ordenes y cobros</h1>
                <p>Consulta las solicitudes registradas, su estado de pago y avance de resultados.</p>
            </div>
            <div class="top-actions">
                @if ($auth->hasPermission('laboratory.view'))
                    <a class="button" href="{{ route('orders.lab') }}">Laboratorio</a>
                @endif
                @if ($auth->hasPermission('orders.create'))
                    <a class="button primary" href="{{ route('orders.create') }}">Registrar cobro</a>
                @endif
            </div>
        </header>

        <section class="panel">
            <form class="filters" method="GET" action="{{ route('orders.index') }}">
                <div class="field">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Paciente, examen o referencia">
                </div>
                <div class="field">
                    <label for="status">Estado</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        @foreach (['pending_results' => 'Pendiente resultado', 'ready' => 'Listo', 'delivered' => 'Entregado', 'cancelled' => 'Anulado'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="payment_status">Pago</label>
                    <select id="payment_status" name="payment_status">
                        <option value="">Todos</option>
                        @foreach (['unpaid' => 'Sin pago', 'partial' => 'Parcial', 'paid' => 'Pagado'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="button primary" type="submit">Buscar</button>
                    <a class="button" href="{{ route('orders.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="history-list">
                @forelse ($orders as $order)
                    @php
                        $examItems = $order['exam_items'] ?? [];
                        $examCount = count($examItems);
                        $examNames = collect($examItems)->pluck('name')->filter()->take(3)->implode(', ');
                        $balance = max(0, (float) $order['total'] - (float) $order['paid_amount']);
                    @endphp
                    <article class="history-row">
                        <a href="{{ route('orders.show', $order['id']) }}">
                            <strong>{{ $order['patient_name'] }}</strong>
                            <span>
                                {{ $examCount > 1 ? $examCount.' examenes' : $order['category_name'] }}
                                @if ($examCount > 1 && $examNames)
                                    · {{ $examNames }}{{ $examCount > 3 ? '...' : '' }}
                                @endif
                                · {{ \Illuminate\Support\Carbon::parse($order['date'])->format('d/m/Y') }}
                            </span>
                            <em>{{ $order['referrer'] ?: 'Sin referencia' }}</em>
                            <span class="badge-line">
                                <span class="status-badge status-{{ $order['status'] }}">{{ $statusLabels[$order['status']] ?? $order['status'] }}</span>
                                <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $paymentLabels[$order['payment_status']] ?? $order['payment_status'] }}</span>
                                <span class="soft-badge">Total Q {{ number_format($order['total'], 2) }}</span>
                                @if ($balance > 0)
                                    <span class="soft-badge">Saldo Q {{ number_format($balance, 2) }}</span>
                                @endif
                            </span>
                        </a>
                        <div class="row-tools">
                            <a class="button" href="{{ route('orders.show', $order['id']) }}">Ver</a>
                            @if ($order['status'] !== 'cancelled' && ($auth->hasPermission('results.create') || $auth->hasPermission('results.edit')))
                                <a class="button" href="{{ route('orders.results', $order['id']) }}">Resultados</a>
                            @endif
                            @if ($auth->hasPermission('results.print'))
                                <a class="button" href="{{ route('orders.pdf', $order['id']) }}">PDF</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty-state">Aun no hay ordenes registradas.</p>
                @endforelse
            </div>
        </section>
    </main>
@endsection
