<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use App\Services\AuthStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthStore $auth,
        private readonly AuditStore $audit,
    ) {}

    public function showLogin()
    {
        if ($this->auth->check()) {
            return redirect()->route('lab.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->audit->record('login_throttled', 'auth', null, ['email' => $data['email']]);

            return back()
                ->withErrors(['email' => "Demasiados intentos. Espera {$seconds} segundos antes de intentar nuevamente."])
                ->onlyInput('email');
        }

        $user = $this->auth->attempt($data['email'], $data['password']);

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);
            $this->audit->record('login_failed', 'auth', null, ['email' => $data['email']]);

            return back()->withErrors(['email' => 'Correo o contrasena incorrectos. Revisa los datos e intenta de nuevo.'])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();
        session(['biolab_user' => $user]);
        $this->audit->record('login_success', 'auth', null, ['email' => $user['email']]);

        return redirect()->intended(route('lab.index'));
    }

    public function logout()
    {
        $this->audit->record('logout', 'auth');
        session()->forget('biolab_user');
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
}
