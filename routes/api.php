<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\GeoController;
use App\Http\Controllers\API\LogementController;
use App\Http\Controllers\API\PropertyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 🧪 ROUTE DE TEST - À SUPPRIMER APRÈS
Route::post('/test-201', function() {
    return response()->json(['test' => 'ok'], 201);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});



Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/logements', [LogementController::class, 'index']);
    Route::get('/proprietes', [PropertyController::class, 'index']);


});

Route::middleware(['auth:sanctum','proprietaire'])->prefix('proprietaire')->group(function () {
    Route::get('/regions', [GeoController::class, 'regions']);
    Route::get('/regions/{id}/departements', [GeoController::class, 'departements']);
    Route::get('/departements/{id}/communes', [GeoController::class, 'communes']);

    Route::get('/dashboard', [PropertyController::class, 'dashboard']);
    // Les proprietes du Bailleur
    Route::get('/proprietes', [PropertyController::class, 'allProperty']);
    // Ajouter  Propriete
    Route::post('/proprietes', [PropertyController::class, 'store']);
    // Mise a jour d'une propriete
    Route::put('/proprietes/{id}', [PropertyController::class, 'update']);
    // Supprimer une propriete
    Route::delete('/proprietes/{id}', [PropertyController::class, 'destroy']);
    // Le nombre de propriete du Bailleur
    Route::get('/proprietes/count', [PropertyController::class, 'countProperty']);
    // reccher des proprietes
    Route::get('/proprietes/search', [PropertyController::class, 'search']);


    // Ajouter un Logement lier a une propriete
    Route::post('/proprietes/{proprieteId}/logements', [LogementController::class, 'store']);
    // Faire La mise a jour d'un logement
    Route::put('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'update']);
    // Supprimer un Logement d'une propriete
    Route::delete('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'destroy']);
    Route::get('/logements/search', [LogementController::class, 'search']);
    // Liste de tout les logements d'une propriete
    Route::get('/proprietes/{proprieteId}/logements', [LogementController::class, 'indexByPropriete']);
    // Le nombre de logement d'une propriete
    Route::get('/proprietes/{proprieteId}/logements/count', [LogementController::class, 'countByPropriete']);
    // route qui permet de publier ton logement
    Route::patch('/proprietes/{proprieteId}/logements/{id}/status', [LogementController::class, 'updateStatusPublication']);

    Route::post('/proprietes/{proprieteId}/logements/{id}/photos', [LogementController::class, 'addPhotos']);

    Route::get('/mes-logements/publies', [LogementController::class, 'getPublishedLogementsByProprietaire']);

    Route::get('/demandes', [DemandeController::class, 'demandesProprietaire']);



});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->prefix('locataire')->group(function () {
    Route::get('/logements/nearby', [LogementController::class, 'nearby']);
    Route::get('/logements/search', [LogementController::class, 'searchzone']);
    Route::post('/demandes', [DemandeController::class, 'store']); // <-- POST pour créer une demande
    Route::get('/demandes', [DemandeController::class, 'demandesLocataire']); // <-- GET pour l’historique du locatair
    //Route::get('/logements/{id}', [LogementController::class, 'show']);
});




