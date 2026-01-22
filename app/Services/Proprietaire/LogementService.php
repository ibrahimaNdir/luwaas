<?php

namespace App\Services\Proprietaire;

use App\Models\Logement;
use App\Models\Propriete;
use Illuminate\Http\UploadedFile;

class LogementService
{
    public function index()
    {
        return Logement::all();
    }

    public function store(array $data, $ownerId)
    {
        // Vérifier que la propriété appartient au propriétaire connecté
        $propriete = Propriete::where('id', $data['propriete_id'])
            ->where('proprietaire_id', $ownerId)
            ->first();

        if (!$propriete) {
            return null; // Ou lever une exception métier
        }

        return Logement::create($data);
    }

    public function show($id)
    {
        return Logement::find($id);
    }

    public function update(array $data, $proprieteId, $logementId)
    {
        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (!$logement) {
            return null;
        }
        $logement->update($data);
        return $logement;
    }

    public function destroy($proprieteId, $logementId)
    {
        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (!$logement) {
            return false;
        }
        $logement->delete();
        return true;
    }


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

    public function getByProprieteAndId($proprieteId, $logementId)
    {
        return Logement::where('propriete_id', $proprieteId)
            ->where('id', $logementId)
            ->first();
    }

    public function updateStatus($id, $statut)
    {
        $logement = Logement::find($id);
        if (!$logement) {
            return null;
        }
        $logement->update(['statut_publication' => $statut]);
        return $logement;
    }


    public function indexByPropriete($proprieteId)
    {
        return Logement::where('propriete_id', $proprieteId)->get();
    }

    public function countByPropriete($proprieteId)
    {
        return Logement::where('propriete_id', $proprieteId)->count();
    }

    public function addPhotos(int $logementId, array $files)
    {
        $logement = Logement::find($logementId);
        if (!$logement) {
            return null;
        }

        $photos = collect();
        $isFirstPhoto = $logement->photos()->count() === 0;

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();

            // ✅ CHANGEMENT : Stocke directement dans public/photos_logements
            $file->move(public_path('photos_logements'), $filename);

            // Le chemin à sauvegarder en base
            $path = 'photos_logements/' . $filename;

            $photo = $logement->photos()->create([
                'url' => $path, // Ex: "photos_logements/abc_123.jpg"
                'principale' => $isFirstPhoto && $index === 0,
                'ordre' => $index + 1,
            ]);

            $photos->push($photo);
        }

        return $photos;
    }



    public function getPublishedLogementsByProprietaire($proprietaireId)
    {
        return Logement::where('statut_publication', 'publie')
            ->whereHas('propriete', function ($query) use ($proprietaireId) {
                $query->where('proprietaire_id', $proprietaireId);
            })
            ->with(['photos', 'propriete'])  // ✅ AJOUTE CETTE LIGNE
            ->get();
    }
}
