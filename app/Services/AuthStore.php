<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AuthStore
{
    public const ROLES = [
        'admin' => 'Administrador',
        'recepcion' => 'Recepcion',
        'laboratorio' => 'Laboratorio',
        'caja' => 'Caja',
    ];

    public const PERMISSIONS = [
        'admin' => ['Administracion total', 'Usuarios y permisos', 'Auditoria', 'Catalogos', 'Caja', 'Laboratorio', 'Resultados'],
        'recepcion' => ['Registrar cobros', 'Consultar ordenes', 'Caja basica', 'Entregar resultados', 'Catalogos de referencia'],
        'laboratorio' => ['Cola de laboratorio', 'Llenar resultados', 'Editar resultados', 'Generar PDF'],
        'caja' => ['Registrar cobros', 'Caja', 'Abonos', 'Anular movimientos autorizados'],
    ];

    public function attempt(string $email, string $password): ?array
    {
        $user = Arr::first($this->users(), fn (array $user) => Str::lower($user['email']) === Str::lower($email));

        if (! $user || ! hash_equals((string) $user['password'], $password)) {
            return null;
        }

        return Arr::except($user, ['password']);
    }

    public function current(): ?array
    {
        return session('biolab_user');
    }

    public function check(): bool
    {
        return $this->current() !== null;
    }

    public function hasRole(array|string $roles): bool
    {
        $user = $this->current();

        if (! $user) {
            return false;
        }

        $roles = Arr::wrap($roles);

        return $user['role'] === 'admin' || in_array($user['role'], $roles, true);
    }

    public function users(): array
    {
        return collect($this->storedUsers())
            ->map(fn (array $user) => [
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'role' => $user['role'],
            ])
            ->values()
            ->all();
    }

    public function updateRole(string $email, string $role): ?array
    {
        if (! array_key_exists($role, self::ROLES)) {
            return null;
        }

        $users = collect($this->users())->map(function (array $user) use ($email, $role) {
            if (Str::lower($user['email']) === Str::lower($email)) {
                $user['role'] = $role;
            }

            return $user;
        })->values()->all();

        $updated = Arr::first($users, fn (array $user) => Str::lower($user['email']) === Str::lower($email));

        if (! $updated) {
            return null;
        }

        $this->persistUsers($users);

        return Arr::except($updated, ['password']);
    }

    private function storedUsers(): array
    {
        if ($this->usesDatabase()) {
            $this->seedDatabaseUsers();

            return DB::table('biolab_users')->orderBy('name')->get()->map(fn ($row) => [
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'password' => (string) $row->password,
                'role' => (string) $row->role,
            ])->all();
        }

        if (File::exists($this->jsonPath())) {
            $users = json_decode(File::get($this->jsonPath()), true);

            return is_array($users) ? $users : $this->configUsers();
        }

        return $this->configUsers();
    }

    private function persistUsers(array $users): void
    {
        if ($this->usesDatabase()) {
            foreach ($users as $user) {
                DB::table('biolab_users')->updateOrInsert(
                    ['email' => $user['email']],
                    [
                        'name' => $user['name'],
                        'password' => $user['password'],
                        'role' => $user['role'],
                        'updated_at' => now(),
                    ]
                );
            }

            return;
        }

        File::ensureDirectoryExists(dirname($this->jsonPath()));
        File::put($this->jsonPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function seedDatabaseUsers(): void
    {
        if (DB::table('biolab_users')->exists()) {
            return;
        }

        foreach ($this->configUsers() as $user) {
            DB::table('biolab_users')->insert([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'role' => $user['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function configUsers(): array
    {
        return config('biolab.users');
    }

    private function jsonPath(): string
    {
        return storage_path('app/admin/users.json');
    }

    private function usesDatabase(): bool
    {
        return config('database.default') !== 'sqlite' && config('biolab.storage') === 'database';
    }
}
