<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProprieteRequest;
use App\Http\Resources\ProprieteResource;
use App\Models\Propriete;
use App\Services\PropertyService;
use Illuminate\Http\Request;
use App\Http\Resources\ProprieteDetailResource;

class PropertyController extends Controller
{
    // � ✅ Injection de dépendances au lieu de new PropertyService()
    public function __construct(protected PropertyService $propertyService) {}

    // ═════════════════════════════════════════════
    // CRUD
    // ════════════════════════════════════════

    public function index()
    {
        try {
            $items = $this->propertyService->index();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des propriétés : ' . $e->getMessage()
            ], 500);
        }

        return ProprieteResource::collection($items);
    }

    public function store(ProprieteRequest $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $propriete = $this->propertyService->creerPropriete(
                $request->validated(),
                $proprietaireId
            );

            return response()->json([
                'message'   => 'Propriété ajoutée avec succès.',
                'propriete' => new ProprieteResource($propriete),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création : ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $proprietaireId = $this->proprietaireId(request());

        try {
            $propriete = Propriete::where('id', $id)
                ->where('proprietaire_id', $proprietaireId)
                ->firstOrFail();

            $propriete->delete();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Propriété non trouvée ou non autorisée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }

        return response()->json(null, 204);
    }

    public function update(ProprieteRequest $request, int $id)
    {
        try {
            $propriete = Propriete::where('id', $id)
                ->where('proprietaire_id', $this->proprietaireId($request))
                ->firstOrFail();

            $updated = $this->propertyService->updatePropriete($propriete, $request->validated());

            if (!$updated) {
                return response()->json([
                    'message' => 'Erreur lors de la mise à jour : aucune modification effectuée.',
                ], 500);
            }

            $propriete->refresh();

            return response()->json([
                'message'   => 'Propriété mise à jour avec succès.',
                'propriete' => new ProprieteResource($propriete),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Propriété non trouvée ou non autorisée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
            ], 500);
        }
    }

    // ═════════════════════════════════════════════
    // LISTING & RECHERCHE
    // ═════════════════════════════════════════════

    public function allProperty(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $items = $this->propertyService->indexByOwner($proprietaireId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des propriétés : ' . $e->getMessage()
            ], 500);
        }

        return ProprieteResource::collection($items);
    }

    public function search(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $results = $this->propertyService->search(
                $request->only(['region_id', 'type']),
                $proprietaireId
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la recherche : ' . $e->getMessage()
            ], 500);
        }

        return response()->json($results);
    }

    public function countProperty(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $count = $this->propertyService->countByOwner($proprietaireId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du comptage des propriétés : ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'total_proprietes' => $count
        ]);
    }


        public function show(Request $request, int $id)
    {
        $proprietaireId = $request->user()->proprietaire->id;

    public function show(Request $request, int $id)
    {
        $proprietaireId = $this->proprietaireId($request);

        try {
            $data = $this->propertyService->getDetailsWithStats($id, $proprietaireId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des détails : ' . $e->getMessage()
            ], 500);
        }

        if (!$data) {
            return response()->json(['message' => 'Propriété non trouvée'], 404);
        }

        return new ProprieteDetailResource((object)$data);
    }

    // ═════════════════════════════════════════════
    // DASHBOARD & STATS
    // ═════════════════════════════════════════════

    /*
    public function dashboard(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return response()->json([
            'dashboard'         => $this->propertyService->dashboard($proprietaireId),
            'historique_6_mois' => $this->propertyService->historique6Mois($proprietaireId),
        ]);
    }

    public function statsProprietes(Request $request)
    {
        $proprietaireId = $this->proprietaireId($request);

        return response()->json([
            'proprietes' => $this->propertyService->statsParPropriete($proprietaireId)
        ]);
    }

    */

    // ═════════════════════════════════════════════
    // HELPER PRIVÉ
    // ════════════════════════════════════════════

    private function proprietaireId(Request $request): int
    {
        $id = $request->user()?->proprietaire?->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }

    /**
     * Retourne la liste de tous les locataires du propriétaire connecté.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function allLocataires(Request $request)
    {
        // Récupérer l'ID du propriétaire connecté
        $ownerId = auth()->user()->proprietaire->id ?? null;

        if (!$ownerId) {
            return response()->json([
                'success' => false,
                'message' => 'Propriétaire non trouvé.',
            ], 404);
        }

        // Récupérer toutes les propriétés du propriétaire, avec les locataires chargés
        $proprietes = Propriete::where('proprietaire_id', $ownerId)
            ->with([
                'locataires.user:id,nom,prenom,telephone',   // seules les colonnes nécessaires
                'locataires.logement:id,numero,adresse'     // logement occupé
            ])
            ->get();

        // Aplatir la collection afin d’obtenir un tableau simple de locataires
        $locataires = $proprietes->flatMap(function ($prop) {
            return $prop->locataires->map(function ($loc) use ($prop) {
                return [
                    'locataire_id'   => $loc->id,
                    'nom'            => $loc->user->nom,
                    'prenom'         => $loc->user->prenom,
                    'telephone'      => $loc->user->telephone ?? null,
                    'logement'       => [
                        'numero'   => $loc->logement->numero ?? null,
                        'adresse'  => $loc->logement->adresse ?? null,
                    ],
                    // Optionnel : on peut aussi renvoyer l’ID de la propriété pour le contexte
                    'propriete_id' => $prop->id,
                    'propriete_nom'=> $prop->nom,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data'    => $locataires->values()->all(),   // ré‑indexer le tableau
        ]);
    }
}