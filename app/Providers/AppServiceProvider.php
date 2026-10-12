<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    // public function boot(): void
    // {
    //     //
    // Gate::policy(\App\Models\Gare::class, \App\Policies\GarePolicy::class);
    // Gate::policy(\App\Models\RouteTransport::class, \App\Policies\RouteTransportPolicy::class);
    // Gate::policy(\App\Models\Billet::class, \App\Policies\BilletPolicy::class);
    // }
}
