@extends('layouts.lab', ['title' => 'Caja'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div><p class="eyebrow">Caja</p><h1>Ingresos y egresos</h1></div>
            <div class="top-actions"><a class="button" href="{{ route('orders.index') }}">Ordenes</a></div>
        </header>
        @if (session('status'))<div class="status-message wide">{{ session('status') }}</div>@endif
        <section class="summary-grid">
            <article><span>Q {{ number_format($totals['income'], 2) }}</span><p>Ingresos validos</p></article>
            <article><span>Q {{ number_format($totals['expense'], 2) }}</span><p>Egresos validos</p></article>
            <article><span>Q {{ number_format($totals['balance'], 2) }}</span><p>Saldo del dia</p></article>
        </section>
        <section class="panel">
            <form class="filters" method="GET" action="{{ route('cash.index') }}">
                <div class="field"><label>Fecha</label><input name="date" type="date" value="{{ $filters['date'] }}"></div>
                <div class="field"><label>Tipo</label><select name="type"><option value="">Todos</option><option value="income" @selected($filters['type']==='income')>Ingreso</option><option value="expense" @selected($filters['type']==='expense')>Egreso</option></select></div>
                <div class="filter-actions"><button class="button primary" type="submit">Filtrar</button><a class="button" href="{{ route('cash.index') }}">Hoy</a></div>
            </form>
        </section>
        <section class="panel">
            <div class="section-heading"><div><p class="eyebrow">Nuevo movimiento</p><h2>Registrar ingreso o egreso</h2></div></div>
            <form class="filters" method="POST" action="{{ route('cash.store') }}">
                @csrf
                <div class="field"><label>Tipo</label><select name="type"><option value="income">Ingreso</option><option value="expense">Egreso</option></select></div>
                <div class="field"><label>Fecha</label><input name="date" type="date" value="{{ $filters['date'] }}" required></div>
                <div class="field"><label>Monto</label><input name="amount" type="number" min="0.01" step="0.01" required></div>
                <div class="field"><label>Metodo</label><select name="method"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option></select></div>
                <div class="field span-2"><label>Descripcion</label><input name="description" required></div>
                <div class="filter-actions"><button class="button primary" type="submit">Guardar</button></div>
            </form>
        </section>
        <section class="panel cash-ledger-panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Movimientos</p>
                    <h2>Detalle de caja</h2>
                </div>
                <span class="count-pill">{{ count($movements) }}</span>
            </div>

            <div class="cash-table">
                <div class="cash-table-head">Fecha</div>
                <div class="cash-table-head">Tipo</div>
                <div class="cash-table-head">Descripcion</div>
                <div class="cash-table-head">Metodo</div>
                <div class="cash-table-head">Monto</div>
                <div class="cash-table-head">Estado</div>
                <div class="cash-table-head">Accion</div>

                @forelse ($movements as $movement)
                    <article class="cash-row {{ $movement['status'] === 'voided' ? 'muted-row' : '' }}">
                        <div>{{ $movement['date'] }}</div>
                        <div><span class="soft-badge">{{ $movement['type'] === 'income' ? 'Ingreso' : 'Egreso' }}</span></div>
                        <div>
                            <strong>{{ $movement['description'] }}</strong>
                            @if (! empty($movement['order_id']))
                                <span>Orden {{ $movement['order_id'] }}</span>
                            @endif
                        </div>
                        <div>{{ ucfirst($movement['method']) }}</div>
                        <div class="amount-cell">Q {{ number_format($movement['amount'], 2) }}</div>
                        <div>{{ $movement['status'] === 'voided' ? 'Anulado' : 'Activo' }}</div>
                        <div>
                            @if ($movement['status'] === 'active')
                                <form class="void-form" method="POST" action="{{ route('cash.void', $movement['id']) }}" onsubmit="return confirm('Deseas anular este movimiento?')">
                                    @csrf
                                    <input name="reason" placeholder="Motivo" required>
                                    <button class="button danger-button compact-button" type="submit">Anular</button>
                                </form>
                            @else
                                <span class="void-reason">{{ $movement['void_reason'] }}</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No hay movimientos para este filtro.</p>
                @endforelse
            </div>
        </section>
    </main>
@endsection
