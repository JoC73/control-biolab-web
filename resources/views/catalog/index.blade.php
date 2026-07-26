@extends('layouts.lab', ['title' => 'Catalogos'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Configuracion</p>
                <h1>Catalogos</h1>
                <p>Precios, medicos, instituciones y plantillas de examenes en una vista horizontal.</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('orders.create') }}">Nueva orden</a>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif

        <section class="catalog-board">
            <section class="panel catalog-strip">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Referencias</p>
                        <h2>Medicos e instituciones</h2>
                    </div>
                    <span class="count-pill">{{ count($referrers) }}</span>
                </div>

                <form class="catalog-inline-form" method="POST" action="{{ route('catalog.referrer') }}">
                    @csrf
                    <input name="name" placeholder="Nueva referencia medica o institucion" required>
                    <button class="button primary" type="submit">Agregar</button>
                </form>

                <input class="catalog-search" type="search" placeholder="Buscar referencia..." data-catalog-filter="referrer-list">

                <div class="reference-list" id="referrer-list" aria-label="Listado de medicos e instituciones">
                    @foreach ($referrers as $referrer)
                        <article class="reference-record" data-filter-item>
                            <div>
                                <strong>{{ $referrer }}</strong>
                                <span>{{ str_contains($referrer, 'CENTRO') || str_contains($referrer, 'FARMACIA') ? 'Institucion' : 'Medico' }}</span>
                            </div>
                            <span class="reference-type">{{ str_contains($referrer, 'CENTRO') || str_contains($referrer, 'FARMACIA') ? 'Inst.' : 'Med.' }}</span>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="panel catalog-strip catalog-strip-wide">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Precios</p>
                        <h2>Precio base por examen</h2>
                    </div>
                    <span class="count-pill">{{ count($categories) }}</span>
                </div>

                <input class="catalog-search" type="search" placeholder="Buscar examen..." data-catalog-filter="price-table">

                <div class="catalog-table" id="price-table">
                    <div class="catalog-table-head">Examen</div>
                    <div class="catalog-table-head">Tipo</div>
                    <div class="catalog-table-head">Pruebas</div>
                    <div class="catalog-table-head">Precio</div>
                    <div class="catalog-table-head">Accion</div>

                    @foreach ($categories as $category)
                        <form class="catalog-table-row" method="POST" action="{{ route('catalog.price') }}" data-filter-item>
                            @csrf
                            <div>
                                <strong>{{ $category['name'] }}</strong>
                                <span>{{ $category['title'] }}</span>
                            </div>
                            <div><span class="soft-badge">{{ ! empty($category['custom']) ? 'Personalizado' : 'Base' }}</span></div>
                            <div>{{ count($category['tests']) ?: 'Libre' }}</div>
                            <div>
                                <input type="hidden" name="slug" value="{{ $category['slug'] }}">
                                <input class="price-input" name="price" type="number" min="0" step="0.01" value="{{ $prices[$category['slug']] ?? 0 }}" aria-label="Precio base de {{ $category['name'] }}">
                            </div>
                            <div class="row-actions">
                                <button class="button compact-button" type="submit">Guardar</button>
                                @if (! empty($category['custom']))
                                    <button class="button danger-button compact-button" type="submit" form="delete-exam-{{ $category['slug'] }}">Eliminar</button>
                                @endif
                            </div>
                        </form>

                        @if (! empty($category['custom']))
                            <form id="delete-exam-{{ $category['slug'] }}" method="POST" action="{{ route('catalog.exam.delete', $category['slug']) }}" onsubmit="return confirm('Deseas eliminar este examen personalizado?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    @endforeach
                </div>
            </section>
        </section>

        <script>
            document.querySelectorAll('[data-catalog-filter]').forEach((input) => {
                const target = document.getElementById(input.dataset.catalogFilter);

                input.addEventListener('input', () => {
                    const term = input.value.trim().toLowerCase();

                    target.querySelectorAll('[data-filter-item]').forEach((item) => {
                        item.hidden = term !== '' && !item.textContent.toLowerCase().includes(term);
                    });
                });
            });
        </script>
    </main>
@endsection
