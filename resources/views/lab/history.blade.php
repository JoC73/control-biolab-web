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
                    <article class="history-row">
                        <a href="{{ route('lab.results.show', $result['id']) }}">
                            <strong>{{ $result['patient_name'] }}</strong>
                            <span>{{ $result['category_name'] }} · {{ \Illuminate\Support\Carbon::parse($result['date'])->format('d/m/Y') }}</span>
                            <em>{{ $result['referred_by'] ?: 'Sin referido' }}</em>
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
