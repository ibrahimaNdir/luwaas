# Luwaas — Plateforme SaaS de Gestion Locative

> Digitalisation de la gestion des locations immobilieres au Senegal

---

## Presentation

Luwaas est une plateforme SaaS de gestion locative concue pour le marche senegalais.
Elle permet aux bailleurs de gerer leurs proprietes, logements, baux, locataires et paiements depuis une interface unique.

Contrairement aux modeles classiques bases sur des quotas de publication, Luwaas laisse la publication **100% libre et illimitee**. Le modele economique repose sur une **commission percue uniquement lorsqu'un loyer est effectivement encaisse via la plateforme**, complete par un abonnement optionnel (Pro) pour des fonctionnalites avancees.

L'API REST est construite avec Laravel et securisee via Laravel Sanctum. Elle est consommee par un frontend web et une application mobile Flutter (projet separe).

---

## Roles & logique multi-tenant

| Role | Description |
|------|-------------|
| **Locataire** | Recherche de logements publies, envoi de demandes de location, consultation de ses baux et paiements |
| **Bailleur (Proprietaire)** | Gestion et publication illimitees de ses proprietes et logements, paiement de loyers en ligne ou hors ligne |
| **Admin plateforme** | Supervision globale, gestion des bailleurs, statistiques SaaS (revenus, taux d'utilisation des paiements en ligne) |

Chaque bailleur dispose de son propre espace de donnees isole (proprietes, logements, locataires, baux).

---

## Experience Bailleur — Flux utilisateur

Apres inscription, le bailleur accede **immediatement et sans restriction** a son espace de gestion :

```
[Inscription + Verification OTP]
           |
    [Dashboard bailleur - plan Starter actif d'office]
           |
Ajouter proprietes        -> ILLIMITE, gratuit
Ajouter logements lies    -> ILLIMITE, gratuit
Publier un logement       -> ILLIMITE, gratuit, sans limite de duree
           |
Encaisser un loyer
   -> Via Luwaas (agregateur)   : commission percue
   -> Hors ligne (cash / Wave)  : paiement manuel, 0% commission
```

Il n'y a **ni periode d'essai a duree limitee, ni quota de publications**. Le bailleur ne rencontre jamais de blocage pour gerer ou publier ses biens. La monetisation se fait exclusivement au moment de l'encaissement d'un loyer via la plateforme, ou via l'abonnement Pro optionnel.

---

## Module Abonnement & Monetisation SaaS

Luwaas adopte un modele **Commission a l'usage + Freemium sur les fonctionnalites**, adapte au marche senegalais.

### Philosophie du modele

Le bailleur s'inscrit sans carte bancaire, gere et publie ses biens sans aucune limite. Il n'est jamais bloque. La plateforme se remunere uniquement lorsqu'elle apporte une valeur mesurable : la securisation d'un paiement de loyer. Ce modele maximise l'adoption et la retention, tout en alignant le revenu de Luwaas sur l'usage reel du service.

### Commission sur les loyers encaisses via Luwaas

Pour chaque loyer paye par le locataire via l'agregateur de paiement integre a Luwaas :

```
Frais Luwaas = max(6 000 FCFA, 6% du loyer)
```

Exemples :

| Montant du loyer | Commission Luwaas |
|-------------------|--------------------|
| 50 000 FCFA | 6 000 FCFA (plancher) |
| 100 000 FCFA | 6 000 FCFA |
| 300 000 FCFA | 18 000 FCFA |

Cette commission n'est prelevee que sur les transactions passant reellement par l'agregateur de paiement. Aucun agregateur n'est fige a ce jour ; l'integration est concue pour rester agnostique du prestataire de paiement (PayDunya ou autre PSP local), afin de pouvoir comparer et changer de fournisseur sans impacter le modele economique.

### Paiement manuel (hors ligne)

Un locataire peut regler son loyer en dehors de l'application (especes, Wave direct, virement). Dans ce cas, le bailleur enregistre le paiement via le bouton **"Marquer comme paye manuellement"** :

- Le loyer passe au statut "paye", les relances automatiques s'arretent.
- Une quittance PDF est generee normalement.
- **Aucune commission n'est prelevee** sur ce paiement, puisque l'argent n'a pas transite par l'agregateur.
- Un message informatif est affiche pour encourager, sans contrainte, l'usage du paiement en ligne :
  > "Ce paiement a ete regle hors ligne. Note : les paiements effectues directement via Luwaas sont 100% automatises et securisent votre comptabilite."

Cette approche privilegie l'adoption et la retention plutot que l'extraction immediate de revenu sur chaque transaction.

### Plans disponibles

| Plan | Prix | Commission sur loyers Luwaas | Publication |
|------|------|-------------------------------|--------------|
| **Starter** | 0 FCFA/mois (par defaut) | max(6 000, 6% du loyer) | Illimitee, gratuite |
| **Pro** | 5 000 FCFA/mois ou 48 000 FCFA/an | max(6 000, 6% du loyer) | Illimitee, gratuite |

> Tout nouveau bailleur demarre automatiquement en plan Starter, actif sans limite de duree. Le plan Pro est une option, jamais une obligation pour publier ou encaisser des loyers.

### Feature Gating par plan

| Fonctionnalite | Starter | Pro |
|----------------|---------|-----|
| Proprietes & logements (gestion interne) | Illimite | Illimite |
| Publications actives | Illimite | Illimite |
| Paiement manuel (hors ligne) | Oui | Oui |
| Commission sur loyers via Luwaas | max(6 000, 6%) | max(6 000, 6%) |
| Quittances PDF | Oui | Oui |
| Mise en avant des annonces | Non | Oui |
| Rapports financiers avances | Non | Oui |
| Export Excel | Non | Oui |
| SMS automatiques (rappels loyer) | Oui (basique) | Oui (avance) |
| Support | Standard | Prioritaire |

### Fonctionnement technique

- **Plan Starter par defaut** : a l'inscription, `plan = starter`, `subscription_status = active`. Aucune date d'expiration.
- **Pas de blocage de publication** : la publication n'est jamais soumise a un quota ou a une periode d'essai.
- **Expiration des abonnements Pro** : une commande Artisan (`subscriptions:expire`) verifie chaque nuit les abonnements Pro expires et retrograde automatiquement le bailleur vers le plan Starter, sans jamais suspendre son compte ni bloquer les encaissements de loyer.
- **Paiement des loyers** : integration a un agregateur de paiement (Wave, Orange Money, carte bancaire), via une couche d'abstraction permettant de changer de prestataire sans impacter le reste de l'application.
- **Activation automatique** : la transaction est confirmee via webhook IPN de l'agregateur.
- **Paiement manuel** : endpoint dedie permettant au bailleur de marquer un loyer comme paye hors ligne, sans commission.

### Middlewares

| Middleware | Role |
|-----------|------|
| `CheckSubscription` | Verifie le statut de l'abonnement (Starter ou Pro) pour l'acces aux fonctionnalites premium |
| `CheckPlanFeature` | Verifie que la fonctionnalite demandee (export, SMS avance, mise en avant, etc.) est disponible dans le plan actuel |

> Le middleware historique `CheckPublicationQuota` (limite de publications) a ete supprime : la publication n'est plus restreinte par le plan.

---

## Fonctionnalites detaillees

### Gestion des utilisateurs
- Inscription et connexion securisees avec Laravel Sanctum
- Verification par OTP (code 6 chiffres envoye par email)
- Gestion des roles : locataire, bailleur, admin
- Controle d'acces rigoureux via middlewares

### Recherche de logements (cote locataire)
- Recherche **par filtres** geographiques (region, departement, commune)
- Recherche **par type** (villa, appartement, studio, chambre)
- Recherche **geolocalisee** par proximite GPS (formule haversine)
- Affichage uniquement des logements **disponibles et publies**

### Demandes de location
- Le locataire envoie une demande pour un logement (`POST /api/locataire/demandes`)
- Le bailleur recoit et gere les demandes (`GET /api/proprietaire/demandes`)
- Statuts geres : en attente, acceptee, refusee, bail cree
- Historique complet pour chaque utilisateur

### Gestion des baux
- Creation de bail liee a une demande acceptee
- Champs detailles : loyer, caution, charges, duree, jour d'echeance, renouvellement automatique
- Statuts : en attente de paiement, actif, resilie, suspendu, expire
- Generation PDF du contrat de bail (conforme au modele senegalais)
- Paiement de signature (caution + premier loyer) genere automatiquement, activable en ligne ou manuellement

### Paiements
- Suivi des loyers mensuels (paye, en retard, partiel)
- Paiement en ligne via agregateur (Wave, Orange Money, carte bancaire) avec commission Luwaas
- **Paiement manuel** (especes, Wave direct, virement) sans commission, avec generation de quittance
- Webhook IPN pour activation automatique des paiements en ligne
- Historique des transactions lie a chaque bail (paiements en ligne et manuels distingues)
- Relances automatiques de loyer (`SendRentReminders`) et alertes de retard (`RappelRetardsPaiement`), desactivees automatiquement lorsqu'un paiement manuel est enregistre
- Generation de quittances PDF

---

## Architecture

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | Laravel 9+, API REST, Sanctum |
| Base de donnees | MySQL / PostgreSQL |
| Auth | Laravel Sanctum + OTP email |
| PDF | barryvdh/laravel-dompdf |
| Paiements | Agregateur de paiement (a confirmer), integration agnostique du PSP |
| Containerisation | Docker + docker-compose |
| Frontend mobile | Flutter (projet separe) |

---

## Installation et configuration

### Prerequis
- PHP 8.1+
- Composer
- MySQL ou PostgreSQL
- Docker (optionnel)

### Installation

```bash
# 1. Cloner le depot
git clone https://github.com/ibrahimaNdir/luwaas.git
cd luwaas

# 2. Installer les dependances
composer install

# 3. Configurer l'environnement
cp .env.example .env
# Modifier les variables DB, mail, agregateur de paiement, etc.

# 4. Generer la cle d'application
php artisan key:generate

# 5. Migrer et seeder les tables (inclut les plans Starter / Pro)
php artisan migrate --seed

# 6. Creer le lien de stockage
php artisan storage:link

# 7. Demarrer le serveur
php artisan serve
```

### Avec Docker

```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

### Scheduler (expiration des abonnements Pro & relances)

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Endpoints API cles

### Authentification (publique)

| Methode | Endpoint | Description |
|---------|----------|--------------|
| POST | `/api/auth/register` | Inscription (locataire ou bailleur) |
| POST | `/api/auth/login` | Connexion |
| POST | `/api/auth/verify-otp` | Verification OTP |
| POST | `/api/auth/resend-otp` | Renvoyer l'OTP |
| POST | `/api/auth/logout` | Deconnexion |

### Abonnement (auth requis)

| Methode | Endpoint | Description |
|---------|----------|--------------|
| GET | `/api/plans` | Liste des plans disponibles (Starter, Pro) |
| GET | `/api/subscription/status` | Statut et plan actif du bailleur |
| POST | `/api/subscription/subscribe` | Souscrire au plan Pro (declenche paiement) |
| POST | `/api/subscription/cancel` | Annuler l'abonnement Pro (retour automatique a Starter) |

### Proprietes & Logements (auth requis — gestion et publication libres)

| Methode | Endpoint | Description |
|---------|----------|--------------|
| GET | `/api/proprietes` | Liste des proprietes du bailleur |
| POST | `/api/proprietes` | Creer une propriete |
| GET | `/api/proprietes/{id}/logements` | Logements d'une propriete |
| POST | `/api/proprietes/{id}/logements` | Creer un logement (illimite) |
| PUT | `/api/logements/{id}` | Modifier un logement |
| DELETE | `/api/logements/{id}` | Supprimer un logement |
| POST | `/api/logements/{id}/publier` | Publier un logement (illimite, gratuit) |
| POST | `/api/logements/{id}/depublier` | Retirer un logement de la publication |
| GET | `/api/logements/search` | Recherche publique par filtres |
| GET | `/api/logements/nearby` | Recherche geolocalisee |

### Baux (auth requis)

| Methode | Endpoint | Description |
|---------|----------|--------------|
| POST | `/api/baux` | Creer un bail depuis une demande acceptee |
| GET | `/api/proprietaire/baux` | Baux du bailleur |
| GET | `/api/locataire/baux` | Baux du locataire |
| GET | `/api/baux/{id}` | Detail d'un bail |
| GET | `/api/baux/{id}/pdf` | Telecharger le contrat PDF |

### Paiements

| Methode | Endpoint | Description |
|---------|----------|--------------|
| POST | `/api/paiements/{id}/mark-as-paid-manually` | Marquer un loyer/signature comme paye hors ligne (0% commission) |
| GET | `/api/paiements/{id}/quittance` | Generer/telecharger la quittance PDF |

### Demandes

| Methode | Endpoint | Description |
|---------|----------|--------------|
| POST | `/api/locataire/demandes` | Locataire : creer une demande |
| GET | `/api/locataire/demandes` | Locataire : historique demandes |
| GET | `/api/proprietaire/demandes` | Bailleur : demandes recues |

---

## Workflow utilisateur

### Locataire
```
Inscription -> OTP -> Login -> Recherche logement publie
-> Demande -> Attente -> Bail cree -> Paiement signature (en ligne ou manuel)
-> Bail actif -> Loyers mensuels (en ligne ou manuel) -> Quittances PDF
```

### Bailleur
```
Inscription -> OTP -> Dashboard actif immediatement (plan Starter)
-> Ajoute et publie proprietes & logements (illimite, gratuit, sans friction)
-> Recoit des demandes -> Cree des baux
-> Encaisse les loyers :
     - via Luwaas (agregateur)  -> commission max(6000, 6%)
     - hors ligne (cash/Wave)   -> paiement manuel, 0% commission, quittance generee
-> Optionnel : passe en Pro pour exports, rapports, SMS avances, mise en avant
```

### Admin
```
Login -> Dashboard revenus (commissions + abonnements Pro) -> Gestion bailleurs
-> Suivi taux de paiements en ligne vs manuels -> Statistiques globales
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

**Developpeur principal** : Ibrahima Ndir
**Email** : [ibrahimandir2410@gmail.com](mailto:ibrahimandir2410@gmail.com)
**GitHub** : [@ibrahimaNdir](https://github.com/ibrahimaNdir)