<?php

namespace App\Providers;

use App\Models\Access;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        View::composer('*', function ($view) {
            if (auth()->check()) {
                $accesses = resolve(Access::class)->get(true);

                return $view->with('accesses', $accesses);
            }
        });
    }
}
