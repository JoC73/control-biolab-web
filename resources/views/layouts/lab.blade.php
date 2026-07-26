<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Control BIOLAB' }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}">
</head>
<body>
    <div class="app-frame">
        <aside class="sidebar">
            <a class="brand-block" href="{{ route('lab.index') }}">
                @include('components.biolab-logo', ['class' => 'brand-logo'])
                <span>
                    <strong>BIOLAB</strong>
                    <small>Control laboratorio</small>
                </span>
            </a>

            <nav class="side-nav" aria-label="Navegacion principal">
                <a class="{{ request()->routeIs('lab.index') ? 'active' : '' }}" href="{{ route('lab.index') }}">Inicio</a>
                <a class="{{ request()->routeIs('orders.create') ? 'active' : '' }}" href="{{ route('orders.create') }}">Nueva orden</a>
                <a class="{{ request()->routeIs('orders.*') && ! request()->routeIs('orders.create') ? 'active' : '' }}" href="{{ route('orders.index') }}">Ordenes</a>
                <a class="{{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}">Caja</a>
                <a class="{{ request()->routeIs('catalog.*') ? 'active' : '' }}" href="{{ route('catalog.index') }}">Catalogos</a>
                <a class="{{ request()->routeIs('lab.history') || request()->routeIs('lab.results.*') ? 'active' : '' }}" href="{{ route('lab.history') }}">Historial</a>
                @if (session('biolab_user.role') === 'admin')
                    <a class="{{ request()->routeIs('audit.*') ? 'active' : '' }}" href="{{ route('audit.index') }}">Auditoria</a>
                @endif
            </nav>

            <div class="sidebar-footer">
                @if (session('biolab_user'))
                    <span>{{ session('biolab_user.name') }}</span>
                    <strong>{{ ucfirst(session('biolab_user.role')) }}</strong>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button compact-button" type="submit">Salir</button>
                    </form>
                @endif
                <span>Local / Produccion</span>
                <strong>{{ now()->format('d/m/Y') }}</strong>
            </div>
        </aside>

        <div class="app-content">
            <header class="mobile-bar">
                <a class="brand-block compact-brand" href="{{ route('lab.index') }}">
                    @include('components.biolab-logo', ['class' => 'brand-logo'])
                    <span>
                        <strong>BIOLAB</strong>
                        <small>Control</small>
                    </span>
                </a>
                <nav class="mobile-nav" aria-label="Navegacion movil">
                    <a href="{{ route('lab.index') }}">Inicio</a>
                    <a href="{{ route('orders.create') }}">Nueva</a>
                    <a href="{{ route('orders.index') }}">Ordenes</a>
                    <a href="{{ route('cash.index') }}">Caja</a>
                </nav>
            </header>

            @yield('body')
        </div>
    </div>
</body>
</html>
