<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar - BIOLAB</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}">
</head>
<body class="login-body">
    <main class="login-shell" aria-labelledby="login-title">
        <section class="login-hero" aria-label="Laboratorio Biologico BIOLAB">
            <div class="login-logo-mark">
                @include('components.biolab-logo', ['class' => 'login-logo'])
            </div>
            <div>
                <p class="eyebrow">Control de resultados</p>
                <h1>Laboratorio Biologico BIOLAB</h1>
                <p>Ordenes, caja, resultados y catalogos en un flujo seguro.</p>
            </div>
        </section>

        <section class="login-card">
            <div class="login-heading">
                <p class="eyebrow">Acceso seguro</p>
                <h2 id="login-title">Ingresar al sistema</h2>
                <p>Usa las credenciales asignadas por administracion.</p>
            </div>

            @if ($errors->any())
                <div class="status-message error-message login-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <div class="field">
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Contrasena</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" autocomplete="current-password" required data-password-input>
                        <button class="button compact-button" type="button" data-password-toggle aria-controls="password" aria-pressed="false">Ver</button>
                    </div>
                </div>
                <button class="button primary login-submit" type="submit">Ingresar</button>
            </form>
        </section>
    </main>

    <script>
        (() => {
            const password = document.querySelector('[data-password-input]');
            const toggle = document.querySelector('[data-password-toggle]');

            if (! password || ! toggle) return;

            toggle.addEventListener('click', () => {
                const visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                toggle.textContent = visible ? 'Ver' : 'Ocultar';
                toggle.setAttribute('aria-pressed', String(! visible));
            });
        })();
    </script>
</body>
</html>
