<?php

namespace App\Services\Proprietaire;

use App\Models\Logement;
use App\Models\Propriete;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LogementService
{
    // ─────────────────────────────────────────
    // 1. LISTE — filtrée par propriétaire
    // ─────────────────────────────────────────

    public function index(int $proprietaireId)
    {
        return Logement::whereHas('propriete', function ($q) use ($proprietaireId) {
                    $q->where('proprietaire_id', $proprietaireId);
                })
                ->with(['photos', 'propriete'])
                ->get();
    }

    // ─────────────────────────────────────────
    // 2. CRÉER
    // ─────────────────────────────────────────

    public function store(array $data, int $ownerId): ?Logement
    {
        $propriete = Propriete::where('id', $data['propriete_id'])
            ->where('proprietaire_id', $ownerId)
            ->first();

        if (! $propriete) return null;

        return Logement::create($data);
    }

    // ─────────────────────────────────────────
    // 3. VOIR
    // ─────────────────────────────────────────

    public function show(int $id): ?Logement
    {
        return Logement::with(['photos', 'propriete'])->find($id);
    }

    // ─────────────────────────────────────────
    // 4. MODIFIER
    // ─────────────────────────────────────────

    public function update(array $data, int $proprieteId, int $logementId): ?Logement
    {
        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (! $logement) return null;

        $logement->update($data);
        return $logement;
    }

    // ─────────────────────────────────────────
    // 5. SUPPRIMER — avec ownership + protection
    // ─────────────────────────────────────────

    public function destroy(int $proprieteId, int $logementId, int $ownerId): bool
    {
        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->whereHas('propriete', function ($q) use ($ownerId) {
                $q->where('proprietaire_id', $ownerId);
            })
            ->first();

        if (! $logement) return false;

        // ✅ Bloquer si logement loué
        if ($logement->statut_occupe === 'loue') return false;

        // Supprimer les photos du storage avant de supprimer le logement
        foreach ($logement->photos as $photo) {
            Storage::disk('public')->delete($photo->url);
        }

        $logement->delete();
        return true;
    }

    // ─────────────────────────────────────────
    // 6. RECHERCHE
    // ─────────────────────────────────────────

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

        return $query->with(['photos', 'propriete'])->get();
    }

    // ─────────────────────────────────────────
    // 7. PAR PROPRIÉTÉ
    // ─────────────────────────────────────────

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

    public function getByProprieteAndId(int $proprieteId, int $logementId): ?Logement
    {
        return Logement::where('propriete_id', $proprieteId)
                       ->where('id', $logementId)
                       ->first();
    }

    // ─────────────────────────────────────────
    // 8. STATUT PUBLICATION
    // ─────────────────────────────────────────

    public function updateStatus(int $id, string $statut, int $ownerId): ?Logement
    {
        $logement = Logement::whereHas('propriete', function ($q) use ($ownerId) {
                        $q->where('proprietaire_id', $ownerId);
                    })
                    ->find($id);

        if (! $logement) return null;

        $logement->update(['statut_publication' => $statut]);
        return $logement;
    }

    // ─────────────────────────────────────────
    // 9. PHOTOS — via Laravel Storage
    // ─────────────────────────────────────────

    public function addPhotos(int $logementId, array $files)
    {
        $logement = Logement::find($logementId);
        if (! $logement) return null;

        $photos       = collect();
        $isFirstPhoto = $logement->photos()->count() === 0;

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) continue;

            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();

            // ✅ Via Laravel Storage (public disk)
            $path = $file->storeAs('photos_logements', $filename, 'public');

            $photo = $logement->photos()->create([
                'url'        => $path,
                'principale' => $isFirstPhoto && $index === 0,
                'ordre'      => $logement->photos()->count() + $index + 1,
            ]);

            $photos->push($photo);
        }

        return $photos;
    }

    // ─────────────────────────────────────────
    // 10. LOGEMENTS PUBLIÉS PAR PROPRIÉTAIRE
    // ─────────────────────────────────────────

    public function getPublishedLogementsByProprietaire(int $proprietaireId)
    {
        return Logement::where('statut_publication', 'publie')
            ->whereHas('propriete', function ($q) use ($proprietaireId) {
                $q->where('proprietaire_id', $proprietaireId);
            })
            ->with(['photos', 'propriete'])
            ->get();
    }
}