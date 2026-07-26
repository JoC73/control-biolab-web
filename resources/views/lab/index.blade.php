@extends('layouts.lab', ['title' => 'Control BIOLAB'])

@section('body')
    <main class="app-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Control de resultados</p>
                <h1>{{ $business['name'] }}</h1>
                <p>{{ $business['address'] }} · Tel. {{ $business['phone'] }}</p>
            </div>
            <div class="top-actions">
                <a class="button primary" href="{{ route('orders.create') }}">Nueva orden</a>
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        <section class="summary-grid">
            <article>
                <span>Orden</span>
                <p>Paciente, examen, referencia y estado</p>
            </article>
            <article>
                <span>Caja</span>
                <p>Cobros, descuentos, egresos y anulaciones</p>
            </article>
            <article>
                <span>Entrega</span>
                <p>PDF, impresion y WhatsApp</p>
            </article>
        </section>

        @if ($recentResults->isNotEmpty())
            <section class="panel recent-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Actividad reciente</p>
                        <h2>Ultimos resultados guardados</h2>
                    </div>
                    <a class="button" href="{{ route('lab.history') }}">Ver historial</a>
                </div>

                <div class="history-list compact-list">
                    @foreach ($recentResults as $result)
                        <a class="history-row" href="{{ route('lab.results.show', $result['id']) }}">
                            <strong>{{ $result['patient_name'] }}</strong>
                            <span>{{ $result['category_name'] }} · {{ \Illuminate\Support\Carbon::parse($result['date'])->format('d/m/Y') }}</span>
                            <em>{{ $result['referred_by'] ?: 'Sin referido' }}</em>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @include('catalog.partials.exam-builder')

        <section class="panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Menu principal</p>
                    <h2>Selecciona el tipo de examen</h2>
                </div>
            </div>

            <div class="category-grid">
                @foreach ($categories as $category)
                    <a class="category-card" href="{{ route('lab.results.create', $category['slug']) }}">
                        <strong>{{ $category['name'] }}</strong>
                        <span>{{ count($category['tests']) ?: 'Pendiente' }} pruebas base</span>
                    </a>
                @endforeach
            </div>
        </section>
    </main>
@endsection
