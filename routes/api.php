<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BailController;
use App\Http\Controllers\API\DemandeController;
use App\Http\Controllers\API\ProprietaireDashboardController;
use App\Http\Controllers\API\GeoController;
use App\Http\Controllers\API\LogementController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\Admin\AdminController;
use App\Http\Controllers\API\Admin\AdminProprietaireController;
use App\Http\Controllers\API\Admin\AdminTransactionController;
use App\Http\Controllers\API\Admin\AdminSubscriptionController;
use App\Http\Controllers\API\LocataireDashboardController;
use App\Http\Controllers\API\Admin\AdminLogementController;
use App\Http\Controllers\API\Admin\AdminPropertyController;
use App\Http\Controllers\API\Admin\AdminBailController;
use App\Http\Controllers\API\Admin\AdminDemandeController;
use App\Http\Controllers\API\Admin\AdminUserController;
use App\Http\Controllers\API\Admin\AdminPaymentController;
use App\Http\Controllers\API\Admin\AdminTicketController;
use App\Http\Controllers\API\TicketController;

use App\Http\Controllers\API\PayoutController; // Added

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ============================================
// 🌍 ROUTES PUBLIQUES
// ============================================

Route::get('/logements/nearby', [LogementController::class, 'nearby']);
Route::get('/logements/search', [LogementController::class, 'searchzone']);
Route::get('/logements', [LogementController::class, 'indexPublic']);
Route::get('/logements/{id}', [LogementController::class, 'showPublic']);

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/verify-otp', 'verifyOtp');
    Route::post('/resend-otp', 'resendOtp');
});

// ============================================
// 🌐 WEBHOOKS (SANS AUTH — PayDunya appelle sans token)
// ============================================

Route::post('/webhook/paydunya', [WebhookController::class, 'handle']);
Route::post('/webhook/bictorys', [WebhookController::class, 'handle']);

// ============================================
// 🔐 ROUTES COMMUNES (auth uniquement)
// ============================================

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    // Plans (visible par tout utilisateur authentifié)
    Route::get('/plans', [PaymentController::class, 'plans']);

    // Paiements bail/loyer (commun locataire + proprietaire)
    Route::get('/baux/{bailId}/paiements', [PaymentController::class, 'paiementsBail']);
    Route::get('/baux/{bailId}/paiement-a-regler', [PaymentController::class, 'paiementARegler']);
    Route::get('/paiements/{id}', [PaymentController::class, 'show']);
    Route::get('/paiements/{id}/quittance-pdf', [PaymentController::class, 'exportQuittancePdf']);

    // Transactions (commun)
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::get('/transactions/{id}/statut', [TransactionController::class, 'verifierStatut']);
    // Route::post('/transactions/{id}/relancer', [PaymentController::class, 'relancer']);
    // Route::post('/transactions/{id}/annuler', [PaymentController::class, 'annulerTransaction']);

    // Tickets de support (locataires + propriétaires)
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    // Route::post('/transactions/{id}/annuler', [PaymentController::class, 'annurerTransaction']);
});

// ============================================
// 👑 ROUTES ADMIN
// ============================================

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);

        Route::get('/logements', [AdminLogementController::class, 'index']);
        Route::get('/logements/{id}', [AdminLogementController::class, 'show']);

        Route::get('/proprietes', [AdminPropertyController::class, 'index']);
        Route::get('/proprietes/{id}', [AdminPropertyController::class, 'show']);

        Route::get('/baux', [AdminBailController::class, 'index']);
        Route::get('/baux/{id}', [AdminBailController::class, 'show']);

        Route::get('/demandes', [AdminDemandeController::class, 'index']);
        Route::get('/demandes/{id}', [AdminDemandeController::class, 'show']);

        Route::get('/paiements', [AdminPaymentController::class, 'index']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);

        Route::get('/proprietaires', [AdminProprietaireController::class, 'index']);
        Route::get('/proprietaires/{id}', [AdminProprietaireController::class, 'show']);
        Route::patch('/proprietaires/{id}/activate', [AdminProprietaireController::class, 'activate']);
        Route::patch('/proprietaires/{id}/suspend', [AdminProprietaireController::class, 'suspend']);

        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/transactions/summary', [AdminTransactionController::class, 'summary']);
        Route::get('/transactions/{id}', [AdminTransactionController::class, 'show']);

        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::get('/plans', [AdminSubscriptionController::class, 'plans']);
        Route::patch('/subscriptions/{proprietaireId}/change-plan', [AdminSubscriptionController::class, 'changePlan']);
        Route::patch('/subscriptions/{proprietaireId}/cancel', [AdminSubscriptionController::class, 'cancel']);

        // Tickets de support
        Route::get('/tickets/stats', [AdminTicketController::class, 'stats']);
        Route::get('/tickets', [AdminTicketController::class, 'index']);
        Route::get('/tickets/{id}', [AdminTicketController::class, 'show']);
        Route::post('/tickets/{id}/repondre', [AdminTicketController::class, 'repondre']);
        Route::patch('/tickets/{id}/fermer', [AdminTicketController::class, 'fermer']);
        // LOCATAIRE SUSPEND / ACTIVATE
        Route::patch('/locataires/{id}/suspend', [AdminLocataireController::class, 'suspend']);
        Route::patch('/locataires/{id}/activate', [AdminLocataireController::class, 'activate']);
    });

// ============================================
// 🏠 ROUTES PROPRIETAIRE
// ============================================

// Sans subscribed → gestion abonnement (le bailleur doit pouvoir payer même sans abonnement)
Route::middleware(['auth:sanctum', 'proprietaire'])->group(function () {
    Route::get('/abonnements/statut',      [TransactionController::class, 'statutAbonnement']);
    Route::post('/abonnements/initier',    [PaymentController::class, 'initierAbonnement']);
    Route::post('/abonnements/annuler',    [PaymentController::class, 'annilerAbonnement']);
    Route::post('/abonnements/renouveler', [PaymentController::class, 'renouvelerAbonnement']);
    //Route::get('/abonnements/historique',  [TransactionController::class, 'indexProprietaire']);
});

// Avec subscribed → accès backoffice complet
Route::middleware(['auth:sanctum', 'proprietaire', 'subscribed'])
    ->prefix('proprietaire')
    ->group(function () {

        // Géolocalisation
        Route::get('/regions', [GeoController::class, 'regions']);
        Route::get('/regions/{id}/departements', [GeoController::class, 'departements']);
        Route::get('/departements/{id}/communes', [GeoController::class, 'communes']);

        // Dashboard
        Route::get('/dashboard', [ProprietaireDashboardController::class, 'proprietaire']);

        // Propriétés
        Route::get('/proprietes/count', [PropertyController::class, 'countProperty']);
        Route::get('/proprietes/search', [PropertyController::class, 'search']);
        Route::get('/proprietes', [PropertyController::class, 'allProperty']);
        Route::post('/proprietes', [PropertyController::class, 'store']);
        Route::put('/proprietes/{id}', [PropertyController::class, 'update']);
        Route::delete('/proprietes/{id}', [PropertyController::class, 'destroy']);
        Route::get('/proprietes/{id}', [PropertyController::class, 'show']);

        // Logements
        Route::post('/proprietes/{proprieteId}/logements', [LogementController::class, 'store'])
            ->middleware('check.publication');
        Route::put('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'updateInfos']);
        Route::delete('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'destroy']);
        Route::get('/proprietes/{proprieteId}/logements', [LogementController::class, 'indexByPropriete']);
        Route::get('/proprietes/{proprieteId}/logements/count', [LogementController::class, 'countByPropriete']);

        Route::get('/mes-logements', [LogementController::class, 'getAllLogementsByProprietaire']);

        Route::get('/mes-logements/{id}', [LogementController::class, 'show']);

        Route::get('/proprietes/{proprieteId}/logements/{id}', [LogementController::class, 'showByPropriete']);


        Route::patch(
            '/proprietes/{proprieteId}/logements/{id}/status',
            [LogementController::class, 'updateStatusPublication']
        );

        Route::patch(
            '/proprietes/{proprieteId}/logements/{id}/highlight',
            [LogementController::class, 'toggleHighlight']
        )
            ->middleware('feature:Mise en avant des logements');

        Route::post('/proprietes/{proprieteId}/logements/{id}/photos', [LogementController::class, 'addPhotos']);
        Route::get('/mes-logements/publies', [LogementController::class, 'getPublishedLogementsByProprietaire']);

        // Dashboard & Stats
        Route::get('/proprietaire/dashboard', [ProprietaireDashboardController::class, 'proprietaire']);

        // Stats optionnelles
        Route::get('/proprietaire/stats/historique-6-mois', [ProprietaireDashboardController::class, 'historico6Mois']);
        Route::get('/proprietaire/stats/par-propriete', [ProprietaireDashboardController::class, 'statsParPropriete']);

        // Rapports Financiers
        Route::get('/rapports/financiers', [\App\Http\Controllers\API\ProprietaireReportController::class, 'getFinances'])
            ->middleware('feature:Rapports financiers avancés');
        Route::get('/rapports/financiers/export', [\App\Http\Controllers\API\ProprietaireReportController::class, 'exportFinances'])
            ->middleware('feature:Export Excel');

        // Demandes
        Route::get('/demandes', [DemandeController::class, 'demandesProprietaire']);
        Route::patch('/demandes/{id}/accepter', [DemandeController::class, 'accepter']);
        Route::patch('/demandes/{id}/refuser', [DemandeController::class, 'refuser']);

        // Baux
        Route::post('/baux', [BailController::class, 'store']);
        Route::get('/baux', [BailController::class, 'bauxBailleur']);
        Route::get('/baux/{id}', [BailController::class, 'show']);
        Route::get('/baux/{id}/pdf', [BailController::class, 'exportPdf']);
        Route::delete('/baux/{id}', [BailController::class, 'destroy']);

        // Paiements loyers
        Route::get('/paiements', [PaymentController::class, 'paiementsProprietaire']);
        Route::patch('/paiements/{id}/manuel', [PaymentController::class, 'markAsPaidManually']);

        // Score Locataire (NOUVEAU)
        Route::get('/locataires/{id}/score', [\App\Http\Controllers\API\LocataireController::class, 'voirScoreProprietaire']);

        // Versements
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
        Route::get('/earnings', [PayoutController::class, 'currentEarnings']);

        // LOCATAIRE LIST
        Route::get('/locataires', [PropertyController::class, 'allLocataires']);
    });

// ============================================
// 🏡 ROUTES LOCATAIRE
// ============================================

Route::middleware(['auth:sanctum', 'locataire'])->prefix('locataire')->group(function () {

    // Dashboard
    Route::get('/dashboard', [LocataireDashboardController::class, 'index']);

    // Demandes
    Route::post('/demandes', [DemandeController::class, 'store']);
    Route::get('/demandes', [DemandeController::class, 'demandesLocataire']);
    Route::delete('/demandes/{id}', [DemandeController::class, 'destroy']);
    Route::patch('/demandes/{id}/annuler', [DemandeController::class, 'annuler']);

    // Logements
    Route::get('/logements', [LogementController::class, 'logementsLocataire']);

    // Baux
    Route::get('/bail-en-attente', [BailController::class, 'getBailEnAttente']);
    Route::get('/baux', [BailController::class, 'bauxLocataire']);
    Route::get('/baux/{id}', [BailController::class, 'show']);
    Route::get('/baux/{id}/pdf', [BailController::class, 'exportPdf']);

    // Paiements loyers
    Route::get('/paiements', [PaymentController::class, 'index']);
    Route::get('/paiements/stats', [PaymentController::class, 'statistiques']);
    Route::post('/paiements/{id}/initier', [PaymentController::class, 'initierLoyer']);

    // Score (NOUVEAU)
    Route::get('/mon-score', [\App\Http\Controllers\API\LocataireController::class, 'voirMonScore']);

    // Transactions loyers
    Route::get('/transactions', [TransactionController::class, 'indexLocataire']);
});

// ============================================
// 🧪 ROUTE DE TEST & ADMIN
// ============================================

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/test-firestore/{userId}', function ($userId, \App\Services\NotificationService $notifService) {
        $user = \App\Models\User::find($userId);
        if (!$user) return response()->json(['error' => 'User not found'], 404);

        $notif = $notifService->sendToUser(
            $user,
            "Test Notification Firestore",
            "Si tu vois ça dans Firebase Console > Firestore, c'est gagné !",
            "test"
        );

        return response()->json(['message' => 'Envoyé !', 'mysql_notif' => $notif]);
    });

    // NOUVEAU : Mécanisme #4 - Ratio paiements (Admin)
    Route::prefix('admin')->group(function () {
        Route::get('/bailleurs/ratio-paiements', [\App\Http\Controllers\API\AdminDashboardController::class, 'ratioPaiements']);
    });
  });
});
