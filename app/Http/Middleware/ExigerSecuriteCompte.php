<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigerSecuriteCompte
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'exigenceSecurite')) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();

        // La page de profil (où l'on règle le problème) et la déconnexion restent accessibles.
        if ($request->routeIs($panel->generateRouteName('auth.profile'), $panel->generateRouteName('auth.logout'))) {
            return $next($request);
        }

        if ($message = $user->exigenceSecurite()) {
            Notification::make()->title('Action requise')->body($message)->warning()->send();

            return redirect(route($panel->generateRouteName('auth.profile')));
        }

        return $next($request);
    }
}