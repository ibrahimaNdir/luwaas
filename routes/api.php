<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BailController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\GeoController;
use App\Http\Controllers\API\LogementController;
use App\Http\Controllers\API\PaiementController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\WebhookController;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Luwaas
|--------------------------------------------------------------------------
*/

// ============================================
// 🌍 ROUTES PUBLIQUES (MODE GUEST)
// ============================================

// Recherche et liste des logements (accessibles sans connexion)
Route::get('/logements/nearby', [LogementController::class, 'nearby']);
Route::get('/logements/search', [LogementController::class, 'searchzone']);
Route::get('/logements/{id}', [LogementController::class, 'show']);

// Auth publique
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

// ============================================
// 🌐 WEBHOOKS (SANS AUTH - Appelés par Wave/OM/PayPal)
// ✅ NOUVELLES ROUTES
// ============================================

Route::post('/webhook/paydunya', [WebhookController::class, 'handlePaydunya']);
Route::post('/webhook/paypal', [WebhookController::class, 'handlePaypal']);

// ============================================
// 🔐 ROUTES COMMUNES (Tous utilisateurs connectés)
// ============================================

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ============================================
// 👑 ROUTES ADMIN
// ============================================

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/logements', [LogementController::class, 'index']);
    Route::get('/proprietes', [PropertyController::class, 'index']);
    Route::get('/baux', [BailController::class, 'index']);
    Route::get('/demande', [DemandeController::class, 'index']);
    Route::get('/paiement', [PaiementController::class, 'index']);
    Route::get('/users', [AuthController::class, 'index']);
});

// ============================================
// 🏠 ROUTES PROPRIETAIRE (BAILLEUR)
// ============================================

Route::middleware(['auth:sanctum', 'proprietaire'])->prefix('proprietaire')->group(function () {

    // Géolocalisation
    Route::get('/regions', [GeoController::class, 'regions']);
    Route::get('/regions/{id}/departements', [GeoController::class, 'departements']);
    Route::get('/departements/{id}/communes', [GeoController::class, 'communes']);

    // Dashboard
    Route::get('/dashboard', [PropertyController::class, 'dashboard']);
    Route::get('/stats-proprietes', [PropertyController::class, 'statsProprietes']);

    // Propriétés (routes spécifiques AVANT les génériques)
    Route::get('/proprietes/count', [PropertyController::class, 'countProperty']);
    Route::get('/proprietes/search', [PropertyController::class, 'search']);
    Route::get('/proprietes', [PropertyController::class, 'allProperty']);
    Route::post('/proprietes', [PropertyController::class, 'store']);
    Route::put('/proprietes/{id}', [PropertyController::class, 'update']);
    Route::delete('/proprietes/{id}', [PropertyController::class, 'destroy']);

    // Logements d'une propriété
    Route::post('/proprietes/{proprieteId}/logements', [LogementController::class, 'store']);
    Route::put('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'update']);
    Route::delete('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'destroy']);
    Route::get('/proprietes/{proprieteId}/logements', [LogementController::class, 'indexByPropriete']);
    Route::get('/proprietes/{proprieteId}/logements/count', [LogementController::class, 'countByPropriete']);

    // Publication et photos
    Route::patch('/proprietes/{proprieteId}/logements/{id}/status', [LogementController::class, 'updateStatusPublication']);
    Route::post('/proprietes/{proprieteId}/logements/{id}/photos', [LogementController::class, 'addPhotos']);
    Route::get('/mes-logements/publies', [LogementController::class, 'getPublishedLogementsByProprietaire']);

    // Gestion des demandes
    Route::get('/demandes', [DemandeController::class, 'demandesProprietaire']);
    Route::patch('/demandes/{id}/accepter', [DemandeController::class, 'accepter']);
    Route::patch('/demandes/{id}/refuser', [DemandeController::class, 'refuser']);

    // ═══════════════════════════════════════════════════════════
    // BAUX (Unification bails → baux)
    // ═══════════════════════════════════════════════════════════
    Route::post('/baux', [BailController::class, 'store']);
    Route::get('/baux', [BailController::class, 'bauxBailleur']);
    Route::get('/baux/{id}', [BailController::class, 'show']);
    Route::get('/baux/{id}/pdf', [BailController::class, 'exportPdf']);
    Route::delete('/baux/{id}', [BailController::class, 'destroy']);

    // ═══════════════════════════════════════════════════════════
    // PAIEMENTS (Côté Bailleur)
    // ✅ NOUVELLE ROUTE
    // ═══════════════════════════════════════════════════════════
    Route::get('/paiements', [PaiementController::class, 'paiementsProprietaire']);
});

// ============================================
// 🏡 ROUTES LOCATAIRE
// ============================================

Route::middleware(['auth:sanctum', 'locataire'])->prefix('locataire')->group(function () {

    // ═══════════════════════════════════════════════════════════
    // DEMANDES DE LOCATION
    // ═══════════════════════════════════════════════════════════
    Route::post('/demandes', [DemandeController::class, 'store']);
    Route::get('/demandes', [DemandeController::class, 'demandesLocataire']);
    Route::delete('/demandes/{id}', [DemandeController::class, 'destroy']);
    Route::patch('/demandes/{id}/annuler', [DemandeController::class, 'annuler']);

    // ═══════════════════════════════════════════════════════════
    // LOGEMENTS
    // ═══════════════════════════════════════════════════════════
    Route::get('/logements', [LogementController::class, 'logementsLocataire']);

    // ═══════════════════════════════════════════════════════════
    // BAUX
    // ✅ NOUVELLE ROUTE
    // ═══════════════════════════════════════════════════════════
    Route::get('/bail-en-attente', [BailController::class, 'getBailEnAttente']); // ⭐ NOUVEAU
    Route::get('/baux', [BailController::class, 'bauxLocataire']);
    Route::get('/baux/{id}', [BailController::class, 'show']);
    Route::get('/baux/{id}/pdf', [BailController::class, 'exportPdf']);

    // ═══════════════════════════════════════════════════════════
    // PAIEMENTS (Consultation)
    // ✅ NOUVELLES ROUTES
    // ═══════════════════════════════════════════════════════════
    Route::get('/paiements', [PaiementController::class, 'index']);
    Route::get('/paiements/stats', [PaiementController::class, 'statistiques']);



    // ═══════════════════════════════════════════════════════════
    // TRANSACTIONS (Mobile Money)
    // ✅ TOUTES NOUVELLES ROUTES
    // ═══════════════════════════════════════════════════════════
    Route::get('/transactions', [TransactionController::class, 'index']); // ⭐ NOUVEAU
});

// ============================================
// 💳 ROUTES PAIEMENTS & TRANSACTIONS (Locataire)
// ✅ TOUTES NOUVELLES
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    // ═══════════════════════════════════════════════════════════
    // PAIEMENTS (Routes communes - accessible locataire)
    // ═══════════════════════════════════════════════════════════
    

    
    // Liste des paiements(payer et non payés) d'un bail spécifique
    Route::get('/baux/{bailId}/paiements', [PaiementController::class, 'paiementsBail']); // ⭐ NOUVEAU
    // Paiement à régler (liste des impayés) d'un bail spécifique
    Route::get('/baux/{bailId}/paiement-a-regler', [PaiementController::class, 'paiementARegler']); // ⭐ NOUVEAU
    // qui nous redrige vers la route de paiement mobile money (initierPaiement)
    Route::get('/paiements/{id}', [PaiementController::class, 'show']);

    // ═══════════════════════════════════════════════════════════
    // TRANSACTIONS - INITIER PAIEMENT (LA PLUS IMPORTANTE)
    // ✅ TOUTES NOUVELLES ROUTES
    // ═══════════════════════════════════════════════════════════
    Route::post('/paiements/{paiementId}/payer', [TransactionController::class, 'initierPaiement']); // ⭐⭐⭐ CRITIQUE

    // Consultation transactions
    Route::get('/transactions/{id}', [TransactionController::class, 'show']); // ⭐ NOUVEAU
    Route::get('/transactions/{id}/statut', [TransactionController::class, 'verifierStatut']); // ⭐ NOUVEAU

    // Gestion transactions
    Route::delete('/transactions/{id}', [TransactionController::class, 'annuler']); // ⭐ NOUVEAU
    Route::post('/transactions/{id}/relancer', [TransactionController::class, 'relancer']); // ⭐ NOUVEAU
});

// ✅ Webhook public — PayDunya appelle directement
//Route::post('/webhook/paydunya', [TransactionController::class, 'webhookPaydunya']);

// ============================================
// 🧪 ROUTE DE TEST (À SUPPRIMER EN PRODUCTION)
// ============================================

Route::middleware(['auth:sanctum', 'admin'])->get('/test-firestore/{userId}', function ($userId, NotificationService $notifService) {
    $user = User::find($userId);

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    $notif = $notifService->sendToUser(
        $user,
        "Test Notification Firestore",
        "Si tu vois ça dans Firebase Console > Firestore, c'est gagné !",
        "test"
    );

    return response()->json([
        'message' => 'Envoyé !',
        'mysql_notif' => $notif
    ]);
});
