<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Control BIOLAB' }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}">
</head>
<body>
    @php $auth = app(\App\Services\AuthStore::class); @endphp
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
                @if ($auth->hasPermission('orders.create'))
                    <a class="{{ request()->routeIs('orders.create') ? 'active' : '' }}" href="{{ route('orders.create') }}">Registrar cobro</a>
                @endif
                @if ($auth->hasPermission('laboratory.view'))
                    <a class="{{ request()->routeIs('orders.lab') || request()->routeIs('orders.results') ? 'active' : '' }}" href="{{ route('orders.lab') }}">Laboratorio</a>
                @endif
                @if ($auth->hasPermission('orders.view'))
                    <a class="{{ request()->routeIs('orders.*') && ! request()->routeIs('orders.create') && ! request()->routeIs('orders.lab') && ! request()->routeIs('orders.results') ? 'active' : '' }}" href="{{ route('orders.index') }}">Ordenes</a>
                @endif
                @if ($auth->hasPermission('cash.view'))
                    <a class="{{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}">Caja</a>
                @endif
                @if ($auth->hasPermission('catalogs.view'))
                    <a class="{{ request()->routeIs('catalog.*') ? 'active' : '' }}" href="{{ route('catalog.index') }}">Catalogos</a>
                @endif
                @if ($auth->hasPermission('results.view'))
                    <a class="{{ request()->routeIs('lab.history') || request()->routeIs('lab.results.*') ? 'active' : '' }}" href="{{ route('lab.history') }}">Historial</a>
                @endif
                @if ($auth->hasPermission('users.view'))
                    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Usuarios</a>
                @endif
                @if ($auth->hasPermission('audit.view'))
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
                    @if ($auth->hasPermission('orders.create'))
                        <a href="{{ route('orders.create') }}">Cobro</a>
                    @endif
                    @if ($auth->hasPermission('laboratory.view'))
                        <a href="{{ route('orders.lab') }}">Lab</a>
                    @endif
                    @if ($auth->hasPermission('orders.view'))
                        <a href="{{ route('orders.index') }}">Ordenes</a>
                    @endif
                    @if ($auth->hasPermission('cash.view'))
                        <a href="{{ route('cash.index') }}">Caja</a>
                    @endif
                    @if ($auth->hasPermission('catalogs.view'))
                        <a href="{{ route('catalog.index') }}">Catalogos</a>
                    @endif
                    @if (session('biolab_user'))
                        <form class="mobile-logout-form" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">Salir</button>
                        </form>
                    @endif
                </nav>
            </header>

            @yield('body')
        </div>
    </div>
</body>
</html>
