<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    // POST /api/login  — uses username (matches web LoginController)
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        // Block inactive users — same rule as web login
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account has been deactivated. Please contact the administrator.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        Log::info('[Auth API] Login', [
            'user_id'  => $user->id,
            'username' => $user->username,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'success'     => true,
            'token'       => $token,
            'user'        => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'roles'    => $user->getRoleNames(),
            ],
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    // GET /api/me
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'roles'    => $user->getRoleNames(),
            ],
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true]);
    }

    // POST /api/change-password
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['old_password'], $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $data['new_password']]); // hashed via cast

        return response()->json(['success' => true]);
    }
}