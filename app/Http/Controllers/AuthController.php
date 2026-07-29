<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use App\Services\AuthStore;
use Illuminate\Http\Request;

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

        $user = $this->auth->attempt($data['email'], $data['password']);

        if (! $user) {
            $this->audit->record('login_failed', 'auth', null, ['email' => $data['email']]);

            return back()->withErrors(['email' => 'Credenciales invalidas.'])->onlyInput('email');
        }

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
}
