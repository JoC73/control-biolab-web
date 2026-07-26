<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBiolabRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = session('biolab_user');

        if (! $user || ($user['role'] !== 'admin' && ! in_array($user['role'], $roles, true))) {
            abort(403, 'No tienes permisos para esta accion.');
        }

        return $next($request);
    }
}
