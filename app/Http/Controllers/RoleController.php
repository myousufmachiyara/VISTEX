<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return view('roles.index', compact('roles'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function create()
    {
        $permissions = Permission::orderBy('name')->get();
        $role        = null;

        return view('roles.form', compact('permissions', 'role'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit(Role $role)
    {
        $permissions = Permission::orderBy('name')->get();

        return view('roles.form', compact('role', 'permissions'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255|unique:roles,name',
            'permissions'     => 'nullable|array',
            'permissions.*'   => 'string|exists:permissions,name',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            Log::info('[Role] Created', ['role' => $role->name, 'by' => auth()->id()]);

            return redirect()->route('roles.index')
                ->with('success', 'Role "' . $role->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Role] Store error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'          => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Guard: superadmin role name cannot be changed
        if ($role->name === 'superadmin' && $request->name !== 'superadmin') {
            return redirect()->back()
                ->with('error', 'The superadmin role name cannot be changed.');
        }

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);

            // syncPermissions with empty array removes all — correct behaviour
            $role->syncPermissions($request->permissions ?? []);

            DB::commit();

            Log::info('[Role] Updated', ['role' => $role->name, 'by' => auth()->id()]);

            return redirect()->route('roles.index')
                ->with('success', 'Role updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Role] Update error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy(Role $role)
    {
        // Guard: system roles cannot be deleted
        $protected = ['superadmin', 'admin', 'manager', 'accountant', 'operator', 'viewer'];
        if (in_array($role->name, $protected)) {
            return redirect()->back()
                ->with('error', 'The "' . $role->name . '" role is a system role and cannot be deleted.');
        }

        // Guard: roles with assigned users cannot be deleted
        if ($role->users()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete role "' . $role->name . '" — it is assigned to ' . $role->users()->count() . ' user(s). Reassign them first.');
        }

        try {
            $roleName = $role->name;
            $role->delete();

            Log::info('[Role] Deleted', ['role' => $roleName, 'by' => auth()->id()]);

            return redirect()->route('roles.index')
                ->with('success', 'Role deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Role] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }
}