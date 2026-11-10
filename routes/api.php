<?php

use App\Http\Controllers\Api\Agent\AgentAuthController;
use App\Http\Controllers\Api\Agent\AgentBilletController;
use App\Http\Controllers\Api\AssistanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvisGareController;
use App\Http\Controllers\Api\BilletController;
use App\Http\Controllers\Api\CompagnieController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PreferencesClientController;
use App\Http\Controllers\Api\RouteTransportController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Middleware\EnsureAgentGare;
use Illuminate\Support\Facades\Route;

// ---------- Public (limité en débit) ----------

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:5,1');

// mot de passe oublié
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// ---------- Voyageurs (application mobile) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // les nouvelles routes marketing et whatsapp
    Route::get('/me/marketing', [MarketingController::class, 'etat']);
    Route::post('/me/marketing', [MarketingController::class, 'definir'])->middleware('throttle:20,1');
    Route::get('/me/preferences', [PreferencesClientController::class, 'index']);

    Route::get('/compagnies', [CompagnieController::class, 'index']);
    Route::get('/compagnies/{compagnie:slug}/routes', [RouteTransportController::class, 'index']);
    Route::get('/compagnies/{compagnie:slug}/villes-depart', [CompagnieController::class, 'villesDepart']);
    Route::post('/billets', [BilletController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/billets', [BilletController::class, 'mesBillets']);

    // routes pour le receuil des avis des passagers
    Route::post('/me/profil', [AuthController::class, 'modifierProfil'])->middleware('throttle:20,1');
    Route::post('/avis-gares', [AvisGareController::class, 'store'])->middleware('throttle:20,1');

    // nouvelles riutes pour le reporting des billets des passagers et la confirmation des passagers embarqués
    Route::get('/billets/{reference}/creneaux', [BilletController::class, 'creneauxDeReport']);
    Route::post('/billets/{reference}/reporter', [BilletController::class, 'reporter'])->middleware('throttle:20,1');

    // les references des billets
    Route::post('/billets/{reference}/presence', [BilletController::class, 'presence'])->middleware('throttle:30,1');

    // les routes pour l'aide et l'assistance
    Route::get('/assistance/contact', [AssistanceController::class, 'contact']);
    Route::get('/assistance/faq', [AssistanceController::class, 'faq']);
    Route::get('/assistance/demandes', [AssistanceController::class, 'index']);
    Route::post('/assistance/demandes', [AssistanceController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/assistance/demandes/{id}', [AssistanceController::class, 'show'])->whereNumber('id');
    Route::post('/assistance/demandes/{id}/messages', [AssistanceController::class, 'repondre'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/assistance/demandes/{id}/note', [AssistanceController::class, 'noter'])->whereNumber('id')->middleware('throttle:10,1');

    // routes des preferences
    Route::get('/me/preferences', [PreferencesClientController::class, 'index']);

});

// ---------- Agents de gare (non utilisé : les agents passent par le panel Filament) ----------
Route::prefix('agent')->name('agent.')->group(function () {
    Route::post('/login', [AgentAuthController::class, 'login'])->middleware('throttle:20,1')->name('login');

    Route::middleware(['auth:sanctum', EnsureAgentGare::class])->group(function () {
        Route::get('/me', [AgentAuthController::class, 'me'])->name('me');
        Route::post('/logout', [AgentAuthController::class, 'logout'])->name('logout');
        Route::post('/mot-de-passe', [AgentAuthController::class, 'motDePasse'])->middleware('throttle:5,1')->name('mot-de-passe');

        Route::get('/billets', [AgentBilletController::class, 'index'])->name('billets.index');
        Route::post('/billets/verifier', [AgentBilletController::class, 'verifier'])->middleware('throttle:120,1')->name('billets.verifier');
        Route::get('/billets/{reference}', [AgentBilletController::class, 'show'])->name('billets.show');
        Route::post('/billets/{reference}/valider', [AgentBilletController::class, 'valider'])->middleware('throttle:60,1')->name('billets.valider');

        Route::post('/billets/{reference}/presence', [BilletController::class, 'presence'])->middleware('throttle:30,1');

        Route::get('/departs', [AgentBilletController::class, 'departs'])->name('departs');
        // les routes pour les notifs
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/lire-tout', [NotificationController::class, 'toutLire']);
        Route::post('/notifications/{notification}/lue', [NotificationController::class, 'lire']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // les routes pour l'algorithme de validation des preferences des users
        Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verifier']);
        Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'recevoir']);

    });
});

// """""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""
// les routes publiques marketing et webhook de whatsap
// """""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verifier']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'recevoir']);
