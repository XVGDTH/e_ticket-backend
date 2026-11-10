<?php

use App\Http\Controllers\PieceComptableController;
use App\Http\Controllers\PieceFactureFournisseurController;
use App\Http\Controllers\PieceFlotteController;

Route::get('/compta/pieces/{ecriture}/{index}', [PieceComptableController::class, 'show'])
    ->middleware('auth')
    ->whereNumber(['ecriture', 'index'])
    ->name('compta.piece');

Route::get('/flotte/pieces/{depense}', [PieceFlotteController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('depense')
    ->name('flotte.piece');

Route::get('/cycle-fournisseurs/factures/{facture}/piece', [PieceFactureFournisseurController::class, 'show'])
    ->middleware('auth')
    ->whereNumber('facture')
    ->name('cycle-fournisseurs.piece');

require __DIR__.'/personnel.php';
require __DIR__.'/guichet.php';
