<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        $user->tokens()->where('name', 'mobile')->delete();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->profile($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($this->profile($request->user()));
    }

    // is_incharge drives the "Inspect Challans" tile in the app
    private function profile(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'role'        => $user->roles->first()->name ?? null,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'is_incharge' => $user->hasRole('superadmin') || \App\Models\CategoryIncharge::where('user_id', $user->id)->exists(),
        ];
    }
}