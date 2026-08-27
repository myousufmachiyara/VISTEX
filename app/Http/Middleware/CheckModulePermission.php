<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CheckPermission middleware
 * ──────────────────────────
 * Usage in routes (always pass the FULL permission string):
 *   ->middleware('check.permission:module.action')
 *
 * Examples:
 *   ->middleware('check.permission:projects.index')
 *   ->middleware('check.permission:vouchers.create')
 *   ->middleware('check.permission:reports.inventory')
 */
class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        // ── Not authenticated ────────────────────────────────────────
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }
            return redirect()->route('login');
        }

        // ── Check the permission ─────────────────────────────────────
        if (!auth()->user()->can($permission)) {
            Log::channel('daily')->warning('[Permission] Denied', [
                'user_id'    => auth()->id(),
                'permission' => $permission,
                'url'        => $request->fullUrl(),
                'method'     => $request->method(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'Access denied. You do not have permission to perform this action.');
        }

        return $next($request);
    }
}