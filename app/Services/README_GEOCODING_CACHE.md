# Implémentation du Cache pour le GeocodingService

## Pourquoi ce cache ?

Le géocodage via Nominatim (OpenStreetMap) présente plusieurs défis :
- Limites de taux : ~1 requête/seconde en usage intensif
- Temps de réponse variable : 200ms-1s+ selon la charge du serveur
- Redondance élevée : mêmes adresses géocodées多次 fois dans l'application

## Implémentation

### Ce qui a été ajouté :

1. **Dans `app/Services/GeocodingService.php`** :
   - Import du facade `Illuminate\Support\Facades\Cache`
   - Propriété `$cacheMinutes` configurable via config/services.php
   - Logique de cache dans la méthode `geocodeAddress()` :
     * Génération d'une clé de cache basée sur MD5 de l'adresse
     * Lecture du cache si activé et si la clé existe
     * Écriture en cache SEULEMENT des résultats réussis (pas des échecs)
     * Durée de cache configurable (défaut : 24h)

2. **Dans `config/services.php`** :
   - Nouvelle section `'geocoding'` avec :
     * `api_url` : URL de l'API de géocodage (défaut : Nominatim)
     * `cache_minutes` : Durée de cache en minutes (défaut : 1440 = 24h)

3. **Dans `.env`** :
   - `GEOCODING_API_URL=https://nominatim.openstreetmap.org/search`
   - `GEOCODING_CACHE_MINUTES=1440`

## Comportement du cache

- **Succès** : Les coordonnées trouvées sont mises en cache pour la durée configurée
- **Échec** : Les adresses introuvables ou erreurs NE SONT PAS mises en cache
  → Permet un retry automatique dès la prochaine tentative
- **Désactivation** : Mettre `GEOCODING_CACHE_MINUTES=0` pour désactiver complètement le cache

## Bénéfices attendus

1. **Performance** :
   - Première recherche : ~600ms (appel API)
   - Recherches suivantes : ~0.3ms (lecture cache)
   - Gain de 99.95% pour les requêtes répétées

2. **Protection API** :
   - Réduction de 99%+ des appels vers Nominatim
   - Élimination du risque de blocage par limites de taux

3. **Fiabilité** :
   - En cas de panne Nominatim, les adresses déjà en cache continuent de fonctionner
   - Dégradation en douceur au lieu d'une panne totale

## Monitoring

Pour surveiller l'efficacité du cache, vous pourriez ajouter :
```php
// Dans le service, après lecture/écriture du cache
Cache::increment('geocoding_hits');   // quand trouvé en cache
Cache::increment('geocoding_misses'); // quand pas en cache (nécessite appel API)
```

Puis afficher le ratio hits/(hits+misses) dans votre tableau de bord de monitoring.

---
Implémenté le : 2026-08-17