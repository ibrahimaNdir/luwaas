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

    // Création d'un logement lié à une propriété spécifique

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

    // Affichage d'un logement par son ID
    public function show($id)
    {
        $logement = $this->logementService->show($id);
        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json($logement, 200);
    }



    // Mise à jour d'un logement par son ID

    /**
     * Mise à jour des infos modifiables par le bailleur
     * (status, prix, description, meuble...)
     */
    public function updateInfos(Request $request, $proprieteId, $id)
    {
        $user = $request->user();
        $proprietaire_id = $user->proprietaire->id ?? null;

        if (!$proprietaire_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Vérifier ownership
        $logement = Logement::whereHas('propriete', function ($q) use ($proprietaire_id) {
            $q->where('proprietaire_id', $proprietaire_id);
        })
            ->where('propriete_id', $proprieteId)
            ->where('id', $id)
            ->first();

        if (!$logement) {
            return response()->json(['message' => 'Logement non trouvé ou non autorisé.'], 404);
        }

        // Validation des champs modifiables
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

        // Règle métier : statut loue → non modifiable manuellement
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
            'success' => true,
            'message' => 'Logement mis à jour avec succès.',
            'logement' => $logement
        ]);
    }



    // Suppression d'un logement par son ID
    public function destroy($proprieteId, $id)
    {
        $deleted = $this->logementService->destroy($proprieteId, $id);
        if (!$deleted) {
            return response()->json(['message' => 'Logement non trouvé'], 404);
        }
        return response()->json(null, 204);
    }



    // Recherche de logements avec filtres
    public function search(Request $request)
    {
        $filters = $request->only(['propriete_id', 'statut_occupe', 'typelogement']);
        $results = $this->logementService->search($filters);
        return response()->json($results, 200);
    }



    // Liste des logements d'une propriété spécifique
    public function indexByPropriete($proprieteId)
    {
        $logements = $this->logementService->indexByPropriete($proprieteId);

        // ✅ Retourne une collection de Resources
        return LogementProprietaireRessource::collection($logements);
    }





    // Compte des logements d'une propriété spécifique
    public function countByPropriete($proprieteId)
    {
        $count = $this->logementService->countByPropriete($proprieteId);
        return response()->json(['total' => $count], 200);
    }



    // Mise à jour du statut de publication d'un logement
    public function updateStatusPublication(Request $request, $proprieteId, $id)
    {
        // ... après la mise à jour
        $logement = $this->logementService->updateStatus($id, $request->statut_publication);

        return response()->json([
            'message' => 'Statut mis à jour avec succès.',
            'statut_publication' => $logement->statut_publication, // On renvoie juste ce qui a changé
            'id' => $logement->id // Utile pour confirmation
        ], 200);
    }



    // Ajout de photos à un logement

    public function addPhotos(Request $request, $proprieteId, $logementId)
    {
        $request->validate([
            'photos' => 'required',
            'photos.*' => 'image|max:5120'
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


    // Récupère les logements publiés d'un propriétaire spécifique

    public function getPublishedLogementsByProprietaire(Request $request)
    {
        $proprietaireId = $request->user()->proprietaire->id;

        $logements = $this->logementService->getPublishedLogementsByProprietaire($proprietaireId);

        // ✅ Retourne avec la Resource (comme indexByPropriete)
        return LogementProprietaireRessource::collection($logements);
    }



    // Recherche de logements à proximité d'une position géographique

    public function nearby(Request $request)
    {
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

        return LogementProprietaireRessource::collection($logements);
    }

    //

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

        return LogementProprietaireRessource::collection($logements);
    }



    // Récupère les logements liés au locataire connecté

    public function logementsLocataire(Request $request)
    {
        $user = $request->user();
        $locataire = $user->locataire ?? null;

        if (!$locataire) {
            return response()->json([
                'message' => 'Non autorisé ou pas de profil locataire.'
            ], 403);
        }

        // Récupère tous les logements à partir des baux du locataire
        $logements = Bail::with('logement.propriete')
            ->where('locataire_id', $locataire->id)
            ->orderByDesc('date_debut')
            ->get()
            ->pluck('logement')        // On récupère seulement les logements
            ->unique('id')             // On enlève les doublons si plusieurs baux sur même logement
            ->values();                // On réindexe proprement

        return LogementLocataireResource::collection($logements);
    }
}
