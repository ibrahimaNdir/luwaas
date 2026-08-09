# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Project Overview

Luwaas is a Laravel-based SaaS platform for rental property management in Senegal. It serves three user roles (locataires, proprietaires, admins) with a subscription-based model for landlords.

## Common Commands

```bash
# Development
php artisan serve
php artisan migrate --seed
php artisan storage:link

# Testing
php artisan test

# Subscription management (run via cron)
php artisan schedule:run
php artisan subscriptions:expire
```

## Architecture

### Multi-Tenant User System

The application uses a role-based multi-tenant architecture:

- **Users table**: Base authentication with `user_type` enum (`proprietaire`, `locataire`, `admin`)
- **Profile tables**: Each role has its own profile table (`proprietaires`, `locataires`, `admins`) linked via `user_id`
- **Authentication**: Laravel Sanctum tokens + OTP verification (6-digit code sent via email)

### Subscription SaaS Model

Landlords (`proprietaires`) must have an active subscription to access the platform:

- **Trial period**: 30 days automatic trial on registration (`trial_ends_at` in `proprietaires` table)
- **Plans**: Starter, Pro, Enterprise with different limits (biens_max, locataires_max, cogestionnaires_max)
- **Subscription flow**: User registers → 30-day trial → Choose plan → PayDunya payment → Webhook activates subscription
- **Expiration**: `subscriptions:expire` command runs nightly to expire trials and subscriptions

Key tables:
- `plans`: Subscription tiers with billing_cycle (monthly/yearly)
- `subscriptions`: Payment records with status (pending, active, cancelled, expired)
- `proprietaires`: Contains subscription_status, plan, trial_ends_at, subscription_ends_at

### Geographic Hierarchy

Senegal-specific geographic data structure:
- `regions` → `departements` → `communes`
- Used for property location filtering and search

### Property & Rental Flow

1. **Properties**: `proprietes` (buildings/properties) → `logements` (individual housing units)
2. **Requests**: `demandes` table tracks rental requests with status (en_attente, acceptee, refusee, bail_cree)
3. **Leases**: `baux` created from accepted requests, with financial terms and status tracking
4. **Payments**: `paiements` for rent tracking, `transactions` for payment gateway records

### Feature Gating

The `CheckSubscription` middleware enforces subscription access for landlords. The `Subscribable` trait on `Proprietaire` model provides:
- `isInTrial()`, `hasActiveSubscription()`, `hasAccess()`
- `canUseFeature()` for plan-based feature access
- `canPublishLogement()`, `canAddLocataire()` for quota enforcement

### Payment Integration

PayDunya integration handles subscription payments:
- `SubscriptionService::initiatePayment()` creates pending subscription and PayDunya invoice
- Webhook at `/webhook/subscription/paydunya` activates subscription on payment confirmation
- Transaction status: pending → active (via webhook)

### Notifications

Firebase-based notification system:
- `NotificationService` handles sending notifications to users
- Events (`DemandeAcceptee`, `BailCree`, etc.) trigger listeners for notifications
- Notifications stored in MySQL `notifications` table with read status tracking

### Route Organization

Routes are organized by role and authentication level in `routes/api.php`:
- Public: `/api/logements/*` (search), `/api/auth/*` (register/login)
- Authenticated: `/api/subscription/*` (plan management)
- Landlord (requires active subscription): `/api/proprietaire/*`
- Tenant: `/api/locataire/*`
- Admin: `/api/admin/*`

### Key Services

- `AuthService`: Login/register with OTP verification flow
- `SubscriptionService`: Subscription lifecycle (initiate, activate, cancel, expire)
- `BailService`: Lease creation and validation
- `TransactionService`: Payment processing
- `NotificationService`: Firebase notifications

### Database Relationships

Important relationships to understand:
- `User` → `Proprietaire`/`Locataire`/`Admin` (one-to-one)
- `Proprietaire` → `Proprietes` → `Logements` (one-to-many cascade)
- `Logement` → `Demandes` → `Baux` (one-to-many)
- `Bail` → `Paiements` → `Transactions` (one-to-many)
- `Proprietaire` → `Subscriptions` (one-to-many, activeSubscription scope)

### Scheduler Configuration

The Laravel scheduler must be configured on the server for subscription expiration:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

This runs the `subscriptions:expire` command which:
- Expires trials past `trial_ends_at`
- Expires paid subscriptions past `subscription_ends_at`
- Sends notifications for trials ending soon
- Cleans up abandoned pending payments
