<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BailController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\GeoController;
use App\Http\Controllers\API\LogementController;
use App\Http\Controllers\API\PaiementController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\TransactionController;
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
    Route::get('/baux', [BailController::class, 'index']);
    Route::get('/demande', [DemandeController::class, 'index']);
    Route::get('/paiement', [PaiementController::class, 'index']);
    Route::get('/users', [AuthController::class, 'index']);



});
// Route qui appartiennent le Proprietaire

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

    // Route qui te permet d'ajouter tes photos
    Route::post('/proprietes/{proprieteId}/logements/{id}/photos', [LogementController::class, 'addPhotos']);
    // Route qui te permet de voir tous lesogement que t'as publier
    Route::get('/mes-logements/publies', [LogementController::class, 'getPublishedLogementsByProprietaire']);

    // Route qui affiches tous les demandes faite par les Locataire
    Route::get('/demandes', [DemandeController::class, 'demandesProprietaire']);

    // Route qui te permet de creer les bail
    Route::post('/baux', [BailController::class, 'store']);

    // Route qui te permet d'afficher tous la bail que t'as creer
    Route::get('/baux', [BailController::class, 'bauxBailleur']);

    // Details Bails
    Route::get('/baux/{id}', [BailController::class, 'show']);

    // Supprimer Bail
    Route::delete('/bails/{id}', [BailController::class, 'destroy']);

    Route::get('paiements',[PaiementController::class, 'paiementsForBailleur']);

    Route::get('/baux/{bailId}/paiements/id ',[PaiementController::class, 'recuLoyer ']);

    Route::post('/transactions/{transaction}/valider-especes', [PaiementController::class, 'validerEspeces']);





});
// Les Routes qui appartient le Locataire

// Routes protégées par authentification
Route::middleware('auth:sanctum')->prefix('locataire')->group(function () {

    // Recherche a partir de ta position
    Route::get('/logements/nearby', [LogementController::class, 'nearby']);

    // Recherche a partir d'une zone
    Route::get('/logements/search', [LogementController::class, 'searchzone']);

    // Faire demande de Location
    Route::post('/demandes', [DemandeController::class, 'store']); //

    // Afficher tous les demande que t'as faite
    Route::get('/demandes', [DemandeController::class, 'demandesLocataire']);

    //Route::get('/logements/{id}', [LogementController::class, 'show']);

    // Liste tous les Bails qui te concerne
    Route::get('/baux', [BailController::class, 'bauxLocataire']);

    // details du bails
     Route::get('/baux/{id}', [BailController::class, 'show']);

    // Route qui te permet de lister tous les bail pour ensuite selectionner l'un des bail et payer
    Route::get('/bauxpaie',[BailController::class, 'bauxForLocataire']);

    // Route qui te redrige de payer ton location
    Route::get('/bail/{bailId}/paiements',[PaiementController::class, 'indexByPaiement']);

    Route::get('/bail/{bailId}/paiements/{id}', [PaiementController::class, 'detailPaiement']);

    Route::post('/paiements/{paiement}/', [PaiementController::class, 'payerEspeces']);







    // Route::get('/baux/{id}/export-pdf', [BailController::class, 'exportPdf']);

});




