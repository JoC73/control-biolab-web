@extends('layouts.lab', ['title' => 'Orden - '.$order['patient_name']])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Orden {{ substr($order['id'], 0, 8) }}</p>
                <h1>{{ $order['patient_name'] }}</h1>
                <p>
                    {{ $order['category_name'] }}
                    <span class="status-badge status-{{ $order['status'] }}">{{ str_replace('_', ' ', $order['status']) }}</span>
                    <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $order['payment_status'] }}</span>
                </p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                @if ($order['status'] !== 'cancelled')
                    <a class="button" href="{{ route('orders.results', $order['id']) }}">Resultados</a>
                @endif
                <a class="button" href="{{ route('orders.pdf', $order['id']) }}">PDF</a>
                @if (!empty($order['phone']))
                    <a class="button" target="_blank" href="{{ $whatsappUrl }}">WhatsApp</a>
                @endif
                <button class="button primary" onclick="window.print()">Imprimir</button>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        <section class="summary-grid">
            <article><span>Q {{ number_format($order['total'], 2) }}</span><p>Total</p></article>
            <article><span>Q {{ number_format($order['paid_amount'], 2) }}</span><p>Pagado</p></article>
            <article><span>Q {{ number_format(max(0, $order['total'] - $order['paid_amount']), 2) }}</span><p>Saldo</p></article>
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
                    <div><p class="eyebrow">Cobro</p><h2>Registrar abono</h2></div>
                </div>
                <form class="inline-form" method="POST" action="{{ route('orders.pay', $order['id']) }}">
                    @csrf
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Monto" required>
                    <select name="method">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                    <button class="button primary" type="submit" @disabled($order['status'] === 'cancelled')>Cobrar</button>
                </form>
            </div>
            <div>
                <div class="section-heading">
                    <div><p class="eyebrow">Control</p><h2>Estado</h2></div>
                </div>
                <div class="row-tools">
                    <form method="POST" action="{{ route('orders.deliver', $order['id']) }}">
                        @csrf
                        <button class="button" type="submit" @disabled($order['status'] === 'cancelled')>Marcar entregada</button>
                    </form>
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
                <div><p class="eyebrow">Resultado</p><h2>{{ $order['category_title'] }}</h2></div>
            </div>
            <table class="print-table">
                <thead><tr><th>Analisis</th><th>Resultado</th><th>Unidades</th><th>V.N.</th></tr></thead>
                <tbody>
                    @forelse ($order['tests'] as $index => $test)
                        <tr>
                            <td>{{ $test['name'] }}</td>
                            <td>{{ $order['results'][$index] ?? '' }}</td>
                            <td>{{ $test['unit'] }}</td>
                            <td>{{ $test['reference'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Resultados pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
@endsection
