<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        return view('roles-permissions', ['roles' => Role::with('permissions')->orderBy('name')->get(), 'permissions' => Permission::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:roles,name'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles-permissions.index')->with('success', 'Rôle créé avec succès.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id], 'permissions' => ['array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles-permissions.index')->with('success', 'Permissions du rôle mises à jour.');
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'superAdmin') {
            return back()->with('error', 'Le rôle superAdmin ne peut pas être supprimé.');
        }

        $role->delete();

        return back()->with('success', 'Rôle supprimé avec succès.');
    }
}