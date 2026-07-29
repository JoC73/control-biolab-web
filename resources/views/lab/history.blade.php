@extends('layouts.lab', ['title' => 'Historial de resultados'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Historial</p>
                <h1>Resultados guardados</h1>
                <p>{{ $business['name'] }}</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('lab.index') }}">Menu</a>
                <a class="button primary" href="{{ route('lab.results.create', config('lab.categories.0.slug')) }}">Nuevo resultado</a>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        <section class="panel">
            <form class="filters" method="GET" action="{{ route('lab.history') }}">
                <div class="field">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Paciente, categoria o referido">
                </div>
                <div class="field">
                    <label for="date_from">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="field">
                    <label for="date_to">Hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="filter-actions">
                    <button class="button primary" type="submit">Buscar</button>
                    <a class="button" href="{{ route('lab.history') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Archivo</p>
                    <h2>{{ $results->count() }} resultado{{ $results->count() === 1 ? '' : 's' }}</h2>
                </div>
            </div>

            <div class="history-list">
                @forelse ($results as $result)
                    @php
                        $examItems = $result['exam_items'] ?? [];
                        $examNames = collect($examItems)->pluck('category_name')->filter()->implode(', ');
                        $balance = $result['order_total'] !== null ? max(0, (float) $result['order_total'] - (float) ($result['paid_amount'] ?? 0)) : null;
                    @endphp
                    <article class="history-row">
                        <a href="{{ route('lab.results.show', $result['id']) }}">
                            <strong>{{ $result['patient_name'] }}</strong>
                            <span>{{ count($examItems) > 1 ? count($examItems).' examenes' : $result['category_name'] }} · {{ \Illuminate\Support\Carbon::parse($result['date'])->format('d/m/Y') }}</span>
                            @if ($examNames)
                                <small>{{ $examNames }}</small>
                            @endif
                            <em>{{ $result['referred_by'] ?: 'Sin referido' }}</em>
                            @if ($balance !== null)
                                <span class="badge-line">
                                    <span class="soft-badge">Total Q {{ number_format((float) $result['order_total'], 2) }}</span>
                                    <span class="soft-badge">Pagado Q {{ number_format((float) ($result['paid_amount'] ?? 0), 2) }}</span>
                                    <span class="soft-badge">Saldo Q {{ number_format($balance, 2) }}</span>
                                </span>
                            @endif
                        </a>
                        <div class="row-tools">
                            <a class="button" href="{{ route('lab.results.show', $result['id']) }}">Ver</a>
                            <a class="button" href="{{ route('lab.results.edit', $result['id']) }}">Editar</a>
                            <a class="button" href="{{ route('lab.results.saved-pdf', $result['id']) }}">PDF</a>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">Aun no hay resultados guardados.</p>
                @endforelse
            </div>
        </section>
    </main>
@endsection
