<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\Logement;
use App\Models\Propriete;
use Illuminate\Support\Facades\Storage;

class LogementService
{
    // ═══════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════

    public function index(int $proprietaireId)
    {
        return Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->with(['propriete', 'photos'])
            ->get();
    }

    public function store(array $data, int $proprietaireId): ?Logement
    {
        $propriete = Propriete::where('id', $data['propriete_id'])
            ->where('proprietaire_id', $proprietaireId)
            ->first();

        if (!$propriete) return null;

        return Logement::create($data);
    }



    public function destroy(
        int $proprieteId,
        int $id,
        int $ownerId
    ): array {
        $logement = Logement::whereHas(
            'propriete',
            fn($q) => $q->where('proprietaire_id', $ownerId)
        )
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (!$logement) {
            return [
                'error' => 'Logement non trouvé ou non autorisé.',
                'status' => 404,
            ];
        }

        if ($logement->statut_occupe === 'occupe') {
            return [
                'error' => 'Impossible de supprimer un logement actuellement occupé.',
                'status' => 422,
            ];
        }

        $logement->delete();

        return [
            'success' => true,
        ];
    }







    // ═══════════════════════════════════════════
    // MISE À JOUR
    // ═══════════════════════════════════════════





    public function updateInfos(
        int $proprieteId,
        int $id,
        int $proprietaireId,
        array $data
    ): array {
        $logement = Logement::whereHas(
            'propriete',
            fn($q) => $q->where('proprietaire_id', $proprietaireId)
        )
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (!$logement) {
            return [
                'error' => 'Logement non trouvé ou non autorisé.',
                'status' => 404,
            ];
        }

        // Bloque TOUTE modification si le logement est occupé
        if ($logement->statut_occupe === 'occupe') {
            return [
                'error' => 'Impossible de modifier un logement actuellement occupé.',
                'status' => 422,
            ];
        }

        $logement->update($data);

        return [
            'logement' => $logement->fresh(),
        ];
    }






    public function updateStatus(int $proprieteId, int $id, string $statut, int $ownerId): ?Logement
    {
        $logement = Logement::where('propriete_id', $proprieteId)
            ->whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('id', $id)
            ->first();

        if (!$logement) return null;

        $logement->update(['statut_publication' => $statut]);
        return $logement;
    }

    // ═══════════════════════════════════════════
    // PHOTOS
    // ═══════════════════════════════════════════

    public function addPhotos(int $logementId, array $files): array
    {
        $logement = Logement::find($logementId);
        if (! $logement) return [];

        $photos       = [];
        $isFirstPhoto = $logement->photos()->count() === 0;
        $currentCount = $logement->photos()->count();

        foreach ($files as $index => $file) {
            $path = $file->store("logements/{$logementId}/photos", 'public');

            $photo = \App\Models\PhotoLogement::create([
                'logement_id' => $logementId,
                'url'         => $path,
                'principale'  => $isFirstPhoto && $index === 0,
                'ordre'       => $currentCount + $index + 1,
            ]);

            $photos[] = $photo;
        }

        return $photos;
    }

    // ═══════════════════════════════════════════
    // LISTING
    // ═══════════════════════════════════════════

    public function indexByPropriete(int $proprieteId)
    {
        return Logement::where('propriete_id', $proprieteId)
            ->with(['photos'])
            ->get();
    }

    public function countByPropriete(int $proprieteId): int
    {
        return Logement::where('propriete_id', $proprieteId)->count();
    }

    public function getPublishedLogementsByProprietaire(int $proprietaireId)
    {
        return Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->where('statut_publication', 'publie')
            ->with(['propriete', 'photos'])
            ->get();
    }

    public function logementsLocataire(int $locataireId)
    {
        return Bail::with('logement.propriete')
            ->where('locataire_id', $locataireId)
            ->orderByDesc('date_debut')
            ->get()
            ->pluck('logement')
            ->unique('id')
            ->values();
    }


    public function showForProprietaire(int $ownerId, int $logementId): ?Logement
    {
        return Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('id', $logementId)
            ->with(['propriete', 'photos'])
            ->first();
    }

    public function showForProprietaireByPropriete(int $ownerId, int $proprieteId, int $logementId): ?Logement
    {
        return Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('propriete_id', $proprieteId)
            ->where('id', $logementId)
            ->with(['propriete', 'photos'])
            ->first();
    } 

    // Dans LogementService.php, ajoute :

    /**
     * Récupère tous les logements publiés (public - sans auth)
     */
    public function getPublishedLogements()
    {
        return Logement::where('statut_publication', 'publie')
            ->orderByDesc('is_highlighted')
            ->orderByDesc('created_at')
            ->with(['propriete', 'photos'])
            ->get();
    }

    /**
     * Récupère un logement publié par ID (public - sans auth)
     */
    public function getPublishedLogementById(int $id): ?Logement
    {
        return Logement::where('statut_publication', 'publie')
            ->where('id', $id)
            ->with(['propriete', 'photos'])
            ->first();
    }

    // ═══════════════════════════════════════════
    // RECHERCHE
    // ═══════════════════════════════════════════

    public function search(array $filters)
    {
        $query = Logement::query();

        if (isset($filters['propriete_id'])) {
            $query->where('propriete_id', $filters['propriete_id']);
        }
        if (isset($filters['statut_occupe'])) {
            $query->where('statut_occupe', $filters['statut_occupe']);
        }
        if (isset($filters['typelogement'])) {
            $query->where('typelogement', $filters['typelogement']);
        }

        return $query->get();
    }

    public function searchZone(array $filters)
    {
        $query = Logement::query()
            ->join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->select('logements.*');

        if (isset($filters['region_id'])) {
            $query->where('proprietes.region_id', $filters['region_id']);
        }
        if (isset($filters['departement_id'])) {
            $query->where('proprietes.departement_id', $filters['departement_id']);
        }
        if (isset($filters['commune_id'])) {
            $query->where('proprietes.commune_id', $filters['commune_id']);
        }
        if (isset($filters['typelogement'])) {
            $query->where('logements.typelogement', $filters['typelogement']);
        }
        if (isset($filters['meuble'])) {
            $query->where('logements.meuble', $filters['meuble']);
        }
        if (isset($filters['nombre_pieces'])) {
            $query->where('logements.nombre_pieces', '>=', $filters['nombre_pieces']);
        }
        if (isset($filters['prix_max'])) {
            $query->where('logements.prix_indicatif', '<=', $filters['prix_max']);
        }

        return $query->orderByDesc('logements.is_highlighted')
            ->orderByDesc('logements.created_at')
            ->with(['propriete', 'photos'])
            ->get();
    }

    public function nearby(float $lat, float $lng, float $radius = 10)
    {
        $formula = '(6371 * acos(
            cos(radians(?)) * cos(radians(proprietes.latitude)) *
            cos(radians(proprietes.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(proprietes.latitude))
        ))';

        return Logement::join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->selectRaw("logements.*, {$formula} AS distance", [$lat, $lng, $lat])
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->whereNotNull('proprietes.latitude')
            ->whereNotNull('proprietes.longitude')
            ->whereRaw("{$formula} <= ?", [$lat, $lng, $lat, $radius])
            ->orderByDesc('logements.is_highlighted')
            ->orderByRaw($formula, [$lat, $lng, $lat])
            ->with(['propriete', 'photos'])
            ->get();
    }
    public function getAllLogementsByProprietaire(int $proprietaireId)
    {
        return Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->with(['propriete', 'photos'])
            ->get();
    }
}
