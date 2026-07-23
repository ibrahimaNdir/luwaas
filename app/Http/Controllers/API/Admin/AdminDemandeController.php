<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use Illuminate\Http\Request;

class AdminDemandeController extends Controller
{
    public function index(Request $request)
    {
        $demandes = Demande::with(['locataire.user', 'logement.propriete'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('date_demande')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $demandes]);
    }

    public function show(string $id)
    {
        $demande = Demande::with(['locataire.user', 'logement.propriete.proprietaire.user'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $demande]);
    }
}