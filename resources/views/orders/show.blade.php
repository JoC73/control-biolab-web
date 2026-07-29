@extends('layouts.lab', ['title' => 'Orden - '.$order['patient_name']])

@section('body')
    @php
        $balance = max(0, round((float) $order['total'] - (float) $order['paid_amount'], 2));
        $statusLabels = ['pending_results' => 'Pendiente resultado', 'ready' => 'Listo', 'delivered' => 'Entregado', 'cancelled' => 'Anulado'];
        $paymentLabels = ['unpaid' => 'Sin pago', 'partial' => 'Parcial', 'paid' => 'Pagado'];
        $pendingExams = collect($examItems)->where('status', '!=', 'ready')->count();
        $canDeliver = ($order['status'] ?? null) === 'ready' && ($order['payment_status'] ?? null) === 'paid' && $balance <= 0 && $pendingExams === 0;
    @endphp

    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Orden {{ substr($order['id'], 0, 8) }}</p>
                <h1>{{ $order['patient_name'] }}</h1>
                <p>
                    {{ $orderTitle }}
                    <span class="status-badge status-{{ $order['status'] }}">{{ $statusLabels[$order['status']] ?? $order['status'] }}</span>
                    <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $paymentLabels[$order['payment_status']] ?? $order['payment_status'] }}</span>
                </p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                @if ($order['status'] !== 'cancelled')
                    <a class="button" href="{{ route('orders.results', $order['id']) }}">Resultados</a>
                @endif
                <a class="button" href="{{ route('orders.pdf', $order['id']) }}">PDF</a>
                @if ($whatsappUrl)
                    <a class="button" target="_blank" href="{{ $whatsappUrl }}">WhatsApp</a>
                @endif
                <a class="button primary" target="_blank" href="{{ route('orders.print', $order['id']) }}">Imprimir</a>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="status-message wide error-message">{{ $errors->first() }}</div>
        @endif

        <section class="summary-grid">
            <article><span>Q {{ number_format($order['total'], 2) }}</span><p>Total</p></article>
            <article><span>Q {{ number_format($order['paid_amount'], 2) }}</span><p>Pagado</p></article>
            <article><span>Q {{ number_format($balance, 2) }}</span><p>Saldo</p></article>
            <article><span>{{ count($examItems) }}</span><p>Examenes</p></article>
        </section>

        <section class="panel form-panel">
            <div><strong>Edad</strong><p>{{ $order['age'] ?: 'No indicada' }}</p></div>
            <div><strong>WhatsApp</strong><p>{{ $order['phone'] ?: 'No indicado' }}</p></div>
            <div><strong>Referencia</strong><p>{{ $order['referrer'] ?: 'Sin referencia' }}</p></div>
            <div><strong>Fecha</strong><p>{{ \Illuminate\Support\Carbon::parse($order['date'])->format('d/m/Y') }}</p></div>
        </section>

        <section class="panel two-column">
            <div>
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Cobro</p>
                        <h2>{{ $balance > 0 ? 'Registrar abono' : 'Pago completo' }}</h2>
                        <p>Saldo pendiente: Q {{ number_format($balance, 2) }}</p>
                    </div>
                </div>
                @if ($balance > 0)
                    <form class="inline-form" method="POST" action="{{ route('orders.pay', $order['id']) }}">
                        @csrf
                        <input name="amount" type="hidden" value="{{ number_format($balance, 2, '.', '') }}">
                        <input type="text" value="Q {{ number_format($balance, 2) }}" readonly aria-label="Saldo pendiente a cobrar">
                        <select name="method">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                        <button class="button primary" type="submit" @disabled($order['status'] === 'cancelled')>Cobrar saldo</button>
                    </form>
                @else
                    <p class="empty-state">Esta orden ya no tiene saldo pendiente.</p>
                @endif
            </div>
            <div>
                <div class="section-heading">
                    <div><p class="eyebrow">Control</p><h2>Estado</h2></div>
                </div>
                <div class="row-tools">
                    <form method="POST" action="{{ route('orders.deliver', $order['id']) }}">
                        @csrf
                        <button class="button" type="submit" @disabled(! $canDeliver)>Marcar entregada</button>
                    </form>
                    @if (! in_array($order['status'] ?? null, ['cancelled', 'delivered'], true) && ! $canDeliver)
                        <p class="empty-state">
                            @if ($balance > 0)
                                No se puede entregar: saldo pendiente Q {{ number_format($balance, 2) }}.
                            @elseif ($pendingExams > 0)
                                No puede entregarse: existen examenes pendientes.
                            @endif
                        </p>
                    @endif
                    @if ($order['status'] !== 'cancelled')
                        <form method="POST" action="{{ route('orders.cancel', $order['id']) }}" onsubmit="return confirm('Deseas anular esta orden? Se registrara reverso en caja si tiene pagos.')">
                            @csrf
                            <input name="reason" placeholder="Motivo de anulacion" required>
                            <button class="button danger-button" type="submit">Anular</button>
                        </form>
                    @else
                        <p class="void-reason">Anulada: {{ $order['cancel_reason'] }}</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="section-heading">
                <div><p class="eyebrow">Examenes</p><h2>Resultados de la orden</h2></div>
            </div>
            <div class="history-list compact-list">
                @foreach ($examItems as $index => $item)
                    <article class="history-row">
                        <a href="{{ route('orders.results', ['id' => $order['id'], 'exam' => $index]) }}">
                            <strong>{{ $item['category_name'] }}</strong>
                            <span>Q {{ number_format((float) $item['price'], 2) }} · {{ count($item['tests'] ?? []) }} campo{{ count($item['tests'] ?? []) === 1 ? '' : 's' }}</span>
                            <em>{{ ($item['status'] ?? 'pending') === 'ready' ? 'Listo' : 'Pendiente' }}</em>
                        </a>
                        <div class="row-tools">
                            @if ($order['status'] !== 'cancelled')
                                <a class="button" href="{{ route('orders.results', ['id' => $order['id'], 'exam' => $index]) }}">Resultados</a>
                            @endif
                            @if (($item['status'] ?? 'pending') === 'ready' && ($order['payment_status'] ?? null) === 'paid')
                                <a class="button" href="{{ route('orders.pdf.exam', ['id' => $order['id'], 'exam' => $index]) }}">PDF individual</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
@endsection
