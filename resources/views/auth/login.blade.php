<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar - BIOLAB</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-card">
            <div class="brand-block login-brand">
                @include('components.biolab-logo', ['class' => 'brand-logo'])
                <span>
                    <strong>BIOLAB</strong>
                    <small>Control laboratorio</small>
                </span>
            </div>
            <div>
                <p class="eyebrow">Acceso seguro</p>
                <h1>Ingresar al sistema</h1>
                <p>Usa tu usuario asignado para registrar ordenes, caja y resultados.</p>
            </div>

            @if ($errors->any())
                <div class="status-message error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <div class="field">
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email" value="{{ old('email', 'admin@biolab.local') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Contrasena</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="button primary" type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>
