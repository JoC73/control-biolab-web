@extends('layouts.lab', ['title' => 'Laboratorio'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Laboratorio</p>
                <h1>Examenes por realizar</h1>
                <p>Pacientes con solicitud registrada para llenar o completar resultados.</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.index') }}">Ordenes</a>
                <a class="button primary" href="{{ route('orders.create') }}">Registrar cobro</a>
            </div>
        </header>

        <section class="summary-grid">
            <article><span>{{ $readyOrders->count() }}</span><p>Pagados listos para resultado</p></article>
            <article><span>{{ $partialOrders->count() }}</span><p>Con abono parcial</p></article>
            <article><span>{{ $unpaidOrders->count() }}</span><p>Pendientes de pago</p></article>
        </section>

        <section class="panel">
            <form class="filters" method="GET" action="{{ route('orders.lab') }}">
                <div class="field span-2">
                    <label for="q">Buscar paciente o examen</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Paciente, examen o referencia">
                </div>
                <div class="filter-actions">
                    <button class="button primary" type="submit">Buscar</button>
                    <a class="button" href="{{ route('orders.lab') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="lab-board">
            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Prioridad</p>
                        <h2>Pagados listos para resultados</h2>
                    </div>
                    <span class="count-pill">{{ $readyOrders->count() }}</span>
                </div>
                @include('orders.partials.lab-queue-list', ['orders' => $readyOrders, 'ready' => true])
            </section>

            <section class="panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Seguimiento</p>
                        <h2>Parciales y pendientes</h2>
                    </div>
                    <span class="count-pill">{{ $partialOrders->count() + $unpaidOrders->count() }}</span>
                </div>
                @include('orders.partials.lab-queue-list', ['orders' => $partialOrders->merge($unpaidOrders), 'ready' => false])
            </section>
        </section>
    </main>
@endsection
