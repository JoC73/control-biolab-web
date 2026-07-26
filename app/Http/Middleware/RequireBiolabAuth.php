<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBiolabAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('biolab_user')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
