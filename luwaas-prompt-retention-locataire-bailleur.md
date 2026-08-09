# Prompt — Mécanismes de rétention Luwaas (paiement en ligne vs manuel)

## Contexte

Tu travailles sur **Luwaas**, une plateforme SaaS de gestion locative pour le marché sénégalais (Laravel/PostgreSQL backend, Next.js/TypeScript frontend). Le paiement manuel (hors ligne) est autorisé sans friction et sans commission — ce choix produit est **non-négociable** et ne doit pas être remis en cause.

Le problème à résoudre : une fois la relation bailleur-locataire établie sur la plateforme, rien n'incite structurellement à continuer de payer en ligne (où Luwaas prélève une commission `max(6000, 6%)`) plutôt qu'en manuel (0% commission). Il ne s'agit **pas** de pénaliser ou bloquer le paiement manuel, mais de rendre le paiement en ligne objectivement plus avantageux, pour que rester sur Luwaas soit un choix rationnel et non une contrainte.

## Objectif

Conçois et spécifie 4 mécanismes de rétention basés sur la valeur ajoutée (pas sur la punition), à intégrer dans l'architecture existante de Luwaas.

## Mécanismes à implémenter

### 1. Historique de paiement certifié (réputation locataire)

- Chaque paiement en ligne alimente un score/historique de fiabilité du locataire (régularité, ponctualité).
- Ce score est visible par les futurs bailleurs lors d'une nouvelle demande de location, pour accélérer l'acceptation du dossier.
- Un paiement manuel ne génère **aucune** entrée dans cet historique certifié.
- Résultat attendu : le locataire lui-même a intérêt à pousser le bailleur vers le paiement en ligne.

### 2. Différenciation de la valeur documentaire des quittances

- Quittance générée après paiement en ligne : marquée **"certifiée Luwaas"**, horodatée, vérifiable via QR code, opposable en cas de litige.
- Quittance générée après paiement manuel : reste une simple déclaration du bailleur, sans certification renforcée.
- Le paiement manuel reste gratuit et sans friction — seule la valeur juridique de la quittance change.

### 3. Automatisation conditionnée aux données de paiement

- Les relances automatiques, calculs de retard et renouvellement de bail en un clic ne fonctionnent que si les paiements transitent par l'app.
- Aucun développement supplémentaire requis pour "forcer" ce comportement : c'est une conséquence naturelle de l'architecture actuelle.
- À ajouter : rendre ce manque visible dans le dashboard bailleur, ex. `"3 loyers non suivis ce mois — passez en paiement Luwaas pour réactiver les relances automatiques."`

### 4. Suivi du ratio manuel/en ligne par bailleur (dashboard admin)

- Ajouter un indicateur admin : ratio paiements manuels / paiements en ligne, par bailleur, dans le temps.
- Objectif : détection précoce des bailleurs qui décrochent du paiement en ligne, pas sanction automatique.
- Action associée : déclenchement d'un message ciblé ou d'un geste commercial (ex. 1 mois de Pro offert) plutôt qu'une pénalité.

## Contraintes à respecter

- Ne jamais bloquer, limiter ou pénaliser financièrement le paiement manuel.
- Rester cohérent avec la philosophie produit déjà en place : publication illimitée, gratuite, sans période d'essai, sans quota.
- Toute mesure doit créer une incitation positive, jamais une contrainte punitive.

## Livrable attendu

Pour chacun des 4 mécanismes : modèle de données impacté, endpoints API à créer/modifier, logique métier côté Laravel, et impact UI côté Next.js (dashboard bailleur, fiche locataire, quittance PDF).
