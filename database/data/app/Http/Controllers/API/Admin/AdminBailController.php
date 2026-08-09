<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bail;
use Illuminate\Http\Request;

class AdminBailController extends Controller
{
    public function index(Request $request)
    {
        $baux = Bail::with(['locataire.user', 'logement.propriete'])
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $baux]);
    }

    public function show(string $id)
    {
        $bail = Bail::with([
            'locataire.user',
            'logement.propriete.proprietaire.user',
            'paiements',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $bail]);
    }
}