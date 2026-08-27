<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::all();

        return view('users.index', compact('users', 'roles'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        // FIX: original had no try/catch and no DB::transaction
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            // FIX: min raised to 8 to match changeMyPassword and app.blade rules
            'password' => 'required|min:8|confirmed',
            'role'     => 'required|exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'      => $request->name,
                'username'  => $request->username,
                'password'  => Hash::make($request->password),
                'is_active' => true,
            ]);

            $role = Role::findById($request->role);
            $user->assignRole($role);

            DB::commit();

            Log::info('[User] Created', ['id' => $user->id, 'by' => auth()->id()]);

            return redirect()->route('users.index')
                ->with('success', 'User "' . $user->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[User] Store error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // show() — returns JSON for the edit modal AJAX call
    public function show($id)
    {
        try {
            $user = User::with('roles:id,name')->findOrFail($id);

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'roles'    => $user->roles->map(fn($r) => [
                        'id'   => $r->id,
                        'name' => $r->name,
                    ]),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            // FIX: Rule::unique ignore on the correct column
            'username' => ['required', 'string', Rule::unique('users', 'username')->ignore($id)],
            'role'     => 'required|exists:roles,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);

            $user->update([
                'name'     => $request->name,
                'username' => $request->username,
            ]);

            $role = Role::findById($request->role);
            if (!$role) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Selected role not found.')
                    ->withInput();
            }

            // syncRoles replaces all existing roles — correct for single-role system
            $user->syncRoles([$role->name]);

            DB::commit();

            Log::info('[User] Updated', ['id' => $user->id, 'by' => auth()->id()]);

            return redirect()->route('users.index')
                ->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[User] Update error', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Admin changing another user's password
    public function changePassword(Request $request, $id)
    {
        $request->validate([
            // FIX: raised to min:8 — consistent with changeMyPassword
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user        = User::findOrFail($id);
            $currentUser = auth()->user();

            // Guard: only superadmin can change another superadmin's password
            if (
                $user->hasRole('superadmin') &&
                !$currentUser->hasRole('superadmin')
            ) {
                return redirect()->back()
                    ->with('error', 'Only a superadmin can change the superadmin password.');
            }

            // Guard: cannot change your own password via admin route — use changeMyPassword
            if ($user->id === $currentUser->id) {
                return redirect()->back()
                    ->with('error', 'Use "Change My Password" to change your own password.');
            }

            $user->password = Hash::make($request->password);
            $user->save();

            Log::info('[User] Password changed by admin', [
                'target_user_id' => $user->id,
                'changed_by'     => $currentUser->id,
            ]);

            return redirect()->route('users.index')
                ->with('success', 'Password changed successfully.');

        } catch (\Exception $e) {
            Log::error('[User] changePassword error', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Logged-in user changing their own password — returns JSON for AJAX
    public function changeMyPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8',
            'new_password_confirmation' => 'required|string|same:new_password',
        ], [
            'new_password.min'               => 'New password must be at least 8 characters.',
            'new_password_confirmation.same' => 'Passwords do not match.',
            'current_password.required'      => 'Please enter your current password.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->all(),
            ], 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'errors'  => ['Current password is incorrect.'],
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        Log::info('[Auth] Own password changed', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    public function toggleActive($id)
    {
        try {
            $user = User::findOrFail($id);

            // Guard: superadmin cannot be deactivated by anyone
            if ($user->hasRole('superadmin')) {
                return redirect()->back()
                    ->with('error', 'The superadmin account cannot be deactivated.');
            }

            // Guard: cannot deactivate yourself
            if ($user->id === auth()->id()) {
                return redirect()->back()
                    ->with('error', 'You cannot deactivate your own account.');
            }

            $user->is_active = !$user->is_active;
            $user->save();

            $status = $user->is_active ? 'activated' : 'deactivated';

            Log::info('[User] Status toggled', [
                'user_id' => $user->id,
                'status'  => $status,
                'by'      => auth()->id(),
            ]);

            return redirect()->back()
                ->with('success', "User {$status} successfully.");

        } catch (\Exception $e) {
            Log::error('[User] toggleActive error', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Guard: superadmin cannot be deleted
            if ($user->hasRole('superadmin')) {
                return redirect()->back()
                    ->with('error', 'The superadmin account cannot be deleted.');
            }

            // Guard: cannot delete yourself
            if ($user->id === auth()->id()) {
                return redirect()->back()
                    ->with('error', 'You cannot delete your own account.');
            }

            $user->delete();

            Log::info('[User] Deleted', ['user_id' => $id, 'by' => auth()->id()]);

            return redirect()->back()
                ->with('success', 'User deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[User] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }
}