@extends('layouts.lab', ['title' => 'Usuarios y permisos'])

@section('body')
    <main class="app-shell">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Administracion</p>
                <h1>Usuarios y permisos</h1>
                <p>Asigna el rol operativo de cada usuario del sistema.</p>
            </div>
            <div class="top-actions">
                <a class="button" href="{{ route('audit.index') }}">Auditoria</a>
            </div>
        </header>

        @if (session('status'))
            <div class="status-message wide">{{ session('status') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            <div class="status-message wide error-message">{{ $errors->first() }}</div>
        @endif

        <section class="panel user-permissions-panel">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Roles</p>
                    <h2>Permisos por usuario</h2>
                </div>
            </div>

            <div class="user-permission-list">
                @foreach ($users as $user)
                    <article class="user-permission-card">
                        <div>
                            <strong>{{ $user['name'] }}</strong>
                            <span>{{ $user['email'] }}</span>
                            <div class="badge-line">
                                @foreach ($permissions[$user['role']] ?? [] as $permission)
                                    <span class="soft-badge">{{ $permission === '*' ? 'Acceso total' : ($permissionLabels[$permission] ?? $permission) }}</span>
                                @endforeach
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.users.update', $user['email']) }}">
                            @csrf
                            @method('PUT')
                            <label for="role-{{ md5($user['email']) }}">Rol</label>
                            <select id="role-{{ md5($user['email']) }}" name="role">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($user['role'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="button primary compact-button" type="submit">Guardar</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
@endsection
