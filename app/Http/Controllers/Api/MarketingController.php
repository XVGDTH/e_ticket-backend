<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketing\Consentement;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function etat(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'success' => true,
            'accepte' => (bool) $u->marketing_whatsapp,
            'app' => (bool) $u->marketing_app,
            'deja_repondu' => $u->marketing_whatsapp_le !== null,
        ]);
    }

    public function definir(Request $request)
    {
        $donnees = $request->validate([
            'accepte' => ['required', 'boolean'],
            'source' => ['nullable', 'in:inscription,profil,invite'],
            'canal' => ['nullable', 'in:whatsapp,app'],
        ]);

        $source = match ($donnees['source'] ?? 'profil') {
            'inscription' => 'inscription_app',
            'invite' => 'invite_app',
            default => 'profil_app',
        };

        Consentement::definir($request->user(), (bool) $donnees['accepte'], $source, $donnees['canal'] ?? 'whatsapp');

        return response()->json(['success' => true, 'accepte' => (bool) $donnees['accepte']]);
    }
}
