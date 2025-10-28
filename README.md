# Luwaas Backend - Documentation complète

## Présentation

Luwaas est une plateforme digitale innovante de gestion locative adaptée au contexte sénégalais.  
Ce backend Laravel propose une API REST sécurisée pour gérer les utilisateurs, les logements, les demandes de location, les baux, et les paiements intégrés. L’objectif est de faciliter la relation entre locataires et bailleurs de manière fluide, traçable et professionnelle.

***

## Fonctionnalités détaillées

### Gestion des utilisateurs

- Inscription et connexion sécurisées avec Laravel Sanctum
- Gestion des rôles : **locataire, bailleur (propriétaire), admin**
- Contrôle d’accès rigoureux via middleware

### Recherche de logements (côté locataire)

- Recherche **par filtres** géographiques (région, département, commune)
- Recherche **par type de logement** (villa, appartement, studio)
- Recherche **géolocalisée**, proximité réelle via coordonnées GPS
- Affichage uniquement des logements **disponibles** et publiés

### Demandes de location

- Le locataire connecté fait une demande pour un logement (POST `/api/locataire/demandes`)
- Le bailleur reçoit et gère les demandes (GET `/api/proprietaire/demandes`)
- Historique complet des demandes pour chaque utilisateur  
- Trace même les appels manqués ou non répondu

### Gestion des baux

- Création de bail liée au logement, locataire, et bailleur
- Champs détaillés : loyer mensuel, caution, charges, durée, échéance, renouvellement automatique
- Statuts gérés : actif, résilié, suspendu, expiré, en attente
- Génération et impression PDF personnalisée conforme au modèle sénégalais  
- Suivi des baux côté locataire et bailleur

### Paiements

- Gestion intégrée des paiements de loyers et cautions via Wave, Orange Money, espèces
- Notification des paiements et mises à jour des statuts
- Historique des transactions lié à chaque bail

***

## Architecture & Technologie

- **Backend** : Laravel 9+, API REST, Sanctum (auth), Eloquent ORM
- **Base de données** : MySQL ou PostgreSQL
- **Génération PDF** : barryvdh/laravel-dompdf
- **Frontend mobile** : Flutter (projet séparé)
- **API sécurisée** avec middleware rôle et validation forte

***

## Installation et configuration

1. Cloner le dépôt backend :
   ```bash
   git clone <url-du-repo-backend>
   cd backend
   ```
2. Installer les dépendances :
   ```bash
   composer install
   ```
3. Copier et configurer le fichier `.env` :
   ```bash
   cp .env.example .env
   # Modifier les variables DB, mail, etc.
   ```
4. Générer la clé d’application :
   ```bash
   php artisan key:generate
   ```
5. Migrer et seed les tables :
   ```bash
   php artisan migrate --seed
   ```
6. (Optionnel) Installer dompdf pour génération PDF :
   ```bash
   composer require barryvdh/laravel-dompdf
   ```
7. Démarrer le serveur :
   ```bash
   php artisan serve
   ```

***

## Endpoints API clés

- **Auth** : `/register`, `/login`, `/logout`
- **Logements** : filtres, recherche proche, gestion bailleur
- **Demandes** :  
  - Locataire POST `/locataire/demandes` (création demande)  
  - Locataire GET `/locataire/demandes` (historique)  
  - Bailleur GET `/proprietaire/demandes` (demandes reçues)
- **Baux** : création, modification, consultation, impression PDF
- **Paiements** : intégration Wave/Orange Money, historique, notifications

***

## Workflow utilisateur résumé

### Locataire

- S’inscrit et se connecte
- Recherche logement par filtres ou proximité
- Fait une demande d’appel au bailleur
- Suit ses demandes dans l’historique
- Consulte ses baux en cours avec détails financiers

### Bailleur

- S’inscrit et se connecte
- Ajoute propriétés et logements
- Reçoit et consulte les demandes des locataires
- Crée et gère les baux
- Génère et imprime les contrats PDF
- Suit les paiements et relances locataires

### Admin

- Supervise les utilisateurs et contenus
- Gère les validations et statistiques globales

***

## Contribution & support

Contributions bienvenues via Pull Requests.  
Pour toute question ou problème, ouvrir une issue.

***

## Contact

Développeur principal : Ibrahima Ndir
Email : ibrahimandir2410@gmail.com

***

