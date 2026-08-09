<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Envoyer une notification push (Broadcast) à un groupe d'utilisateurs
     */
    public function broadcast(Request $request)
    {
        $request->validate([
            'cible'   => 'required|in:tous,proprietaires,locataires',
            'titre'   => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        $query = User::query()->where('is_active', true);

        if ($request->cible === 'proprietaires') {
            $query->where('user_type', 'proprietaire');
        } elseif ($request->cible === 'locataires') {
            $query->where('user_type', 'locataire');
        }

        $users = $query->get();

        $nbEnvoyes = $this->notificationService->sendToMultipleUsers(
            $users->all(),
            $request->titre,
            $request->message,
            'broadcast_admin',
            ['broadcast_by_admin' => true]
        );

        return response()->json([
            'success' => true,
            'message' => "Notification envoyée avec succès à {$nbEnvoyes} utilisateur(s).",
            'total_cible' => count($users),
            'total_succes' => $nbEnvoyes,
        ]);
    }
}
