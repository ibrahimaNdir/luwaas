<?php

use App\Http\Controllers\VerificationPaiementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// ── Vérification publique de quittance via QR code (aucune auth requise)
Route::get('/verifier/paiement/{token}', [VerificationPaiementController::class, 'verifier'])
    ->name('paiement.verifier');
