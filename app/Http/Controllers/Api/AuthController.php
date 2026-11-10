<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationClient;
use App\Models\Otp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /** Durée de la session : prolongée à chaque ouverture de l'application (comme WhatsApp). */
    private const SESSION_JOURS = 180;

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

        // if ($user && $user->telephone_verifie) {
        if ($user && ($user->telephone_verifie || $user->aUnRoleStaff())) {
            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ['Ce numéro est déjà utilisé.']],
            ], 422);
        }

        $cleEnvoi = 'otp-envoi:'.$telephone;

        if (RateLimiter::tooManyAttempts($cleEnvoi, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes. Réessayez dans '.RateLimiter::availableIn($cleEnvoi).' secondes.',
            ], 429);
        }

        RateLimiter::hit($cleEnvoi, 600);

        $user ??= new User;

        $user->forceFill([
            'prenom' => $request->prenom,
            'nom' => $request->nom,
            'telephone' => $telephone,
            'password' => $request->password,
            'telephone_verifie' => false,
            'tentatives_login' => 0,
            'verrouille_jusqua' => null,
            'derniere_tentative_echec' => null,
        ])->save();

        if ($user->wasRecentlyCreated) {
            $user->assignerRolePourCompagnie('client');
        }

        $otp = Otp::genererPour($telephone);

        // Le client n'est jamais informé d'un envoi qui n'a pas eu lieu.
        if (! $this->envoyerOtp($telephone, (string) $otp->code)) {
            return $this->echecEnvoiCode();
        }

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
        $cle = 'otp-verif:'.$telephone;

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop d\'essais. Réessayez dans '.RateLimiter::availableIn($cle).' secondes.',
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
        $premiereVerification = ! $user->telephone_verifie;
        $user->update(['telephone_verifie' => true]);

        if ($premiereVerification) {
            // Si la création échoue, la vraie raison est écrite dans storage/logs/laravel.log.
            try {
                (new NotificationClient)->forceFill([
                    'user_id' => $user->id,
                    'type' => 'info',
                    'titre' => 'Bienvenue sur votre application !',
                    'corps' => 'Réservez vos trajets en toute simplicité, où que vous soyez au Burkina.',
                    'cle' => 'bienvenue',
                ])->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->reponseConnexion($user);
    }

    public function login(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string|max:20',
            'password' => 'required|string|max:100',
        ]);

        $telephone = trim($request->telephone);
        $user = User::where('telephone', $telephone)->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Identifiants incorrects.'], 401);
        }

        // 1. Compte bloqué définitivement : il doit réinitialiser son mot de passe
        if ($user->doit_changer_mot_de_passe) {
            return response()->json([
                'success' => false,
                'code' => 'compte_verrouille_otp_requis',
                'message' => 'Compte bloqué. Veuillez changer votre mot de passe.',
            ], 403);
        }

        // 2. Compte en pause temporaire (les 30 secondes)
        if ($user->verrouille_jusqua && $user->verrouille_jusqua->isFuture()) {
            $secondes = (int) max(1, now()->diffInSeconds($user->verrouille_jusqua, false));

            return response()->json([
                'success' => false,
                'code' => 'trop_de_tentatives',
                'message' => "Patientez {$secondes} secondes.",
                'retry_after' => $secondes,
            ], 429);
        }

        // 3. Vérification du mot de passe
        if (! Hash::check($request->password, $user->password)) {
            return $this->enregistrerEchecLogin($user);
        }

        // 4. Vérifications de sécurité basiques
        if (! $user->telephone_verifie) {
            return response()->json([
                'success' => false,
                'code' => 'telephone_non_verifie',
                'message' => 'Numéro non vérifié.',
            ], 403);
        }

        if (! ($user->actif ?? true)) {
            return response()->json(['success' => false, 'message' => 'Compte désactivé.'], 403);
        }

        // 5. Succès : on remet tout à zéro
        $user->forceFill([
            'tentatives_login' => 0,
            'verrouille_jusqua' => null,
            'derniere_tentative_echec' => null,
        ])->save();

        return $this->reponseConnexion($user);
    }

    private function enregistrerEchecLogin(User $user)
    {
        $tentatives = (int) $user->tentatives_login + 1;

        // Après la pause de 30 s, une nouvelle erreur bloque le compte : il faut changer le mot de passe.
        if ($tentatives >= 4) {
            $user->forceFill([
                'tentatives_login' => $tentatives,
                'doit_changer_mot_de_passe' => true,
                'verrouille_jusqua' => null,
            ])->save();

            // Un code est envoyé automatiquement ; un échec d'envoi est journalisé.
            try {
                $otp = Otp::genererPour($user->telephone);
                $this->envoyerOtp($user->telephone, (string) $otp->code);
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json([
                'success' => false,
                'code' => 'compte_verrouille_otp_requis',
                'message' => 'Compte bloqué. Un code a été envoyé par WhatsApp.',
            ], 403);
        }

        // Au 3e échec : pause de 30 secondes.
        $pause = ($tentatives === 3);

        $user->forceFill([
            'tentatives_login' => $tentatives,
            'verrouille_jusqua' => $pause ? now()->addSeconds(30) : null,
            'derniere_tentative_echec' => now(),
        ])->save();

        if ($pause) {
            return response()->json([
                'success' => false,
                'code' => 'trop_de_tentatives',
                'message' => 'Trop de tentatives. Patientez 30 secondes.',
                'retry_after' => 30,
            ], 429);
        }

        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects.',
            'tentatives_restantes' => 3 - ($tentatives % 3),
        ], 401);
    }

    /** Appelée à chaque ouverture de l'application : vérifie la session et la prolonge. */
    public function me(Request $request)
    {
        $user = $request->user();
        $jeton = $user->currentAccessToken();
        $jetonReel = $jeton instanceof PersonalAccessToken ? $jeton : null;

        if (! ($user->actif ?? true)) {
            $jetonReel?->delete();

            return response()->json(['success' => false, 'message' => 'Compte désactivé.'], 401);
        }

        $jetonReel?->forceFill(['expires_at' => now()->addDays(self::SESSION_JOURS)])->save();

        return response()->json([
            'success' => true,
            'user' => [
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'telephone' => $user->telephone,
            ],
        ]);
    }

    /** Déconnexion volontaire : le jeton de cet appareil est supprimé. */
    public function logout(Request $request)
    {
        $jeton = $request->user()->currentAccessToken();

        if ($jeton instanceof PersonalAccessToken) {
            $jeton->delete();
        }

        return response()->json(['success' => true]);
    }

    /** Renvoi du code pour un numéro inscrit mais pas encore vérifié. */
    public function resendOtp(Request $request)
    {
        $request->validate(['telephone' => 'required|string|max:20']);

        $telephone = trim($request->telephone);
        $user = User::where('telephone', $telephone)->first();

        // Même réponse que le numéro existe ou non : on ne révèle rien sur les comptes.
        if (! $user || $user->telephone_verifie) {
            return response()->json(['success' => true, 'message' => 'Si ce numéro attend une vérification, un code vient d\'être envoyé.']);
        }

        $cle = 'otp-envoi:'.$telephone;

        if (RateLimiter::tooManyAttempts($cle, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes. Réessayez dans '.RateLimiter::availableIn($cle).' secondes.',
            ], 429);
        }

        RateLimiter::hit($cle, 600);

        $otp = Otp::genererPour($telephone);

        if (! $this->envoyerOtp($telephone, (string) $otp->code)) {
            return $this->echecEnvoiCode();
        }

        return response()->json(['success' => true, 'message' => 'Code renvoyé par WhatsApp.']);
    }

    /** Mot de passe oublié, étape 1 : un code est envoyé par WhatsApp. */
    public function forgotPassword(Request $request)
    {
        $request->validate(['telephone' => 'required|string|max:20']);

        $telephone = trim($request->telephone);
        $cle = 'otp-envoi:'.$telephone;

        // Limite appliquée à tous les numéros, qu'ils aient un compte ou non.
        if (RateLimiter::tooManyAttempts($cle, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de demandes. Réessayez dans '.RateLimiter::availableIn($cle).' secondes.',
            ], 429);
        }

        RateLimiter::hit($cle, 600);

        $user = User::where('telephone', $telephone)->first();

        if ($user && $this->peutReinitialiser($user)) {
            $otp = Otp::genererPour($telephone);
            $this->envoyerOtp($telephone, (string) $otp->code); // un échec est journalisé
        }

        // Même réponse dans tous les cas : on ne révèle rien sur les comptes.
        return response()->json([
            'success' => true,
            'message' => 'Si ce numéro a un compte, un code vient d\'être envoyé par WhatsApp.',
        ]);
    }

    /** Étape 2 : code reçu + nouveau mot de passe. Le client est ensuite connecté. */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string|max:20',
            'code' => 'required|string|max:10',
            'password' => 'required|string|min:6|max:100',
        ]);

        $telephone = trim($request->telephone);
        $cle = 'otp-verif:'.$telephone;

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop d\'essais. Réessayez dans '.RateLimiter::availableIn($cle).' secondes.',
            ], 429);
        }

        $otp = Otp::where('telephone', $telephone)->where('utilise', false)->latest()->first();
        $user = User::where('telephone', $telephone)->first();

        if (! $otp || ! $otp->estValide($request->code) || ! $user || ! $this->peutReinitialiser($user)) {
            RateLimiter::hit($cle, 600);

            return response()->json(['success' => false, 'message' => 'Code incorrect ou expiré.'], 422);
        }

        RateLimiter::clear($cle);
        $otp->update(['utilise' => true]);

        $user->forceFill(['password' => $request->password])->save();

        // Tous les appareils déjà connectés sont déconnectés ; celui-ci reçoit un nouveau jeton.
        $user->tokens()->delete();

        return $this->reponseConnexion($user);
    }

    /** Seuls les comptes voyageurs actifs et vérifiés : le personnel passe par l'administration. */
    private function peutReinitialiser(User $user): bool
    {
                return $user->telephone_verifie
            && ($user->actif ?? true)
            && ! $user->aUnRoleStaff();
    }

    /** Le client corrige son prénom et son nom (le numéro de téléphone ne se change pas ici). */
    public function modifierProfil(Request $request)
    {
        $data = $request->validate([
            'prenom' => 'required|string|min:2|max:100',
            'nom' => 'required|string|min:2|max:100',
        ]);

        $user = $request->user();
        $user->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'user' => [
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'telephone' => $user->telephone,
            ],
        ]);
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

    /** Envoie réellement le code. Renvoie false si WhatsApp n'est pas configuré ou refuse l'envoi. */
    private function envoyerOtp(string $telephone, string $code): bool
    {
        if (! config('services.whatsapp.token')) {
            report(new \RuntimeException('WhatsApp n\'est pas configuré : aucun code de vérification ne peut être envoyé.'));

            return false;
        }

        try {
            app(WhatsAppService::class)->envoyerOtp($telephone, $code);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function echecEnvoiCode()
    {
        return response()->json([
            'success' => false,
            'message' => 'Impossible d\'envoyer le code par WhatsApp pour le moment. Réessayez dans quelques instants.',
        ], 503);
    }
}
