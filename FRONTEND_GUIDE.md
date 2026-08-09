# Guide de Cohérence Front-End / Back-End (Luwaas)

Ce document définit les standards pour garantir que le front-end consomme l'API de manière cohérente et robuste.

## 1. Standardisation des Réponses API
Chaque réponse API doit impérativement respecter cette structure pour simplifier le traitement côté front :

**Succès :**
```json
{
  "success": true,
  "message": "Opération réussie.", // Optionnel
  "data": { ... } // Les objets retournés
}
```

**Erreur :**
```json
{
  "success": false,
  "message": "Message d'erreur compréhensible par l'utilisateur.",
  "errors": { ... } // Optionnel (pour les validations Laravel 422)
}
```

## 2. Stratégie de Notifications (FCM + Firestore)
Le front-end doit gérer deux flux de notifications :
*   **FCM (Push) :** Utilisé pour l'alerte immédiate (le "ding" sonore). Le front doit simplement écouter les événements Firebase (`onMessage`).
*   **Firestore (Feed) :** Utilisé pour afficher l'historique dans le dashboard. 
    *   **Logique :** Quand une notif FCM arrive, le front-end doit déclencher un rafraîchissement de la liste Firestore pour garantir la mise à jour en temps réel sans recharger la page.

## 3. Flux d'Authentification (OTP)
1. **Login/Register** -> Réception `temporary_token` ou `auth_token`.
2. **OTP** -> POST `/api/auth/verify-otp`.
3. **Persistance** -> Le front doit stocker le token dans un `HttpOnly Cookie` ou `LocalStorage` (avec précaution) pour les requêtes authentifiées avec Sanctum.

## 4. Gestion des États du Métier
Pour les processus complexes (Demandes, Baux, Paiements), le front-end doit toujours se baser sur le **statut** retourné par l'API pour afficher les boutons d'action :
*   Si `status == 'en_attente'`, afficher boutons "Accepter/Refuser".
*   Si `status == 'acceptee'`, afficher bouton "Créer Bail".
*   **NE JAMAIS** coder la logique métier dans le front-end. Le front-end doit juste refléter l'état envoyé par le back-end.

## 5. Gestion des Erreurs
*   **401 :** Redirection vers la page de login.
*   **403 :** Afficher "Action non autorisée" (vérifier les permissions/abonnement).
*   **422 :** Mapper les erreurs de validation Laravel directement sur les champs du formulaire.
*   **500 :** Afficher un message d'erreur générique : "Une erreur est survenue, veuillez réessayer plus tard."
