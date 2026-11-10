<?php

use App\Http\Controllers\PersonnelDocumentsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('personnel')->name('personnel.')->group(function () {
    Route::get('bulletins/{id}', [PersonnelDocumentsController::class, 'bulletin'])->whereNumber('id')->name('bulletin');
    Route::get('employes/{id}/attestation', [PersonnelDocumentsController::class, 'attestation'])->whereNumber('id')->name('attestation');
});
