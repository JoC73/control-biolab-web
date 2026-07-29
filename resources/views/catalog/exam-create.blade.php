@extends('layouts.lab', ['title' => 'Crear examen personalizado'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Plantilla nueva</p>
                <h1>Crear examen personalizado</h1>
                <p>Configura el nombre, precio y campos que apareceran al emitir resultados.</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('lab.index') }}">Menu principal</a>
                <a class="button" href="{{ route('catalog.index') }}">Catalogos</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="status-message wide error-message">{{ $errors->first() }}</div>
        @endif

        @include('catalog.partials.exam-builder')
    </main>
@endsection
