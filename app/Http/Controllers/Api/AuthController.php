<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prenom' => 'required|string|min:2|max:100',
            'nom' => 'required|string|min:2|max:100',
            'telephone' => 'required|string|min:8|max:20',
            'password' => 'required|string|min:6|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $telephone = trim($request->telephone);
        $user = User::where('telephone', $telephone)->first();

        // Un numéro déjà vérifié ne peut pas être réinscrit.
        if ($user && $user->telephone_verifie) {
            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ['Ce numéro est déjà utilisé.']],
            ], 422);
        }

        // 3 demandes de code par numéro et par 10 minutes (chaque envoi WhatsApp coûte de l'argent).
        $cleEnvoi = 'otp-envoi:' . $telephone;

        if (RateLimiter::tooManyAttempts($cleEnvoi, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes. Réessayez dans ' . RateLimiter::availableIn($cleEnvoi) . ' secondes.',
            ], 429);
        }

        RateLimiter::hit($cleEnvoi, 600);

        // Numéro jamais vérifié : l'inscription est mise à jour, ce qui empêche un tiers
        // de « réserver » le numéro d'autrui avec son propre mot de passe.
        $user ??= new User();

        $user->forceFill([
            'prenom' => $request->prenom,
            'nom' => $request->nom,
            'telephone' => $telephone,
            'password' => $request->password,
            'telephone_verifie' => false,
        ])->save();

        if ($user->wasRecentlyCreated) {
            $user->assignerRolePourCompagnie('client');
        }

        $otp = Otp::genererPour($telephone);

        $this->envoyerOtp($telephone, (string) $otp->code);

        return response()->json([
            'success' => true,
            'message' => 'Code envoyé par WhatsApp.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string|max:20',
            'code' => 'required|string|max:10',
        ]);

        $telephone = trim($request->telephone);
        $cle = 'otp-verif:' . $telephone;

        // 5 essais par numéro et par 10 minutes, quelle que soit l'adresse IP.
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop d\'essais. Réessayez dans ' . RateLimiter::availableIn($cle) . ' secondes.',
            ], 429);
        }

        $otp = Otp::where('telephone', $telephone)
            ->where('utilise', false)
            ->latest()
            ->first();

        if (! $otp || ! $otp->estValide($request->code)) {
            RateLimiter::hit($cle, 600);

            return response()->json(['success' => false, 'message' => 'Code incorrect ou expiré.'], 422);
        }

        RateLimiter::clear($cle);
        $otp->update(['utilise' => true]);

        $user = User::where('telephone', $telephone)->firstOrFail();
        $user->update(['telephone_verifie' => true]);

        return $this->reponseConnexion($user);
    }

    public function login(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string|max:20',
            'password' => 'required|string|max:100',
        ]);

        $telephone = trim($request->telephone);
        $cle = 'login:' . Str::lower($telephone) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($cle) . ' secondes.',
            ], 429);
        }

        $user = User::where('telephone', $telephone)->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            RateLimiter::hit($cle, 60);

            return response()->json(['success' => false, 'message' => 'Identifiants incorrects.'], 401);
        }

        // Un numéro non vérifié ne peut pas se connecter : il doit d'abord saisir le code reçu.
        if (! $user->telephone_verifie) {
            return response()->json([
                'success' => false,
                'code' => 'telephone_non_verifie',
                'message' => 'Numéro non vérifié. Terminez l\'inscription avec le code reçu.',
            ], 403);
        }

        if (! ($user->actif ?? true)) {
            return response()->json(['success' => false, 'message' => 'Compte désactivé.'], 403);
        }

        RateLimiter::clear($cle);

        return $this->reponseConnexion($user);
    }

    /** Jeton de l'application voyageurs : capacité limitée, valable 180 jours. */
    private function reponseConnexion(User $user)
    {
        $token = $user->createToken('mobile', ['client'], now()->addDays(180))->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'telephone' => $user->telephone,
            ],
        ]);
    }

    private function envoyerOtp(string $telephone, string $code): void
    {
        if (config('services.whatsapp.token')) {
            try {
                app(WhatsAppService::class)->envoyerOtp($telephone, $code);
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif (app()->environment('local')) {
            // Développement seulement : le code est lisible dans storage/logs/laravel.log.
            logger()->info("[OTP local] {$telephone} : {$code}");
        }
    }
}