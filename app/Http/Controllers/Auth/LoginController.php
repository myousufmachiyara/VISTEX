<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Override the username field.
     * Your users table has 'username', not 'email'.
     * Laravel's default AuthenticatesUsers uses 'email'.
     */
    public function username(): string
    {
        return 'username';
    }

    /**
     * Called after successful authentication.
     *
     * FIX 1: Sets session('user_name') and session('role_name') which
     *         app.blade.php header reads. Laravel does NOT set these
     *         automatically — they must be set here on every login.
     *
     * FIX 2: Blocks inactive users even if credentials are correct.
     *         Without this check, deactivated users can still log in.
     */
    protected function authenticated(Request $request, $user): mixed
    {
        // Block inactive users
        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'Your account has been deactivated. Please contact the administrator.']);
        }

        // Set session variables used in app.blade.php header
        $roles = $user->getRoleNames(); // Spatie — returns Collection of strings

        session([
            'user_name' => $user->name,
            'role_name' => $roles->first() ?? 'User',
        ]);

        Log::info('[Auth] Login', [
            'user_id'  => $user->id,
            'username' => $user->username,
            'role'     => $roles->first(),
            'ip'       => $request->ip(),
        ]);

        return redirect()->intended($this->redirectTo);
    }

    /**
     * Override failed login to return username-specific error.
     * Default returns email-related error which is confusing.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            $this->username() => ['Invalid username or password.'],
        ]);
    }
}