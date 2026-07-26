<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuthStore
{
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
        return collect(config('biolab.users'))
            ->map(fn (array $user) => [
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'role' => $user['role'],
            ])
            ->values()
            ->all();
    }
}
