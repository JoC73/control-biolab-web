<div class="history-list compact-list">
    @forelse ($orders as $order)
        @php
            $balance = max(0, (float) $order['total'] - (float) $order['paid_amount']);
            $paymentLabels = ['unpaid' => 'Sin pago', 'partial' => 'Parcial', 'paid' => 'Pagado'];
            $examItems = $order['exam_items'] ?? [];
            $pendingExams = collect($examItems)->where('status', '!=', 'ready')->count();
            $examNames = collect($examItems)->pluck('name')->filter()->take(3)->implode(', ');
        @endphp
        <article class="history-row lab-queue-row">
            <a href="{{ route('orders.show', $order['id']) }}">
                <strong>{{ $order['patient_name'] }}</strong>
                <span>
                    {{ count($examItems) > 1 ? count($examItems).' examenes' : $order['category_name'] }}
                    @if (count($examItems) > 1 && $examNames)
                        · {{ $examNames }}{{ count($examItems) > 3 ? '...' : '' }}
                    @endif
                    · {{ \Illuminate\Support\Carbon::parse($order['date'])->format('d/m/Y') }}
                </span>
                <em>{{ $order['referrer'] ?: 'Sin referencia' }}</em>
                <span class="badge-line">
                    <span class="status-badge pay-{{ $order['payment_status'] }}">{{ $paymentLabels[$order['payment_status']] ?? $order['payment_status'] }}</span>
                    <span class="soft-badge">Saldo Q {{ number_format($balance, 2) }}</span>
                    <span class="soft-badge">{{ $pendingExams }} pendiente{{ $pendingExams === 1 ? '' : 's' }}</span>
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
