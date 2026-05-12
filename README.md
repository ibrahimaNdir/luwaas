# Luwaas — Plateforme SaaS de Gestion Locative

> Digitalisation de la gestion des locations immobilières au Sénégal

---

## Présentation

Luwaas est une plateforme SaaS de gestion locative conçue pour le marché sénégalais.
Elle permet aux bailleurs de gérer leurs propriétés, logements, baux, locataires et paiements depuis une interface unique, avec un système de publication de logements conditionné à un abonnement.

L'API REST est construite avec Laravel et sécurisée via Laravel Sanctum. Elle est consommée par un frontend web et une application mobile Flutter (projet séparé).

---

## Rôles & logique multi-tenant

| Rôle | Description |
|------|-------------|
| **Locataire** | Recherche de logements publiés, envoi de demandes de location, consultation de ses baux et paiements |
| **Bailleur (Propriétaire)** | Gestion illimitée de ses propriétés et logements (en interne), publication conditionnée au plan souscrit |
| **Admin plateforme** | Supervision globale, gestion des bailleurs, statistiques SaaS (MRR, taux de conversion) |

Chaque bailleur dispose de son propre espace de données isolé (propriétés, logements, locataires, baux).

---

## Expérience Bailleur — Flux utilisateur

Après inscription, le bailleur accède **immédiatement et librement** à son espace de gestion :

```
[Inscription + Vérification OTP]
           ↓
    [Dashboard bailleur]
           ↓
✅ Ajouter propriétés       → ILLIMITÉ (aucune restriction)
✅ Ajouter logements liés   → ILLIMITÉ (aucune restriction)
           ↓
⚠️  Publier un logement     → LIMITÉ selon le plan actif
```

La **gestion interne** (propriétés, logements, locataires, baux) est toujours libre. Seule la **publication d'annonces** visible par les locataires est régulée par le plan. Cette approche permet au bailleur de s'installer confortablement dans la plateforme avant de s'engager financièrement.

---

## Module Abonnement SaaS

Luwaas adopte un modèle **Freemium à quotas de publication**, adapté au marché sénégalais.

### Philosophie des plans

Le bailleur s'inscrit sans carte bancaire, utilise la plateforme librement, et rencontre la limite seulement lorsqu'il tente de publier plus d'annonces que son plan ne l'autorise. C'est à ce moment — une fois déjà engagé dans la plateforme — qu'il choisit son plan. Ce modèle maximise l'acquisition tout en assurant une conversion naturelle.

### Plans disponibles

| Plan | Prix / mois | Publications actives | Facturation annuelle |
|------|-------------|----------------------|----------------------|
| **Gratuit** | 0 FCFA | 1 logement publié | — |
| **Pro** | ~8 000 FCFA | 10 logements publiés | -20% (~76 800 FCFA/an) |
| **Agence** | ~20 000 FCFA | Illimité | -20% (~192 000 FCFA/an) |

> Les prix sont indicatifs et calibrés pour le marché sénégalais, basés sur la valeur perçue (temps gagné, loyers sécurisés) plutôt que sur les tarifs européens.

### Ce que "publications actives" signifie

- Un logement **ajouté** mais **non publié** = visible uniquement dans le dashboard du bailleur
- Un logement **publié** = visible dans les résultats de recherche des locataires
- La **limite s'applique au nombre de publications simultanées**, pas aux logements créés

### Feature Gating par plan

| Fonctionnalité | Gratuit | Pro | Agence |
|----------------|---------|-----|--------|
| Propriétés & logements (gestion interne) | Illimité | Illimité | Illimité |
| Publications actives | 1 | 10 | Illimité |
| Photos par annonce | 3 | 10 | Illimité |
| Durée de publication | 15 jours | 30 jours renouvelable | Permanente |
| Mise en avant des annonces | ❌ | ❌ | ✅ |
| Rapports financiers | ❌ | ✅ | ✅ avancés |
| Export Excel | ❌ | ✅ | ✅ |
| SMS automatiques (rappels loyer) | ❌ | ✅ | ✅ |
| Co-gestionnaires | ❌ | 3 max | Illimité |
| Support | Email | Email prioritaire | Dédié |

### Fonctionnement technique

- **Plan gratuit par défaut** : tout nouveau bailleur démarre sur le plan gratuit avec 1 publication active.
- **Upgrade au bon moment** : lorsqu'il tente de publier un 2e logement, un popup d'upgrade s'affiche.
- **Expiration automatique** : une commande Artisan (`subscriptions:expire`) vérifie chaque nuit les abonnements expirés.
- **Paiement** : intégration PayDunya (Wave, Orange Money, Free Money, carte bancaire).
- **Activation automatique** : l'abonnement est activé via webhook IPN PayDunya dès confirmation du paiement.

### Middlewares

| Middleware | Rôle |
|-----------|------|
| `CheckSubscription` | Vérifie qu'un abonnement actif existe avant d'accéder aux routes protégées |
| `CheckPlanFeature` | Vérifie que la fonctionnalité ou le quota demandé est disponible dans le plan actuel |
| `CheckPublicationQuota` | Bloque la publication si le nombre de publications actives atteint la limite du plan |

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
- Recherche **géolocalisée** par proximité GPS (formule haversine)
- Affichage uniquement des logements **disponibles et publiés** par un bailleur actif

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

### Scheduler (abonnements & quotas)

Pour que les abonnements et les publications expirent automatiquement, ajouter au cron du serveur :

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
| GET | `/api/subscription/status` | Statut et plan actif du bailleur |
| POST | `/api/subscription/subscribe` | Souscrire à un plan (déclenche paiement PayDunya) |
| POST | `/api/subscription/cancel` | Annuler l'abonnement |

### Propriétés & Logements (auth requis — gestion interne libre)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/proprietes` | Liste des propriétés du bailleur |
| POST | `/api/proprietes` | Créer une propriété |
| GET | `/api/proprietes/{id}/logements` | Logements d'une propriété |
| POST | `/api/proprietes/{id}/logements` | Créer un logement (illimité) |
| PUT | `/api/logements/{id}` | Modifier un logement |
| DELETE | `/api/logements/{id}` | Supprimer un logement |

### Publication (auth + quota vérifié par `CheckPublicationQuota`)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/logements/{id}/publier` | Publier un logement (quota vérifié) |
| POST | `/api/logements/{id}/depublier` | Retirer un logement de la publication |
| GET | `/api/logements/search` | Recherche publique par filtres |
| GET | `/api/logements/nearby` | Recherche géolocalisée |

### Baux (auth requis)

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
```
Inscription → OTP → Login → Recherche logement publié
→ Demande → Attente → Bail créé → Paiement signature
→ Bail actif → Loyers mensuels → Quittances PDF
```

### Bailleur
```
Inscription → OTP → Dashboard libre
→ Ajoute propriétés & logements (illimité, sans friction)
→ Tente de publier → Quota plan gratuit (1 publication)
→ Atteint la limite → Choix plan Pro ou Agence
→ Paiement PayDunya (Wave / Orange Money) → Accès étendu
→ Réception demandes → Création baux → Suivi paiements
```

### Admin
```
Login → Dashboard MRR → Gestion bailleurs
→ Gestion plans → Statistiques globales
```

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
