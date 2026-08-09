<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogementRequest;
use App\Http\Resources\LogementLocataireResource;
use App\Http\Resources\LogementProprietaireRessource;
use App\Models\Logement;
use App\Models\Propriete;
use App\Services\LogementService;
use Illuminate\Http\Request;

class LogementController extends Controller
{
    public function __construct(protected LogementService $logementService) {}

    // ═══════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════

    public function index(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        $logements    = $this->logementService->index($proprietaire->id);

        return response()->json([
            'logements' => $logements,
            'total'     => $logements->count(),
            'limits'    => $proprietaire->planLimits(),
        ]);
    }

    public function store(LogementRequest $request, $proprieteId)
    {

        $data                 = $request->validated();
        $data['propriete_id'] = $proprieteId;

        $logement = $this->logementService->store($data, $request->user()->proprietaire->id);

        abort_if(!$logement, 403, 'La propriété ne vous appartient pas.');

        return response()->json($logement, 201);
    }

    public function updateInfos(Request $request, $proprieteId, $id)
    {
        $proprietaireId = $this->proprietaireId($request);

        $validated = $request->validate([
            'superficie'            => 'sometimes|numeric|min:0',
            'nombre_pieces'         => 'sometimes|integer|min:0',
            'meuble'                => 'sometimes|boolean',
            'etat'                  => 'sometimes|in:bon,moyen,a_renover',
            'description'           => 'sometimes|nullable|string',
            'prix_loyer'            => 'sometimes|numeric|min:0',
            'nombre_chambres'       => 'sometimes|integer|min:0',
            'nombre_salles_de_bain' => 'sometimes|integer|min:0',
            //'statut_publication'    => 'sometimes|in:publie,brouillon',
        ]);

        $result = $this->logementService->updateInfos($proprieteId, $id, $proprietaireId, $validated);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Logement mis à jour avec succès.',
            'logement' => $result['logement'],
        ]);
    }


    public function destroy(Request $request, $proprieteId, $id)
    {
        $result = $this->logementService->destroy(
            $proprieteId,
            $id,
            $this->proprietaireId($request)
        );

        if (isset($result['error'])) {
            return response()->json(
                ['message' => $result['error']],
                $result['status']
            );
        }

        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════
    // PHOTOS
    // ═══════════════════════════════════════════

    public function addPhotos(Request $request, $proprieteId, $logementId)
    {
        $request->validate([
            'photos'   => 'required',
            'photos.*' => 'image|max:5120',
        ]);

        $proprietaireId = $this->proprietaireId($request);

        $propriete = Propriete::where('id', $proprieteId)
            ->where('proprietaire_id', $proprietaireId)
            ->firstOrFail();

        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->firstOrFail();

        $photos = $this->logementService->addPhotos($logementId, $request->file('photos'));

        return response()->json([
            'message' => 'Photos ajoutées avec succès.',
            'photos'  => $photos,
        ], 201);
    }

    // ═══════════════════════════════════════════
    // LISTING
    // ═══════════════════════════════════════════

    public function indexByPropriete($proprieteId)
    {
        return LogementProprietaireRessource::collection(
            $this->logementService->indexByPropriete($proprieteId)
        );
    }

    public function countByPropriete($proprieteId)
    {
        return response()->json([
            'total' => $this->logementService->countByPropriete($proprieteId)
        ]);
    }

    public function getPublishedLogementsByProprietaire(Request $request)
    {
        return LogementProprietaireRessource::collection(
            $this->logementService->getPublishedLogementsByProprietaire($this->proprietaireId($request))
        );
    }

    public function logementsLocataire(Request $request)
    {
        $locataireId = $request->user()->locataire->id ?? null;

        abort_if(!$locataireId, 403, 'Non autorisé ou pas de profil locataire.');

        return LogementLocataireResource::collection(
            $this->logementService->logementsLocataire($locataireId)
        );
    }

    // ✅ Check publication selon le plan
    public function updateStatusPublication(Request $request, $proprieteId, $id)
    {
        $request->validate([
            'statut_publication' => 'required|in:publie,brouillon',
        ]);

        $logement = $this->logementService->updateStatus(
            $id,
            $request->statut_publication,
            $this->proprietaireId($request),
            (int) $proprieteId  // ← ajout : validation de la hiérarchie propriete → logement
        );

        abort_if(!$logement, 404, 'Logement non trouvé ou non autorisé.');

        return response()->json([
            'message'            => 'Statut mis à jour avec succès.',
            'statut_publication' => $logement->statut_publication,
            'id'                 => $logement->id,
            'limits'             => $request->user()->proprietaire->planLimits(),
        ]);
    }

    public function toggleHighlight(Request $request, $proprieteId, $id)
    {
        $logement = Logement::whereHas('propriete', fn($q) => $q->where('proprietaire_id', $this->proprietaireId($request)))
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->firstOrFail();

        $logement->update([
            'is_highlighted' => !$logement->is_highlighted
        ]);

        return response()->json([
            'message'        => 'Mise en avant modifiée avec succès.',
            'is_highlighted' => $logement->is_highlighted,
        ]);
    }

    // ═══════════════════════════════════════════
    // RECHERCHE & GÉOLOCALISATION
    // ═══════════════════════════════════════════

    public function search(Request $request)
    {
        return response()->json(
            $this->logementService->search(
                $request->only(['propriete_id', 'statut_occupe', 'typelogement'])
            )
        );
    }

    public function searchzone(Request $request)
    {
        return LogementProprietaireRessource::collection(
            $this->logementService->searchZone(
                $request->only([
                    'region_id',
                    'departement_id',
                    'commune_id',
                    'typelogement',
                    'meuble',
                    'nombre_pieces',
                    'prix_max',
                ])
            )
        );
    }

    public function nearby(Request $request)
    {
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);

        return LogementProprietaireRessource::collection(
            $this->logementService->nearby(
                $request->float('lat'),
                $request->float('lng'),
                $request->float('radius', 10)
            )
        );
    }

    public function getAllLogementsByProprietaire(Request $request)
    {
        return LogementProprietaireRessource::collection(
            $this->logementService->getAllLogementsByProprietaire($this->proprietaireId($request))
        );
    }


    public function show(Request $request, int $id)
    {
        $logement = $this->logementService->showForProprietaire(
            $this->proprietaireId($request),
            $id
        );

        if (!$logement) {
            return response()->json([
                'message' => 'Logement introuvable'
            ], 404);
        }

        return response()->json([
            'data' => new LogementProprietaireRessource($logement)
        ]);
    }

    public function showByPropriete(Request $request, int $proprieteId, int $id)
    {
        $logement = $this->logementService->showForProprietaireByPropriete(
            $this->proprietaireId($request),
            $proprieteId,
            $id
        );

        if (!$logement) {
            return response()->json([
                'message' => 'Logement introuvable'
            ], 404);
        }

        return response()->json([
            'data' => new LogementProprietaireRessource($logement)
        ]);
    }


    // Dans LogementController.php, remplace les méthodes publiques par :

    public function indexPublic()
    {
        return LogementLocataireResource::collection(
            $this->logementService->getPublishedLogements()
        );
    }

    public function showPublic(int $id)
    {
        $logement = $this->logementService->getPublishedLogementById($id);

        if (!$logement) {
            return response()->json([
                'message' => 'Logement introuvable'
            ], 404);
        }

        return response()->json([
            'data' => new LogementLocataireResource($logement)
        ]);
    }



    // ═══════════════════════════════════════════
    // HELPER PRIVÉ
    // ═══════════════════════════════════════════

    private function proprietaireId(Request $request): int
    {
        $id = $request->user()->proprietaire->id ?? null;
        abort_if(!$id, 403, 'Non autorisé.');
        return $id;
    }
}
