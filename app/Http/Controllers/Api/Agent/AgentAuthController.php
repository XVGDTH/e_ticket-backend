<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AgentAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'telephone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
            'appareil' => ['nullable', 'string', 'max:60'],
        ]);

        $telephone = trim($donnees['telephone']);
        $cle = 'agent-login:' . Str::lower($telephone) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($cle) . ' secondes.',
            ], 429);
        }

        $user = User::where('telephone', $telephone)->first();

        // Même message quelle que soit la raison : mauvais mot de passe, compte désactivé ou pas un agent.
        $valide = $user
            && Hash::check($donnees['password'], $user->password)
            && ($user->actif ?? true)
            && $user->isAgentGare()
            && $user->gareId();

        if (! $valide) {
            RateLimiter::hit($cle, 60);

            return response()->json(['message' => 'Numéro de téléphone ou mot de passe incorrect.'], 422);
        }

        RateLimiter::clear($cle);

        // Un seul jeton par appareil ; expiration à 30 jours ; capacité limitée à l'espace agent.
        $nomAppareil = 'agent-' . Str::slug($donnees['appareil'] ?? 'mobile');
        $user->tokens()->where('name', $nomAppareil)->delete();

        $jeton = $user->createToken($nomAppareil, ['agent'], now()->addDays(30));

        return response()->json([
            'token' => $jeton->plainTextToken,
            'expire_le' => $jeton->accessToken->expires_at?->toIso8601String(),
            'doit_changer_mot_de_passe' => (bool) $user->doit_changer_mot_de_passe,
            'agent' => $this->profil($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['agent' => $this->profil($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $jeton = $request->user()->currentAccessToken();

        if ($jeton instanceof PersonalAccessToken) {
            $jeton->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function motDePasse(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'mot_de_passe_actuel' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'confirmed', 'different:mot_de_passe_actuel', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($donnees['mot_de_passe_actuel'], $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->forceFill([
            'password' => $donnees['nouveau_mot_de_passe'],
            'doit_changer_mot_de_passe' => false,
        ])->save();

        // Les autres appareils sont déconnectés ; celui-ci reste valable.
        $courant = $user->currentAccessToken();

        if ($courant instanceof PersonalAccessToken) {
            $user->tokens()->where('id', '!=', $courant->id)->delete();
        }

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    private function profil(User $user): array
    {
        $profil = $user->profilStaff();

        return [
            'id' => $user->id,
            'nom' => trim("{$user->prenom} {$user->nom}"),
            'telephone' => $user->telephone,
            'role' => $profil['role'],
            'compagnie' => $profil['compagnie'],
            'gare' => [
                'id' => $user->gareId(),
                'quartier' => $profil['gare'],
                'ville' => $profil['ville'],
            ],
        ];
    }
}