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

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Stockage du fichier dans le disque "public/photos_logements"
            $path = $file->store('photos_logements', 'public');

            // Création de l’enregistrement en base de données
            $photo = $logement->photos()->create([
                'url' => $path,
                'principale' => false,
                'ordre' => 1,
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
            })->get();
    }


}
