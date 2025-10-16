<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Http\Requests\LogementRequest;
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

    public function store(LogementRequest $request)
    {
        $ownerId = $request->user()->proprietaire->id;
        $logement = $this->logementService->store($request->validated(), $ownerId);

        if (!$logement) {
            return response()->json(['message' => 'La propriété ne vous appartient pas.'], 403);
        }

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

    public function update(LogementRequest $request, $id)
    {
        $logement = $this->logementService->update($request->validated(), $id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json($logement, 200);
    }

    public function destroy($id)
    {
        $deleted = $this->logementService->destroy($id);
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
        return response()->json($logements, 200);
    }

    public function countByPropriete($proprieteId)
    {
        $count = $this->logementService->countByPropriete($proprieteId);
        return response()->json(['total' => $count], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut_publication' => 'required|in:brouillon,publie'
        ]);

        $logement = $this->logementService->updateStatus($id, $request->statut_publication);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json($logement, 200);
    }

    public function addPhotos(Request $request, $proprieteId, $logementId)
    {
        $request->validate([
            'photos.*' => 'required|image|max:2048'
        ]);

        // Vérification que la propriété appartient bien au propriétaire connecté
        $propriete = Propriete::where('id', $proprieteId)
            ->where('proprietaire_id', auth()->id())
            ->first();

        if (!$propriete) {
            return response()->json(['message' => 'Propriété non autorisée'], 403);
        }

        // Vérification que le logement appartient bien à cette propriété
        $logement = Logement::where('id', $logementId)
            ->where('propriete_id', $proprieteId)
            ->first();

        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé ou ne correspond pas à la propriété'], 404);
        }

        // Récupération des fichiers photos
        $files = $request->file('photos');

        // Ajout des photos via le service
        $photos = $this->logementService->addPhotos($logementId, $files);

        return response()->json([
            'message' => 'Photos ajoutées avec succès',
            'photos' => $photos,
        ], 201);
    }

}
