<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Tampilkan halaman roles + permissions
     */
    public function index()
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->get();

        $permissions = Permission::orderBy('group')->orderBy('display_name')->get()
            ->groupBy('group');

        return Inertia::render('settings/Roles', [
            'roles'       => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Buat role baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:50|unique:roles,name|alpha_dash',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
            'color'        => 'required|string|in:gray,red,orange,yellow,green,blue,violet,pink',
            'permissions'  => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name'         => $validated['name'],
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
            'color'        => $validated['color'],
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->back()->with('success', "Role '{$role->display_name}' berhasil dibuat.");
    }

    /**
     * Update role dan permissions-nya
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:50|alpha_dash|unique:roles,name,' . $role->id,
            'display_name'  => 'required|string|max:100',
            'description'   => 'nullable|string|max:255',
            'color'         => 'required|string|in:gray,red,orange,yellow,green,blue,violet,pink',
            'permissions'   => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name'         => $validated['name'],
            'display_name' => $validated['display_name'],
            'description'  => $validated['description'] ?? null,
            'color'        => $validated['color'],
        ]);

        // Sync permissions (hapus yang lama, pasang yang baru)
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->back()->with('success', "Role '{$role->display_name}' berhasil diperbarui.");
    }

    /**
     * Hapus role
     */
    public function destroy(Role $role)
    {
        // Cek apakah role masih dipakai user
        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', "Role '{$role->display_name}' masih digunakan oleh {$role->users()->count()} user.");
        }

        $roleName = $role->display_name;
        $role->delete();

        return redirect()->back()->with('success', "Role '{$roleName}' berhasil dihapus.");
    }
}
