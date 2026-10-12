<?php

use App\Http\Controllers\Api\Agent\AgentAuthController;
use App\Http\Controllers\Api\Agent\AgentBilletController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BilletController;
use App\Http\Controllers\Api\CompagnieController;
use App\Http\Controllers\Api\RouteTransportController;
use App\Http\Middleware\EnsureAgentGare;
use Illuminate\Support\Facades\Route;

// ---------- Public (limité en débit) ----------
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// ---------- Clients (application voyageurs) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/compagnies', [CompagnieController::class, 'index']);
    Route::get('/compagnies/{compagnie}/routes', [RouteTransportController::class, 'index']);
    Route::post('/billets', [BilletController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/billets', [BilletController::class, 'mesBillets']);
    Route::get('/me', fn (\Illuminate\Http\Request $r) => $r->user());
});

// ---------- Agents de gare (application des agents) ----------
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

        Route::get('/departs', [AgentBilletController::class, 'departs'])->name('departs');
    });
});