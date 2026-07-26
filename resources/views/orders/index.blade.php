@extends('layouts.lab', ['title' => 'Ordenes'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Operacion</p>
                <h1>Ordenes de laboratorio</h1>
            </div>
            <div class="top-actions">
                <a class="button primary" href="{{ route('orders.create') }}">Nueva orden</a>
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
                    <article class="history-row">
                        <a href="{{ route('orders.show', $order['id']) }}">
                            <strong>{{ $order['patient_name'] }}</strong>
                            <span>{{ $order['category_name'] }} · {{ \Illuminate\Support\Carbon::parse($order['date'])->format('d/m/Y') }}</span>
                            <em>{{ $order['referrer'] ?: 'Sin referencia' }}</em>
                            <span class="badge-line">
                                <span class="status-badge status-{{ $order['status'] }}">{{ str_replace('_', ' ', $order['status']) }}</span>
                                <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $order['payment_status'] }}</span>
                            </span>
                        </a>
                        <div class="row-tools">
                            <a class="button" href="{{ route('orders.show', $order['id']) }}">Ver</a>
                            @if ($order['status'] !== 'cancelled')
                                <a class="button" href="{{ route('orders.results', $order['id']) }}">Resultados</a>
                            @endif
                            <a class="button" href="{{ route('orders.pdf', $order['id']) }}">PDF</a>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">Aun no hay ordenes registradas.</p>
                @endforelse
            </div>
        </section>
    </main>
@endsection
