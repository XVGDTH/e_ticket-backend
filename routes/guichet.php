<?php

use App\Http\Controllers\GuichetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('guichet')->name('guichet.')->group(function () {
    Route::get('billets/{id}', [GuichetController::class, 'billet'])->whereNumber('id')->name('billet');
    Route::get('manifeste', [GuichetController::class, 'manifeste'])->name('manifeste');
    Route::get('caisses/{id}', [GuichetController::class, 'caisse'])->whereNumber('id')->name('caisse');
});
