<?php

namespace App\Http\Middleware;

use App\Models\Access;
use App\Models\Menu;
use Closure;
use Illuminate\Http\Request;

class CheckAccess
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $name = explode('.', $request->route()->getName())[0];

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

        $menuId = Menu::whereName($name)->first()->id;
        $accessType = Access::where([
            ['menu_id', '=', $menuId],
            ['role_id', '=', auth()->user()->role_id],
        ])->first()->status;

        if ($accessType < 1) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
