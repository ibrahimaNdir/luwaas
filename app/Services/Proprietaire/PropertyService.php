<?php

namespace App\Services\Proprietaire;

use App\Models\Propriete;

class PropertyService
{
    public function index()
    {
        $proprietes = Propriete::all();
        return $proprietes ;
    }

    public function store(array $request)
    {
        $propriete= Propriete::create($request);
        return   $propriete;
    }

    public function show($id)
    {
        $propriete= Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        return  $propriete;
    }

    public function update(array $data, $id)
    {
        $propriete = Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        $propriete->update($data);
        return  $propriete;
    }

    public function destroy($id)
    {
        $offre = Propriete::find($id);

        if (!$offre) {
            return false;
        }

        $offre->delete();
        return true;
    }
    // Le nombre de propriete lie a un proprietaire
    public function countByOwner($Id)
    {
        return Propriete::where('proprietaire_id', $Id)->count();
    }

    // ✅ Rechercher / filtrer les propriétés
    public function search(array $filters, $ownerId)
    {
        $query = Propriete::where('proprietaire_id', $ownerId);

        if (isset($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }


        return $query->get();
    }

    public function dashboard($ownerId)
    {
        // Nombre total de propriétés du propriétaire
        $totalProprietes = Propriete::where('proprietaire_id', $ownerId)->count();

        // Chargement des propriétés avec leurs logements essentiels
        $proprietes = Propriete::with(['logements' => function($query) {
            $query->select('id', 'propriete_id', 'numero', 'statut_occupe');
        }])->where('proprietaire_id', $ownerId)->get();

        // Initialisation des compteurs
        $totalLogements = 0;
        $totalLogementsOccupe = 0;
        $totalLogementsDispo = 0;

        foreach ($proprietes as $propriete) {
            $totalLogements += $propriete->logements->count();
            $totalLogementsOccupe += $propriete->logements->where('statut_occupe', 'occupe')->count();
            $totalLogementsDispo += $propriete->logements->where('statut_occupe', 'disponible')->count();
        }

        return [
            'total_proprietes'       => $totalProprietes,
            'total_logements'        => $totalLogements,
            'total_logements_occupe' => $totalLogementsOccupe,
            'total_logements_disponible' => $totalLogementsDispo,
            'proprietes'             => $proprietes
        ];
    }




    // la Methode qui gere la propriete
    public function indexByOwner($ownerId)
    {
        return Propriete::where('proprietaire_id', $ownerId)->get();
    }



}
