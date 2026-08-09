# Luwaas — Business Model Abonnement & Paiement Loyer

> Document de référence sur la logique économique du modèle abonnement bailleur + paiement loyer via PayDunya, incluant les workflows, scénarios et grille tarifaire.

---

## ⚠️ Points de vigilance avant mise en prod (priorité)

1. **Taux de commission PayDunya non confirmé (estimation 1,5%-3,5%)** — variable la plus critique du modèle. En attendant confirmation, calibrage fait sur l'hypothèse haute (3,5%) pour rester en sécurité. **Action : contacter le commercial PayDunya pour verrouiller le taux exact par opérateur (Wave/OM/Free Money).**
2. **Tarif de l'API de déboursement non vérifié** — un buffer de sécurité de 1% est intégré au calcul en attendant confirmation.
3. **Marge qui fond sur les loyers élevés** — palier "loyer élevé" ajouté à la grille tarifaire (section 5) pour sécuriser ces cas.
4. **Paiement partiel et double paiement tranchés** — voir sections 11 et 14, ne plus laisser en suspens avant lancement (sources fréquentes de litiges en gestion locative réelle).

---

## 1. Contexte et problème de départ

Luwaas gère deux flux de paiement distincts, tous deux via PayDunya :

1. **Paiement abonnement** : le bailleur paie pour publier et gérer ses biens sur la plateforme
2. **Paiement loyer** : le locataire paie son loyer, qui doit être reversé au bailleur

**Contraintes business posées dès le départ :**
- Le locataire paie **exactement** le montant du loyer (aucun frais visible)
- Le bailleur reçoit **exactement** le montant du loyer (aucune retenue)

**Le problème mécanique que ça crée :** PayDunya prélève toujours sa commission (~1,5%-3,5% selon l'opérateur) sur celui qui **reçoit** l'argent en premier — dans notre cas, toujours le compte marchand Luwaas, jamais directement le bailleur ou le locataire. Satisfaire les deux contraintes ci-dessus crée donc un déficit à chaque loyer reversé, qui doit être financé ailleurs : par les abonnements bailleurs.

---

## 2. Comment PayDunya intervient dans les deux flux

### Règle universelle
> La commission PayDunya est toujours prélevée sur celui qui **reçoit** l'argent — jamais sur celui qui paie. Dans les deux flux, c'est toujours le compte marchand Luwaas qui reçoit en premier.

### Flux abonnement (pas de complication)
1. Bailleur paie X FCFA pile (aucun frais visible pour lui)
2. PayDunya encaisse sur le compte Luwaas, prélève sa commission dessus
3. Luwaas reçoit net (montant - commission)
4. **Fin du flux** — aucun argent ne ressort, la commission reste une charge silencieuse absorbée dans la marge

### Flux loyer (complication du reversement)
1. Locataire paie le loyer pile (aucun frais visible)
2. PayDunya encaisse sur le compte Luwaas, prélève sa commission
3. Luwaas reçoit net (montant - commission)
4. Luwaas reverse **le montant intégral** au bailleur — donc plus que ce qu'il a reçu net
5. Le différentiel (= la commission PayDunya) est financé par la trésorerie globale Luwaas, alimentée par les abonnements

### Exemple chiffré (loyer 100 000 FCFA, commission ~3%)
| Étape | Montant |
|---|---|
| Locataire paie | 100 000 FCFA |
| PayDunya prélève (~3%) | -3 000 FCFA |
| Luwaas reçoit net | 97 000 FCFA |
| Luwaas reverse au bailleur | 100 000 FCFA |
| **Perte à combler sur cette transaction** | **-3 000 FCFA** |

---

## 3. APIs PayDunya disponibles (notes techniques)

- **Checkout invoice classique** : utilisé pour les deux flux (abonnement et loyer), différenciés par un champ `type` dans les `custom_data`
- **API PER (Paiement Et Redistribution)** : permet un split natif au moment du paiement, mais nécessite que le destinataire ait **son propre compte PayDunya** — contrainte peu réaliste pour onboarder des bailleurs rapidement
- **API de déboursement (`/api/v2/disburse/get-invoice`)** : permet de pousser de l'argent directement vers un numéro de téléphone (Wave, Orange Money) sans compte PayDunya requis pour le destinataire — **c'est celle à utiliser pour automatiser le reversement au bailleur**. Doit être explicitement activée dans le dashboard business PayDunya.
- Le tarif exact de l'API de déboursement (frais additionnels ou non) reste à vérifier directement sur le dashboard/support PayDunya.

---

## 4. Principe économique de la solution

> L'abonnement bailleur ne doit pas être vu comme un revenu isolé, mais comme **le financement du déficit PayDunya** généré par les loyers reversés.

### Le piège des paliers fixes
Un abonnement à prix fixe basé sur un quota de biens (ex: "5 000 FCFA pour 2 biens") ne colle pas à la réalité :
- Le quota de publication ne prédit pas le nombre de loyers réellement encaissés (biens vacants vs loués)
- Le montant des loyers varie énormément d'un bailleur à l'autre (studio à 40k vs villa à 300k) — un prix fixe par palier ignore cette variabilité
- Résultat : certains bailleurs (gros loyers, forte occupation) rendent la marge nulle ou négative

### La solution retenue : prix par bien publié
```
Abonnement = Frais de base + (Prix unitaire × nombre de biens publiés)
```

Avantages :
- On facture sur ce qui est **publié**, pas sur ce qui est **loué** → chaque bien vacant est du profit pur
- Le prix par bien est calibré à ~2x le coût moyen réel absorbé par bien loué → marge positive garantie même dans le pire cas (tous les biens loués, à plein régime)
- Simple à vendre au bailleur : modèle SaaS classique ("X FCFA par bien géré"), pas perçu comme une commission déguisée

### Principe de mutualisation (comme une assurance)
On ne cherche **pas** un équilibre parfait bailleur par bailleur, mois par mois — c'est statistiquement impossible à garantir avec un prix fixe. On surveille un seul indicateur, à l'échelle de toute la plateforme :

> **Total encaissé en abonnements (tous bailleurs) ≥ Total des commissions PayDunya absorbées sur tous les loyers reversés**

Certains bailleurs rapportent plus qu'ils ne coûtent, d'autres l'inverse — l'équilibre se fait sur la masse, pas cas par cas.

---

## 5. Grille tarifaire — Les offres d'abonnement

| Offre | Prix de base | Prix par bien publié | Cible | Features incluses |
|---|---|---|---|---|
| **free_trial** | 0 FCFA | — (max 2 biens gratuits) | Tout nouveau bailleur | Publication seule, 15 jours max |
| **starter** | 2 000 FCFA | 6 000 FCFA/bien | Bailleur avec baux actifs, usage standard | Quittances automatiques, dashboard basique |
| **pro** | 5 000 FCFA | 6 000 FCFA/bien | Bailleur voulant plus d'outils de gestion | + Rapports comptables, export conformité légale (caution décret n°2023-382), alertes retard, support prioritaire |
| **enterprise** | Négocié sur mesure | Négocié (souvent dégressif) | Portefeuille 10+ biens | + Multi-utilisateurs, API dédiée, accompagnement personnalisé |

**Transition automatique** : `free_trial` → `starter` par défaut dès qu'un bail est signé. Upgrade vers `pro` possible à tout moment. `enterprise` sur contact commercial dédié.

### Vérification de rentabilité (hypothèses : loyer moyen 100 000 FCFA, commission PayDunya ~3% = 3 000 FCFA/bien loué/mois)

| Cas | Biens loués | Abonnement facturé | Commission absorbée | Marge nette |
|---|---|---|---|---|
| Starter, 1 bien vacant | 0/1 | 8 000 FCFA | 0 FCFA | +8 000 FCFA |
| Starter, 3 biens tous loués | 3/3 | 20 000 FCFA | 9 000 FCFA | +11 000 FCFA (55%) |
| Starter, 3 biens loyers élevés (150k) | 3/3 | 20 000 FCFA | 13 500 FCFA | +6 500 FCFA (32%) |

> ⚠️ Ces chiffres reposent sur des hypothèses (loyer moyen, taux de commission). À recalibrer avec les vrais chiffres du dashboard PayDunya une fois en production.

### Palier "loyer élevé" (sécurisation de la marge sur les biens haut de gamme)

| Tranche de loyer déclaré | Surcharge par bien |
|---|---|
| ≤ 150 000 FCFA | Prix standard (6 000 FCFA) |
| 150 001 - 300 000 FCFA | +2 000 FCFA (→ 8 000 FCFA) |
| > 300 000 FCFA | +4 000 FCFA (→ 10 000 FCFA) |

Objectif : éviter que la marge devienne négative sur les villas/biens premium, sans complexifier l'offre standard pour la majorité des bailleurs.

---

## 6. Workflow — Inscription et essai gratuit

1. Le bailleur s'inscrit → statut `free_trial`, compteur de 15 jours démarre
2. Il publie jusqu'à **2 biens maximum** gratuitement (plafond volontaire pour éviter l'abus)
3. Publier un bien, en soi, ne coûte rien à Luwaas (aucune transaction PayDunya tant qu'il n'y a pas de loyer payé)

---

## 7. Règle centrale — Abonnement obligatoire dès signature de bail

> Dès qu'un locataire signe un bail et qu'un premier paiement de loyer va être traité, le bailleur bascule **immédiatement** vers `starter` (payant) — même si on est encore à J+2 de l'essai gratuit de 15 jours.

**Pourquoi cette règle est non négociable** : la publication seule ne coûte rien, mais dès qu'un vrai paiement de loyer transite par PayDunya, un vrai coût (la commission) doit être absorbé. Sans cette règle, un bailleur pourrait faire louer plusieurs biens gratuitement pendant toute la période d'essai et générer des pertes sèches sans aucune compensation d'abonnement.

---

## 8. Workflow — Paiement abonnement (cycle mensuel)

1. Facture générée automatiquement à la date anniversaire (base + nombre de biens actuel)
2. Bailleur paie via PayDunya → webhook confirme → statut `actif` reconduit pour 30 jours
3. **Échec de paiement** (solde insuffisant, carte refusée, etc.) → statut passe en `grace_period` (3 jours), relances envoyées
4. **Grace period expirée sans paiement** → statut `bloqué`

---

## 9. Ce qui est bloqué vs jamais bloqué en cas d'impayé d'abonnement

| Bloqué en cas d'abonnement impayé | Jamais bloqué |
|---|---|
| Nouvelles publications de biens | Reversement des loyers déjà encaissés au bailleur |
| Accès aux rapports/exports avancés (offre pro) | Paiement du locataire sur un bail existant |
| Signature de nouveaux baux | Historique et quittances déjà générées |

**Principe clé** : l'argent du loyer appartient au bailleur, pas à Luwaas. Le retenir à cause d'un abonnement impayé transformerait un problème de facturation en un vrai problème de confiance (et potentiellement un problème juridique), en pénalisant le locataire qui n'est pour rien dans ce litige.

---

## 10. Workflow — Paiement loyer (avec vérification abonnement)

1. Locataire paie le loyer → webhook PayDunya confirme
2. Le système vérifie le statut abonnement du bailleur — **à titre informatif/alerte uniquement, jamais pour bloquer le reversement**
3. Reversement automatique intégral déclenché vers le bailleur (via API de déboursement), quel que soit son statut d'abonnement
4. Si le statut abonnement est `bloqué` : une alerte est envoyée au bailleur ("votre loyer a bien été reversé, mais votre abonnement est en souffrance — renouvelez pour continuer à publier de nouveaux biens")

---

## 11. Tableau des scénarios complets

| # | Scénario | Statut abonnement | Ce qui se passe |
|---|---|---|---|
| 1 | Bailleur inscrit, aucun bail encore | `free_trial` | Publie librement (max 2 biens), 0 coût pour Luwaas |
| 2 | Bail signé à J+3 de l'essai | `free_trial` → `starter` (immédiat) | Abonnement facturé dès ce jour |
| 3 | 15 jours passés, aucun bail signé | `free_trial` expiré | Publication bloquée tant que non abonné (aucun coût réel encouru) |
| 4 | Abonnement starter actif, tous les biens loués | `starter` | Marge positive garantie par le calcul (prix/bien > coût/bien) |
| 5 | Renouvellement échoue | `starter` → `grace_period` | Loyers reversés normalement, relances envoyées |
| 6 | Grace period expirée sans renouvellement | `bloqué` | Nouvelles publications/baux bloqués, loyers existants toujours reversés |
| 7 | Upgrade vers pro en cours de mois | `starter` → `pro` | Proratisation du complément, accès immédiat aux features avancées |
| 8 | Bailleur avec 12 biens | `enterprise` | Tarif négocié à part, hors grille standard |
| 9 | Résiliation du compte | → `résilié` | Biens dépubliés, gestion des baux existants à définir |
| 10 | Échec du déboursement vers le bailleur (numéro invalide, opérateur en panne) | — | Statut "payé, reversement en attente" + retry automatique + alerte admin après plusieurs échecs |
| 11 | Retard de paiement locataire | — | Pas de commission PayDunya à absorber tant qu'il n'y a pas de paiement (aucun risque financier additionnel) |
| 12 | Paiement partiel du locataire | — | **Tranché** : statut `partiel`, le système accumule les versements jusqu'au montant exact du loyer, puis déclenche un reversement intégral unique. Si non complété sous 5 jours → bascule en `retard` (procédure de relance standard) |
| 13 | Double paiement (locataire ou bailleur) | — | **Tranché** : pas de remboursement automatique (lent/coûteux en mobile money). Le montant en excédent est crédité automatiquement sur le loyer du mois suivant, avec notification au locataire et au bailleur. Remboursement possible uniquement sur demande explicite du locataire, traité au cas par cas |
| 14 | Résiliation de bail en cours de mois | — | Décision à trancher : gestion du prorata (remboursement ou report sur caution) |

---

## 12. Indicateur de suivi à surveiller (le seul chiffre qui compte vraiment)

> **Total encaissé en abonnements (ce mois, tous bailleurs) vs Total des commissions PayDunya absorbées sur les loyers reversés (ce mois, tous bailleurs)**

Si ce ratio reste positif à l'échelle de la plateforme, le modèle économique tient — même si certains bailleurs pris individuellement sont déficitaires (loyers élevés, forte occupation).

---

## 13. Points encore ouverts à trancher

- Durée exacte de la grace period (3 jours proposé, à valider)
- Gestion des baux existants à la résiliation d'un compte bailleur
- Gestion du prorata en cas de résiliation de bail en cours de mois
- ~~Politique sur les paiements partiels de loyer~~ → tranché, voir scénario #12
- ~~Politique sur les doubles paiements~~ → tranché, voir scénario #13
- **Vérification du taux de commission réel PayDunya par opérateur — priorité absolue avant lancement**
- Vérification du tarif réel de l'API de déboursement PayDunya (frais additionnels ou non)
- Calibrage final des prix une fois les vraies données d'usage disponibles (loyer moyen réel, taux de commission réel, taux d'occupation réel)

---

*Document généré le 05 août 2026 — à mettre à jour au fur et à mesure des décisions prises et des données réelles collectées.*
