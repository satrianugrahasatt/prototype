<?php

namespace App\Http\Middleware;

use App\Models\Access;
use App\Models\Menu;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckAccess
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            Log::warning('CheckAccess: Unauthenticated user', ['route' => $request->route()->getName()]);

            return redirect()->route('login')->with('error', 'Please log in to access this resource.');
        }

        // Get route name
        $name = explode('.', $request->route()->getName())[0] ?? '';
        Log::debug('CheckAccess: Route name', ['name' => $name]);

        // Map route names to menu names
        if (in_array($name, ['employees-data', 'departments-data', 'positions-data'])) {
            $name = 'data';
        } elseif (in_array($name, ['users', 'roles'])) {
            $name = 'accounts';
        } elseif ($name == 'profile') {
            $name = 'user';
        } elseif ($name == 'employees-leave-request') {
            $name = 'leave-request';
        } elseif ($name == 'employees-performance-score') {
            $name = 'performance';
        } elseif ($name == 'score-categories') {
            $name = 'score-category';
        }

        // Find menu
        $menu = Menu::whereName($name)->first();
        if (! $menu) {
            Log::error('CheckAccess: Menu not found', ['name' => $name]);

            return redirect()->route('dashboard')->with('error', 'Menu not found.');
        }

        // Find access
        $access = Access::where([
            ['menu_id', '=', $menu->id],
            ['role_id', '=', Auth::user()->role_id],
        ])->first();

        if (! $access || $access->status < 1) {
            Log::warning('CheckAccess: Access denied', [
                'menu_id' => $menu->id,
                'role_id' => Auth::user()->role_id,
                'status' => $access ? $access->status : null,
            ]);

            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
