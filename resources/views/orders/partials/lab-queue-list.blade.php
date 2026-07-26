<div class="history-list compact-list">
    @forelse ($orders as $order)
        @php
            $balance = max(0, (float) $order['total'] - (float) $order['paid_amount']);
        @endphp
        <article class="history-row lab-queue-row">
            <a href="{{ route('orders.show', $order['id']) }}">
                <strong>{{ $order['patient_name'] }}</strong>
                <span>{{ $order['category_name'] }} · {{ \Illuminate\Support\Carbon::parse($order['date'])->format('d/m/Y') }}</span>
                <em>{{ $order['referrer'] ?: 'Sin referencia' }}</em>
                <span class="badge-line">
                    <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $order['payment_status'] }}</span>
                    <span class="soft-badge">Saldo Q {{ number_format($balance, 2) }}</span>
                </span>
            </a>
            <div class="row-tools">
                @if ($ready)
                    <a class="button primary" href="{{ route('orders.results', $order['id']) }}">Llenar resultados</a>
                @else
                    <a class="button" href="{{ route('orders.show', $order['id']) }}">Ver cobro</a>
                @endif
            </div>
        </article>
    @empty
        <p class="empty-state">No hay pacientes en esta lista.</p>
    @endforelse
</div>
