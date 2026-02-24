<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserRoleController extends Controller
{
    /**
     * Tampilkan daftar semua user beserta roles-nya
     */
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate(15)
            ->withQueryString();

        $roles = Role::select('id', 'name', 'display_name', 'color')->get();

        return Inertia::render('settings/Users', [
            'users'  => $users,
            'roles'  => $roles,
            'filters' => ['search' => $request->search],
        ]);
    }

    /**
     * Buat user baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:255|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'roles'                 => 'array',
            'roles.*'               => 'exists:roles,id',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'email_verified_at' => now(), // langsung verified karena dibuat oleh admin
        ]);

        if (!empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        return redirect()->back()->with('success', "User '{$user->name}' berhasil dibuat.");
    }

    /**
     * Update roles milik seorang user
     */
    public function updateRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'   => 'array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        return redirect()->back()->with('success', "Roles untuk '{$user->name}' berhasil diperbarui.");
    }

    /**
     * Hapus user
     */
    public function destroy(Request $request, User $user)
    {
        // Tidak boleh hapus diri sendiri
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->back()->with('success', "User '{$userName}' berhasil dihapus.");
    }
}
