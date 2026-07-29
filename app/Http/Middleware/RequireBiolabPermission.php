<?php

namespace App\Http\Middleware;

use App\Services\AuthStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBiolabPermission
{
    public function __construct(private readonly AuthStore $auth) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $permission) {
            if ($this->auth->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permisos para esta accion.');
    }
}
