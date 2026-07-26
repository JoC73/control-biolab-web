@extends('layouts.lab', ['title' => 'Auditoria'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Seguridad</p>
                <h1>Auditoria del sistema</h1>
                <p>Ultimas acciones registradas por usuario, modulo y fecha.</p>
            </div>
        </header>

        <section class="panel">
            <form class="filters" method="GET" action="{{ route('audit.index') }}">
                <div class="field">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Usuario, modulo o referencia">
                </div>
                <div class="field">
                    <label for="action">Accion</label>
                    <select id="action" name="action">
                        <option value="">Todas</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="button primary" type="submit">Filtrar</button>
                    <a class="button" href="{{ route('audit.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="cash-table audit-table">
                <div class="cash-table-head">Fecha</div>
                <div class="cash-table-head">Usuario</div>
                <div class="cash-table-head">Accion</div>
                <div class="cash-table-head">Modulo</div>
                <div class="cash-table-head">Referencia</div>

                @forelse ($records as $record)
                    <article class="cash-row">
                        <div>{{ $record['created_at'] }}</div>
                        <div>
                            <strong>{{ $record['user_name'] }}</strong>
                            <span>{{ $record['user_role'] ?: 'sistema' }}</span>
                        </div>
                        <div><span class="soft-badge">{{ $record['action'] }}</span></div>
                        <div>{{ $record['subject_type'] }}</div>
                        <div>{{ $record['subject_id'] ?: '-' }}</div>
                    </article>
                @empty
                    <p class="empty-state">Aun no hay eventos registrados.</p>
                @endforelse
            </div>
        </section>
    </main>
@endsection
