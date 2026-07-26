<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use App\Services\AuthStore;
use Illuminate\Http\Request;

class UserPermissionController extends Controller
{
    public function __construct(
        private readonly AuthStore $auth,
        private readonly AuditStore $audit,
    ) {
    }

    public function index()
    {
        return view('admin.users', [
            'users' => $this->auth->users(),
            'roles' => AuthStore::ROLES,
            'permissions' => AuthStore::PERMISSIONS,
        ]);
    }

    public function update(Request $request, string $email)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(AuthStore::ROLES))],
        ]);

        $updated = $this->auth->updateRole($email, $data['role']);
        abort_if($updated === null, 404);

        $this->audit->record('user_role_updated', 'user', $email, [
            'email' => $email,
            'role' => $data['role'],
        ]);

        if (session('biolab_user.email') === $updated['email']) {
            session(['biolab_user' => $updated]);
        }

        return redirect()->route('admin.users.index')->with('status', 'Permisos actualizados.');
    }
}
