<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentGare
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Jeton agent, compte actif, vraiment agent, rattaché à une gare.
        if (! $user || ! ($user->actif ?? true) || ! $user->isAgentGare() || ! $user->gareId() || ! $user->tokenCan('agent')) {
            return response()->json(['message' => 'Accès réservé aux agents de gare.'], 403);
        }

        // Mot de passe temporaire : seuls le profil, le changement de mot de passe et la déconnexion passent.
        if ($user->doit_changer_mot_de_passe && ! $request->routeIs('agent.mot-de-passe', 'agent.me', 'agent.logout')) {
            return response()->json([
                'message' => 'Vous devez d\'abord changer votre mot de passe.',
                'code' => 'mot_de_passe_a_changer',
            ], 403);
        }

        return $next($request);
    }
}