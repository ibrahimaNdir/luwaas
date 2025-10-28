<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Http\Requests\LogementRequest;
use App\Http\Resources\LogementProprietaireResource;
use App\Models\Logement;
use App\Models\Propriete;
use App\Services\Proprietaire\LogementService;
use Illuminate\Http\Request;


class LogementController extends Controller
{
    protected $logementService;

    public function __construct(LogementService $logementService)
    {
        $this->logementService = $logementService;
    }

    public function index()
    {
        $logements = $this->logementService->index();
        return response()->json($logements, 200);
    }

    public function store(LogementRequest $request, $proprieteId)
    {
        $ownerId = $request->user()->proprietaire->id;

        $data = $request->validated();
        $data['propriete_id'] = $proprieteId; // injecte le paramètre de la route

        $logement = $this->logementService->store($data, $ownerId);

        // 🛑 Si la propriété n'appartient pas au propriétaire connecté → on arrête tout
        abort_if(!$logement, 403, 'La propriété ne vous appartient pas.');

        // ✅ Sinon, on retourne la ressource créée
        return response()->json($logement, 201);
    }






    public function show($id)
    {
        $logement = $this->logementService->show($id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json($logement, 200);
    }

    public function update(LogementRequest $request, $proprieteId, $id)
    {
        // Vérifier que le logement appartient à la propriété $proprieteId
        $logement = $this->logementService->update($request->validated(), $proprieteId, $id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json($logement, 200);
    }

    public function destroy($proprieteId, $id)
    {
        $deleted = $this->logementService->destroy($proprieteId, $id);
        if (!$deleted) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json(null, 204);
    }


    public function search(Request $request)
    {
        $filters = $request->only(['propriete_id', 'statut_occupe', 'typelogement']);
        $results = $this->logementService->search($filters);
        return response()->json($results, 200);
    }

    public function indexByPropriete($proprieteId)
    {
        $logements = $this->logementService->indexByPropriete($proprieteId);

        // ✅ Retourne une collection de Resources
        return LogementProprietaireResource::collection($logements);
    }


    public function countByPropriete($proprieteId)
    {
        $count = $this->logementService->countByPropriete($proprieteId);
        return response()->json(['total' => $count], 200);
    }

    public function updateStatusPublication(Request $request, $proprieteId, $id)
    {
        $request->validate([
            'statut_publication' => 'required|in:brouillon,publie'
        ]);

        $logement = $this->logementService->getByProprieteAndId($proprieteId, $id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé dans cette propriété'], 404);
        }

        $logement = $this->logementService->updateStatus($id, $request->statut_publication);

        return response()->json($logement, 200);
    }


    public function addPhotos(Request $request, $proprieteId, $logementId)
    {
        $request->validate([
            'photos' => 'required',
            'photos.*' => 'image|max:2048'
        ]);

        $proprietaire = auth()->user()->proprietaire;

        if (!$proprietaire) {
            return response()->json(['message' => 'Utilisateur non lié à un compte propriétaire.'], 403);
        }

        $propriete = Propriete::where('id', $proprieteId)
            ->where('proprietaire_id', $proprietaire->id)
            ->first();

        if (!$propriete) {
            return response()->json(['message' => 'Propriété non autorisée'], 403);
        }

        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé ou ne correspond pas à la propriété'], 404);
        }

        $files = $request->file('photos');

        $photos = $this->logementService->addPhotos($logementId, $files);

        return response()->json([
            'message' => 'Photos ajoutées avec succès',
            'photos' => $photos,
        ], 201);
    }


    public function getPublishedLogementsByProprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;

        $logements = $this->logementService->getPublishedLogementsByProprietaire($proprietaireId);

        return response()->json($logements, 200);
    }

    public function nearby(Request $request) {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius', 10);

        // Calculer la formule de distance une seule fois
        $distanceFormula = '(
        6371 * acos(
            cos(radians(?)) * cos(radians(proprietes.latitude)) *
            cos(radians(proprietes.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(proprietes.latitude))
        )
    )';

        $logements = Logement::join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->selectRaw("logements.*, {$distanceFormula} AS distance", [$lat, $lng, $lat])
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->whereNotNull('proprietes.latitude')
            ->whereNotNull('proprietes.longitude')
            ->whereRaw("{$distanceFormula} <= ?", [$lat, $lng, $lat, $radius])
            ->orderByRaw($distanceFormula, [$lat, $lng, $lat])
            ->get();

        // Charger les relations
        $logements->load(['propriete', 'photos']);

        return response()->json($logements, 200);
    }



    /**
     * Recherche par zone administrative (région, département, commune)
     */
    public function searchzone(Request $request)
    {
        $query = Logement::query()
            ->join('proprietes', 'logements.propriete_id', '=', 'proprietes.id')
            ->where('logements.statut_publication', 'publie')
            ->where('logements.statut_occupe', 'disponible')
            ->select('logements.*');

        // Filtre par région
        if ($request->has('region_id')) {
            $query->where('proprietes.region_id', $request->input('region_id'));
        }

        // Filtre par département
        if ($request->has('departement_id')) {
            $query->where('proprietes.departement_id', $request->input('departement_id'));
        }

        // Filtre par commune
        if ($request->has('commune_id')) {
            $query->where('proprietes.commune_id', $request->input('commune_id'));
        }

        // Filtres supplémentaires sur le logement
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

        $logements = $query->with(['propriete', 'photos'])->get();

        return response()->json($logements, 200);
    }



}
