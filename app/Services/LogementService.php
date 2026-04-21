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

    public function show(int $id): ?Logement
    {
        return Logement::with(['propriete', 'photos'])->find($id);
    }

    public function destroy(int $proprieteId, int $id, int $ownerId): bool
    {
        $logement = Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (!$logement || $logement->statut_occupe === 'loue') return false;

        $logement->delete();
        return true;
    }

    // ═══════════════════════════════════════════
    // MISE À JOUR
    // ═══════════════════════════════════════════

    public function updateInfos(int $proprieteId, int $id, int $proprietaireId, array $data): array
    {
        $logement = Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $proprietaireId))
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (!$logement) {
            return ['error' => 'Logement non trouvé ou non autorisé.', 'status' => 404];
        }

        if (isset($data['status']) && $data['status'] === 'disponible' && $logement->status === 'loue') {
            return ['error' => 'Impossible de modifier un logement actuellement loué.', 'status' => 422];
        }

        $logement->update($data);
        return ['logement' => $logement];
    }

    public function updateStatus(int $id, string $statut, int $ownerId): ?Logement
    {
        $logement = Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $ownerId))
            ->find($id);

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

        return $query->with(['propriete', 'photos'])->get();
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
            ->orderByRaw($formula, [$lat, $lng, $lat])
            ->with(['propriete', 'photos'])
            ->get();
    }
}
