<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogementRequest;
use App\Http\Resources\LogementProprietaireRessource;
use App\Http\Resources\LogementLocataireResource;
use App\Models\Logement;
use App\Models\Propriete;
use App\Models\Bail;
use App\Services\Proprietaire\LogementService;
use Illuminate\Http\Request;

class LogementController extends Controller
{
    public function __construct(
        protected LogementService $logementService
    ) {}

    // ─────────────────────────────────────────
    // 1. LISTE DES LOGEMENTS DU PROPRIÉTAIRE
    // ─────────────────────────────────────────

    public function index(Request $request)
    {
        $proprietaire = $request->user()->proprietaire;
        $logements    = $this->logementService->index($proprietaire->id); // ✅ filtré

        return response()->json([
            'logements' => $logements,
            'total'     => $logements->count(),
            'limits'    => $proprietaire->planLimits(), // ✅ quotas pour le front
        ], 200);
    }

    // ─────────────────────────────────────────
    // 2. CRÉER UN LOGEMENT
    // ─────────────────────────────────────────

    public function store(LogementRequest $request, $proprieteId)
    {
        $proprietaire = $request->user()->proprietaire;

        // ✅ Feature-gating : vérifier la limite du plan
        if (! $proprietaire->canAddLogement()) {
            return response()->json([
                'message'     => "Vous avez atteint la limite de logements de votre plan ({$proprietaire->plan}).",
                'code'        => 'LOGEMENT_LIMIT_REACHED',
                'upgrade_url' => url('/plans'),
                'limits'      => $proprietaire->planLimits(),
            ], 403);
        }

        $data                 = $request->validated();
        $data['propriete_id'] = $proprieteId;

        $logement = $this->logementService->store($data, $proprietaire->id);

        abort_if(!$logement, 403, 'La propriété ne vous appartient pas.');

        return response()->json($logement, 201);
    }

    // ─────────────────────────────────────────
    // 3. VOIR UN LOGEMENT
    // ─────────────────────────────────────────

    public function show($id)
    {
        $logement = $this->logementService->show($id);

        if (! $logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }

        return response()->json($logement, 200);
    }

    // ─────────────────────────────────────────
    // 4. MODIFIER LES INFOS D'UN LOGEMENT
    // ─────────────────────────────────────────

    public function updateInfos(Request $request, $proprieteId, $id)
    {
        $proprietaire_id = $request->user()->proprietaire?->id; // ✅ via request

        if (! $proprietaire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $logement = Logement::whereHas('propriete', function ($q) use ($proprietaire_id) {
            $q->where('proprietaire_id', $proprietaire_id);
        })
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (! $logement) {
            return response()->json(['message' => 'Logement non trouvé ou non autorisé.'], 404);
        }

        $validated = $request->validate([
            'superficie'            => 'sometimes|numeric|min:0',
            'nombre_pieces'         => 'sometimes|integer|min:0',
            'meuble'                => 'sometimes|boolean',
            'etat'                  => 'sometimes|in:bon,moyen,a_renover',
            'description'           => 'sometimes|nullable|string',
            'prix_loyer'            => 'sometimes|numeric|min:0',
            'nombre_chambres'       => 'sometimes|integer|min:0',
            'nombre_salles_de_bain' => 'sometimes|integer|min:0',
            'status'                => 'sometimes|in:disponible,en_travaux,indisponible',
            'statut_publication'    => 'sometimes|in:publie,brouillon',
        ]);

        if (
            isset($validated['status']) &&
            $validated['status'] === 'disponible' &&
            $logement->status === 'loue'
        ) {
            return response()->json([
                'message' => 'Impossible de modifier un logement actuellement loué.'
            ], 422);
        }

        $logement->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Logement mis à jour avec succès.',
            'logement' => $logement,
        ]);
    }

    // ─────────────────────────────────────────
    // 5. SUPPRIMER UN LOGEMENT
    // ─────────────────────────────────────────

    // Dans LogementController::destroy()
    public function destroy(Request $request, $proprieteId, $id)
    {
        $ownerId = $request->user()->proprietaire->id; // ✅ ajout
        $deleted = $this->logementService->destroy($proprieteId, $id, $ownerId);

        if (! $deleted) {
            return response()->json(['message' => 'Logement non trouvé ou loué.'], 404);
        }

        return response()->json(null, 204);
    }

    // ─────────────────────────────────────────
    // 6. RECHERCHE ET FILTRES
    // ─────────────────────────────────────────

    public function search(Request $request)
    {
        $filters = $request->only(['propriete_id', 'statut_occupe', 'typelogement']);
        $results = $this->logementService->search($filters);
        return response()->json($results, 200);
    }

    public function searchzone(Request $request)
    {
        $query = Logement::query()
            ->join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->select('logements.*');

        if ($request->has('region_id')) {
            $query->where('proprietes.region_id', $request->input('region_id'));
        }
        if ($request->has('departement_id')) {
            $query->where('proprietes.departement_id', $request->input('departement_id'));
        }
        if ($request->has('commune_id')) {
            $query->where('proprietes.commune_id', $request->input('commune_id'));
        }
        if ($request->has('typelogement')) {
            $query->where('logements.typelogement', $request->input('typelogement'));
        }
        if ($request->has('meuble')) {
            $query->where('logements.meuble', $request->input('meuble'));
        }
        if ($request->has('nombre_pieces')) {
            $query->where('logements.nombre_pieces', '>=', $request->input('nombre_pieces'));
        }
        if ($request->has('prix_max')) {
            $query->where('logements.prix_indicatif', '<=', $request->input('prix_max'));
        }

        return LogementProprietaireRessource::collection(
            $query->with(['propriete', 'photos'])->get()
        );
    }

    // ─────────────────────────────────────────
    // 7. GÉOLOCALISATION
    // ─────────────────────────────────────────

    public function nearby(Request $request)
    {
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);

        $lat    = $request->input('lat');
        $lng    = $request->input('lng');
        $radius = $request->input('radius', 10);

        $distanceFormula = '(6371 * acos(
            cos(radians(?)) * cos(radians(proprietes.latitude)) *
            cos(radians(proprietes.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(proprietes.latitude))
        ))';

        $logements = Logement::join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->selectRaw("logements.*, {$distanceFormula} AS distance", [$lat, $lng, $lat])
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->whereNotNull('proprietes.latitude')
            ->whereNotNull('proprietes.longitude')
            ->whereRaw("{$distanceFormula} <= ?", [$lat, $lng, $lat, $radius])
            ->orderByRaw($distanceFormula, [$lat, $lng, $lat])
            ->with(['propriete', 'photos'])
            ->get();

        return LogementProprietaireRessource::collection($logements);
    }

    // ─────────────────────────────────────────
    // 8. AUTRES MÉTHODES
    // ─────────────────────────────────────────

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
        ], 200);
    }

    public function updateStatusPublication(Request $request, $proprieteId, $id)
    {
        // ✅ Validation du statut
        $request->validate([
            'statut_publication' => 'required|in:publie,brouillon',
        ]);

        $ownerId = $request->user()->proprietaire->id; // ✅ ajout

        $logement = $this->logementService->updateStatus(
            $id,
            $request->statut_publication,
            $ownerId  // ✅ ajout
        );

        // ✅ Gérer le cas logement non trouvé
        if (! $logement) {
            return response()->json([
                'message' => 'Logement non trouvé ou non autorisé.',
            ], 404);
        }

        return response()->json([
            'message'            => 'Statut mis à jour avec succès.',
            'statut_publication' => $logement->statut_publication,
            'id'                 => $logement->id,
        ], 200);
    }

    public function addPhotos(Request $request, $proprieteId, $logementId)
    {
        $request->validate([
            'photos'   => 'required',
            'photos.*' => 'image|max:5120',
        ]);

        $proprietaire = $request->user()->proprietaire; // ✅ via request

        if (! $proprietaire) {
            return response()->json(['message' => 'Utilisateur non lié à un compte propriétaire.'], 403);
        }

        $propriete = Propriete::where('id', $proprieteId)
            ->where('proprietaire_id', $proprietaire->id)
            ->first();

        if (! $propriete) {
            return response()->json(['message' => 'Propriété non autorisée.'], 403);
        }

        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (! $logement) {
            return response()->json(['message' => 'Logement non trouvé.'], 404);
        }

        $photos = $this->logementService->addPhotos($logementId, $request->file('photos'));

        return response()->json([
            'message' => 'Photos ajoutées avec succès.',
            'photos'  => $photos,
        ], 201);
    }

    public function logementsLocataire(Request $request)
    {
        $locataire = $request->user()->locataire ?? null;

        if (! $locataire) {
            return response()->json([
                'message' => 'Non autorisé ou pas de profil locataire.'
            ], 403);
        }

        $logements = Bail::with('logement.propriete')
            ->where('locataire_id', $locataire->id)
            ->orderByDesc('date_debut')
            ->get()
            ->pluck('logement')
            ->unique('id')
            ->values();

        return LogementLocataireResource::collection($logements);
    }

    public function getPublishedLogementsByProprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;

        return LogementProprietaireRessource::collection(
            $this->logementService->getPublishedLogementsByProprietaire($proprietaireId)
        );
    }
}
