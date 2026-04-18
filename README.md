# Luwaas — Plateforme SaaS de Gestion Locative

> Digitalisation de la gestion des locations immobilières au Sénégal

---

## Présentation

Luwaas est une plateforme SaaS de gestion locative adaptée au contexte sénégalais.  
Elle permet aux bailleurs de gérer leurs logements, baux, locataires et paiements depuis une interface unique, avec un système d'abonnement intégré (trial, plans, facturation).

L'API REST est construite avec Laravel et sécurisée via Laravel Sanctum. Elle est consommée par un frontend web et une application mobile Flutter (projet séparé).

---

## Rôles & logique multi-tenant

| Rôle | Description |
|------|-------------|
| **Locataire** | Recherche de logements, demandes de location, consultation de ses baux et paiements |
| **Bailleur (Propriétaire)** | Gestion de ses logements, baux, locataires, paiements. Accès conditionné à un abonnement actif |
| **Admin plateforme** | Supervision globale, gestion des bailleurs, statistiques SaaS |

Chaque bailleur dispose de son propre espace de données (logements, baux, locataires) et souscrit à un plan pour accéder à la plateforme.

---

## Module Abonnement SaaS

Luwaas intègre un système d'abonnement complet pour les bailleurs :

### Plans disponibles

| Plan | Prix / mois | Logements | Locataires | Co-gestionnaires |
|------|-------------|-----------|------------|------------------|
| **Starter** | 5 000 FCFA | 5 max | 5 max | 1 |
| **Pro** | 15 000 FCFA | 20 max | Illimité | 3 |
| **Enterprise** | Sur devis | Illimité | Illimité | Illimité |

### Fonctionnement

- **Trial 30 jours** : tout nouveau bailleur bénéficie d'un accès complet pendant 30 jours, sans carte bancaire.
- **Expiration automatique** : une commande Artisan (`subscriptions:expire`) vérifie chaque nuit les trials et abonnements expirés.
- **Paiement** : intégration PayDunya (Wave, Orange Money, Free Money, carte bancaire).
- **Activation automatique** : l'abonnement est activé via webhook IPN PayDunya dès confirmation du paiement.

### Middlewares

| Middleware | Rôle |
|-----------|------|
| `CheckSubscription` | Bloque l'accès à l'API si l'abonnement est expiré ou annulé |
| `CheckPlanFeature` | Vérifie qu'une fonctionnalité ou quota est disponible dans le plan actuel |

### Feature Gating

Les fonctionnalités suivantes sont conditionnées au plan :
- **SMS automatiques** (rappels de loyer) → Pro et Enterprise uniquement
- **Export Excel** → Pro et Enterprise uniquement
- **Statistiques avancées** → Pro et Enterprise uniquement
- **Co-gestionnaires** → Pro (3 max) et Enterprise (illimité)

---

## Fonctionnalités détaillées

### Gestion des utilisateurs
- Inscription et connexion sécurisées avec Laravel Sanctum
- Vérification par OTP (code 6 chiffres envoyé par email)
- Gestion des rôles : locataire, bailleur, admin
- Contrôle d'accès rigoureux via middlewares

### Recherche de logements (côté locataire)
- Recherche **par filtres** géographiques (région, département, commune)
- Recherche **par type** (villa, appartement, studio, chambre)
- Recherche **géolocalisée** par proximité GPS (haversine formula)
- Affichage uniquement des logements disponibles et publiés

### Demandes de location
- Le locataire envoie une demande pour un logement (`POST /api/locataire/demandes`)
- Le bailleur reçoit et gère les demandes (`GET /api/proprietaire/demandes`)
- Statuts gérés : en attente, acceptée, refusée, bail créé
- Historique complet pour chaque utilisateur

### Gestion des baux
- Création de bail liée à une demande acceptée
- Champs détaillés : loyer, caution, charges, durée, jour d'échéance, renouvellement automatique
- Statuts : en attente de paiement, actif, résilié, suspendu, expiré
- Génération PDF du contrat de bail (conforme au modèle sénégalais)
- Paiement de signature (caution + premier loyer) généré automatiquement

### Paiements
- Suivi des loyers mensuels (payé, en retard, partiel)
- Intégration PayDunya (Wave, Orange Money, espèces)
- Webhook IPN pour activation automatique des paiements
- Historique des transactions lié à chaque bail
- Génération de quittances PDF

---

## Architecture

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | Laravel 9+, API REST, Sanctum |
| Base de données | MySQL / PostgreSQL |
| Auth | Laravel Sanctum + OTP email |
| PDF | barryvdh/laravel-dompdf |
| Paiements | PayDunya (Wave, OM, Free, Carte) |
| Containerisation | Docker + docker-compose |
| Frontend mobile | Flutter (projet séparé) |

---

## Installation et configuration

### Prérequis
- PHP 8.1+
- Composer
- MySQL ou PostgreSQL
- Docker (optionnel)

### Installation

```bash
# 1. Cloner le dépôt
git clone https://github.com/ibrahimaNdir/luwaas.git
cd luwaas

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env.example .env
# Modifier les variables DB, mail, PayDunya, etc.

# 4. Générer la clé d'application
php artisan key:generate

# 5. Migrer et seeder les tables (inclut les plans d'abonnement)
php artisan migrate --seed

# 6. Créer le lien de stockage
php artisan storage:link

# 7. Démarrer le serveur
php artisan serve
```

### Avec Docker

```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

### Scheduler (abonnements)

Pour que les abonnements expirent automatiquement, ajouter au cron du serveur :

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Endpoints API clés

### Authentification (publique)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/auth/register` | Inscription (locataire ou bailleur) |
| POST | `/api/auth/login` | Connexion |
| POST | `/api/auth/verify-otp` | Vérification OTP |
| POST | `/api/auth/resend-otp` | Renvoyer l'OTP |
| POST | `/api/auth/logout` | Déconnexion |

### Abonnement (auth requis)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/plans` | Liste des plans disponibles |
| GET | `/api/subscription/status` | Statut de l'abonnement du bailleur |
| POST | `/api/subscription/subscribe` | Souscrire à un plan |
| POST | `/api/subscription/cancel` | Annuler l'abonnement |

### Logements (auth + abonnement actif)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/logements` | Liste des logements du bailleur |
| POST | `/api/proprietes/{id}/logements` | Créer un logement |
| GET | `/api/logements/{id}` | Détail d'un logement |
| PUT | `/api/logements/{id}` | Modifier un logement |
| DELETE | `/api/logements/{id}` | Supprimer un logement |
| GET | `/api/logements/search` | Recherche par filtres |
| GET | `/api/logements/nearby` | Recherche géolocalisée |

### Baux (auth + abonnement actif)
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/baux` | Créer un bail depuis une demande acceptée |
| GET | `/api/proprietaire/baux` | Baux du bailleur |
| GET | `/api/locataire/baux` | Baux du locataire |
| GET | `/api/baux/{id}` | Détail d'un bail |
| GET | `/api/baux/{id}/pdf` | Télécharger le contrat PDF |

### Demandes
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/locataire/demandes` | Locataire : créer une demande |
| GET | `/api/locataire/demandes` | Locataire : historique demandes |
| GET | `/api/proprietaire/demandes` | Bailleur : demandes reçues |

---

## Workflow utilisateur

### Locataire
Inscription → OTP → Login → Recherche logement
→ Demande → Attente → Bail créé → Paiement signature
→ Bail actif → Loyers mensuels → Quittances PDF 


### Bailleur 
Inscription → OTP → Trial 30 jours → Ajout logements
→ Réception demandes → Création baux → Suivi paiements
→ Choix plan (Starter/Pro) → Paiement PayDunya → Accès continu 


### Admin
Login → Dashboard MRR → Gestion bailleurs
→ Gestion plans → Statistiques globales


---

## Tests

```bash
php artisan test
```

---

## Contribution

Les contributions sont bienvenues via Pull Requests.  
Pour toute question ou suggestion, ouvrir une issue.

---

## Contact

**Développeur principal** : Ibrahima Ndir  
**Email** : ibrahimandir2410@gmail.com  
**GitHub** : [@ibrahimaNdir](https://github.com/ibrahimaNdir)
