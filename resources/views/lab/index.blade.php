@extends('layouts.lab', ['title' => 'Control BIOLAB'])

@section('body')
    @php $auth = app(\App\Services\AuthStore::class); @endphp

    <main class="app-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Control de resultados</p>
                <h1>{{ $business['name'] }}</h1>
                <p>{{ $business['address'] }} · Tel. {{ $business['phone'] }}</p>
            </div>
            <div class="top-actions">
                @if ($auth->hasPermission('orders.create'))
                    <a class="button primary" href="{{ route('orders.create') }}">Registrar cobro</a>
                @endif
                @if ($auth->hasPermission('laboratory.view'))
                    <a class="button" href="{{ route('orders.lab') }}">Laboratorio</a>
                @endif
                @if ($auth->hasPermission('orders.view'))
                    <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        <section class="summary-grid">
            @if ($auth->hasPermission('orders.create'))
                <article>
                    <span>Cobro</span>
                    <p>Paciente, examen, precio, descuento y pago</p>
                </article>
            @endif
            @if ($auth->hasPermission('laboratory.view'))
                <article>
                    <span>Laboratorio</span>
                    <p>Pacientes listos para llenar resultados</p>
                </article>
            @endif
            @if ($auth->hasPermission('orders.deliver') || $auth->hasPermission('results.print'))
                <article>
                    <span>Entrega</span>
                    <p>PDF, impresion y WhatsApp</p>
                </article>
            @endif
            @if ($auth->hasPermission('catalogs.prices'))
                <article>
                    <span>Precios</span>
                    <p>Actualizacion de precios base</p>
                </article>
            @endif
        </section>

        @if ($recentResults->isNotEmpty())
            <section class="panel recent-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Actividad reciente</p>
                        <h2>Ultimos resultados guardados</h2>
                    </div>
                    @if ($auth->hasPermission('results.view'))
                        <a class="button" href="{{ route('lab.history') }}">Ver historial</a>
                    @endif
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

        @if ($auth->hasPermission('results.create') || $auth->hasPermission('catalogs.manage'))
            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Menu principal</p>
                        <h2>Selecciona el tipo de examen</h2>
                    </div>
                </div>

                <div class="category-grid">
                    @if ($auth->hasPermission('catalogs.manage'))
                        <a class="category-card category-create" href="{{ route('catalog.exam.create') }}">
                            <strong>Crear examen personalizado</strong>
                        </a>
                    @endif
                    @foreach ($categories as $category)
                        <a class="category-card category-{{ $category['slug'] }}" href="{{ route('lab.results.create', $category['slug']) }}">
                            <strong>{{ $category['name'] }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
@endsection
