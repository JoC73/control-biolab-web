@extends('layouts.lab', ['title' => 'Caja'])

@section('body')
    @php $auth = app(\App\Services\AuthStore::class); @endphp

    <main class="app-shell cash-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Caja</p>
                <h1>Ingresos y egresos</h1>
                <p>Registra movimientos y revisa el historial sin salir del flujo de trabajo.</p>
            </div>
            <div class="top-actions">
                @if ($auth->hasPermission('orders.view'))
                    <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                @endif
                @if ($auth->hasPermission('catalogs.prices'))
                    <a class="button primary" href="{{ route('catalog.index') }}">Precios</a>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-errors">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="cash-dashboard">
            <article class="cash-metric-card cash-metric-balance">
                <span>Saldo del periodo</span>
                <strong>Q {{ number_format($totals['balance'], 2) }}</strong>
                <small>{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</small>
            </article>
            <article class="cash-metric-card">
                <span>Ingresos</span>
                <strong>Q {{ number_format($totals['income'], 2) }}</strong>
                <small>{{ $totals['income_count'] }} movimiento{{ $totals['income_count'] === 1 ? '' : 's' }}</small>
            </article>
            <article class="cash-metric-card cash-metric-expense">
                <span>Egresos</span>
                <strong>Q {{ number_format($totals['expense'], 2) }}</strong>
                <small>{{ $totals['expense_count'] }} movimiento{{ $totals['expense_count'] === 1 ? '' : 's' }}</small>
            </article>
            <article class="cash-metric-card">
                <span>Resumen mensual</span>
                <strong>Q {{ number_format($monthlyTotals['balance'], 2) }}</strong>
                <small>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthlyTotals['month'])->translatedFormat('F Y') }}</small>
            </article>
        </section>

        @if ($auth->hasPermission('cash.manage'))
            <section class="cash-workspace">
                <div class="panel cash-action-panel">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Movimiento nuevo</p>
                            <h2>Registrar en caja</h2>
                        </div>
                        <span class="soft-badge">Disponible hoy Q {{ number_format($dailyTotals['balance'], 2) }}</span>
                    </div>

                    <div class="cash-action-grid">
                        <form class="cash-quick-card income-entry-card" method="POST" action="{{ route('cash.store') }}">
                            @csrf
                            <input type="hidden" name="type" value="income">
                            <div class="cash-card-title">
                                <span>Ingreso</span>
                                <strong>Registrar ingreso</strong>
                            </div>
                            <div class="field"><label>Monto / cantidad</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('type') === 'income' ? old('amount') : '' }}" placeholder="0.00" required></div>
                            <div class="field"><label>Descripcion</label><input name="description" value="{{ old('type') === 'income' ? old('description') : '' }}" placeholder="Ej. ingreso manual de caja" required></div>
                            <div class="cash-inline-fields">
                                <div class="field"><label>Fecha</label><input name="date" type="date" value="{{ old('type') === 'income' ? old('date', $filters['date']) : $filters['date'] }}" required></div>
                                <div class="field"><label>Metodo</label><select name="method"><option value="efectivo" @selected(old('type') === 'income' && old('method')==='efectivo')>Efectivo</option><option value="transferencia" @selected(old('type') === 'income' && old('method')==='transferencia')>Transferencia</option><option value="tarjeta" @selected(old('type') === 'income' && old('method')==='tarjeta')>Tarjeta</option></select></div>
                            </div>
                            <button class="button primary" type="submit">Guardar ingreso</button>
                        </form>

                        <form class="cash-quick-card expense-entry-card" method="POST" action="{{ route('cash.store') }}">
                            @csrf
                            <input type="hidden" name="type" value="expense">
                            <div class="cash-card-title">
                                <span>Egreso</span>
                                <strong>Registrar egreso</strong>
                            </div>
                            <div class="field"><label>Monto / cantidad</label><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('type') === 'expense' ? old('amount') : '' }}" placeholder="0.00" required></div>
                            <div class="field"><label>Descripcion</label><input name="description" value="{{ old('type') === 'expense' ? old('description') : '' }}" placeholder="Ej. compra de reactivos o insumos" required></div>
                            <div class="cash-inline-fields">
                                <div class="field"><label>Fecha</label><input name="date" type="date" value="{{ old('type') === 'expense' ? old('date', $filters['date']) : $filters['date'] }}" required></div>
                                <div class="field"><label>Metodo</label><select name="method"><option value="efectivo" @selected(old('type') === 'expense' && old('method')==='efectivo')>Efectivo</option><option value="transferencia" @selected(old('type') === 'expense' && old('method')==='transferencia')>Transferencia</option><option value="tarjeta" @selected(old('type') === 'expense' && old('method')==='tarjeta')>Tarjeta</option></select></div>
                            </div>
                            <button class="button danger-button" type="submit">Guardar egreso</button>
                        </form>
                    </div>
                </div>

                <aside class="panel cash-month-panel">
                    <p class="eyebrow">Mes</p>
                    <h2>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthlyTotals['month'])->translatedFormat('F Y') }}</h2>
                    <form class="cash-month-form" method="GET" action="{{ route('cash.index') }}">
                        <input type="hidden" name="period" value="month">
                        <input type="hidden" name="date" value="{{ $filters['date'] }}">
                        @if ($filters['type'])
                            <input type="hidden" name="type" value="{{ $filters['type'] }}">
                        @endif
                        <label for="month">Cambiar mes</label>
                        <input id="month" name="month" type="month" value="{{ $filters['month'] }}">
                        <button class="button primary compact-button" type="submit">Ver</button>
                    </form>
                    <div class="cash-month-list">
                        <span>Ingresos <strong>Q {{ number_format($monthlyTotals['income'], 2) }}</strong></span>
                        <span>Egresos <strong>Q {{ number_format($monthlyTotals['expense'], 2) }}</strong></span>
                        <span>Anulados <strong>{{ $monthlyTotals['voided_count'] }}</strong></span>
                    </div>
                </aside>
            </section>
        @endif

        <section class="panel cash-history-panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Historial</p>
                    <h2>Detalle de caja</h2>
                </div>
                <span class="count-pill">{{ count($movements) }}</span>
            </div>

            <form class="cash-filter-bar" method="GET" action="{{ route('cash.index') }}">
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

            <div class="cash-movement-list">
                @forelse ($movements as $movement)
                    <article class="cash-movement-item {{ $movement['status'] === 'voided' ? 'muted-row' : '' }}">
                        <div class="cash-movement-main">
                            <span class="cash-type-pill {{ $movement['type'] === 'income' ? 'income-pill' : 'expense-pill' }}">{{ $movement['type'] === 'income' ? 'Ingreso' : 'Egreso' }}</span>
                            <div>
                                <strong>{{ $movement['description'] }}</strong>
                                <span>{{ \Illuminate\Support\Carbon::parse($movement['date'])->format('d/m/Y') }} · {{ ucfirst($movement['method']) }} · Registrado por {{ $movement['created_by'] ?: 'Sistema' }}</span>
                                @if (! empty($movement['order_id']))
                                    <span>Orden {{ $movement['order_id'] }}</span>
                                @endif
                                @if ($movement['status'] !== 'active')
                                    <em>{{ $movement['void_reason'] }}</em>
                                @endif
                            </div>
                        </div>
                        <div class="cash-movement-side">
                            <strong>Q {{ number_format($movement['amount'], 2) }}</strong>
                            <span>{{ $movement['status'] === 'voided' ? 'Anulado' : 'Activo' }}</span>
                            @if ($movement['status'] === 'active' && $auth->hasPermission('cash.manage'))
                                <form class="void-form compact-void-form" method="POST" action="{{ route('cash.void', $movement['id']) }}" onsubmit="return confirm('Deseas anular este movimiento?')">
                                    @csrf
                                    <input name="reason" placeholder="Motivo" required>
                                    <button class="button danger-button compact-button" type="submit">Anular</button>
                                </form>
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
