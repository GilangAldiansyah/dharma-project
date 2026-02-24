<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get()->groupBy('group');

        return Inertia::render('settings/Permissions', [
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:permissions,name|regex:/^[a-z0-9\-\.]+$/',
            'display_name' => 'required|string|max:100',
            'group'        => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        Permission::create($validated);

        return redirect()->back()->with('success', "Permission '{$validated['display_name']}' berhasil dibuat.");
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|regex:/^[a-z0-9\-\.]+$/|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:100',
            'group'        => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        $permission->update($validated);

        return redirect()->back()->with('success', "Permission '{$permission->display_name}' berhasil diperbarui.");
    }

    public function destroy(Permission $permission)
    {
        $name = $permission->display_name;
        $permission->delete();

        return redirect()->back()->with('success', "Permission '{$name}' berhasil dihapus.");
    }
}
