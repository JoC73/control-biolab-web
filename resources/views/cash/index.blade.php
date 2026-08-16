@extends('layouts.lab', ['title' => 'Caja'])

@section('body')
    @php $auth = app(\App\Services\AuthStore::class); @endphp

    <main class="app-shell">
        <header class="topbar compact">
            <div><p class="eyebrow">Caja</p><h1>Ingresos y egresos</h1></div>
            <div class="top-actions">
                @if ($auth->hasPermission('orders.view'))
                    <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                @endif
                @if ($auth->hasPermission('catalogs.prices'))
                    <a class="button primary" href="{{ route('catalog.index') }}">Precios</a>
                @endif
            </div>
        </header>
        @if (session('status'))<div class="status-message wide">{{ session('status') }}</div>@endif

        <section class="panel monthly-cash-panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Resumen mensual</p>
                    <h2>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthlyTotals['month'])->translatedFormat('F Y') }}</h2>
                </div>
                <form class="month-filter" method="GET" action="{{ route('cash.index') }}">
                    <input type="hidden" name="date" value="{{ $filters['date'] }}">
                    @if ($filters['type'])
                        <input type="hidden" name="type" value="{{ $filters['type'] }}">
                    @endif
                    <label for="month">Mes</label>
                    <input id="month" name="month" type="month" value="{{ $filters['month'] }}">
                    <button class="button primary compact-button" type="submit">Ver mes</button>
                </form>
            </div>
            <div class="summary-grid monthly-summary-grid">
                <article><span>Q {{ number_format($monthlyTotals['income'], 2) }}</span><p>Ingresos del mes · {{ $monthlyTotals['income_count'] }} mov.</p></article>
                <article><span>Q {{ number_format($monthlyTotals['expense'], 2) }}</span><p>Egresos del mes · {{ $monthlyTotals['expense_count'] }} mov.</p></article>
                <article><span>Q {{ number_format($monthlyTotals['balance'], 2) }}</span><p>Saldo neto mensual</p></article>
                <article><span>{{ $monthlyTotals['voided_count'] }}</span><p>Movimientos anulados</p></article>
            </div>
        </section>

        <section class="summary-grid">
            <article><span>Q {{ number_format($totals['income'], 2) }}</span><p>Ingresos validos del periodo</p></article>
            <article><span>Q {{ number_format($totals['expense'], 2) }}</span><p>Egresos validos del periodo</p></article>
            <article><span>Q {{ number_format($totals['balance'], 2) }}</span><p>Saldo del periodo</p></article>
        </section>
        <section class="panel">
            <form class="filters" method="GET" action="{{ route('cash.index') }}">
                <div class="field">
                    <label>Periodo</label>
                    <select name="period">
                        <option value="day" @selected($filters['period']==='day')>Dia</option>
                        <option value="week" @selected($filters['period']==='week')>Semana</option>
                        <option value="month" @selected($filters['period']==='month')>Mes</option>
                        <option value="year" @selected($filters['period']==='year')>Año</option>
                    </select>
                </div>
                <div class="field"><label>Fecha base</label><input name="date" type="date" value="{{ $filters['date'] }}"></div>
                <div class="field"><label>Mes</label><input name="month" type="month" value="{{ $filters['month'] }}"></div>
                <div class="field"><label>Tipo</label><select name="type"><option value="">Todos</option><option value="income" @selected($filters['type']==='income')>Ingreso</option><option value="expense" @selected($filters['type']==='expense')>Egreso</option></select></div>
                <div class="filter-actions"><button class="button primary" type="submit">Filtrar</button><a class="button" href="{{ route('cash.index') }}">Hoy</a></div>
            </form>
            <p class="period-note">Mostrando movimientos del {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}.</p>
        </section>
        @if ($auth->hasPermission('cash.manage'))
            <section class="panel cash-entry-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Nuevo movimiento</p>
                        <h2>Registrar ingreso o egreso</h2>
                        <p>El sistema usa la fecha indicada y bloquea egresos mayores al saldo disponible de ese dia.</p>
                    </div>
                    <span class="soft-badge">Disponible del dia Q {{ number_format($dailyTotals['balance'], 2) }}</span>
                </div>
                @if ($errors->any())
                    <div class="form-errors">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <form class="cash-entry-form" method="POST" action="{{ route('cash.store') }}">
                    @csrf
                    <div class="field"><label>Tipo</label><select name="type"><option value="income" @selected(old('type')==='income')>Ingreso</option><option value="expense" @selected(old('type')==='expense')>Egreso</option></select></div>
                    <div class="field"><label>Fecha</label><input name="date" type="date" value="{{ old('date', $filters['date']) }}" required></div>
                    <div class="field"><label>Monto / cantidad</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0.00" required></div>
                    <div class="field"><label>Metodo</label><select name="method"><option value="efectivo" @selected(old('method')==='efectivo')>Efectivo</option><option value="transferencia" @selected(old('method')==='transferencia')>Transferencia</option><option value="tarjeta" @selected(old('method')==='tarjeta')>Tarjeta</option></select></div>
                    <div class="field span-3"><label>Descripcion</label><input name="description" value="{{ old('description') }}" placeholder="Ej. compra de insumos, pago de servicio, ajuste de caja" required></div>
                    <div class="filter-actions"><button class="button primary" type="submit">Guardar movimiento</button></div>
                </form>
            </section>
        @endif
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
                            @if ($movement['status'] === 'active' && $auth->hasPermission('cash.manage'))
                                <form class="void-form" method="POST" action="{{ route('cash.void', $movement['id']) }}" onsubmit="return confirm('Deseas anular este movimiento?')">
                                    @csrf
                                    <input name="reason" placeholder="Motivo" required>
                                    <button class="button danger-button compact-button" type="submit">Anular</button>
                                </form>
                            @elseif ($movement['status'] !== 'active')
                                <span class="void-reason">{{ $movement['void_reason'] }}</span>
                            @else
                                <span class="soft-badge">Sin accion</span>
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
