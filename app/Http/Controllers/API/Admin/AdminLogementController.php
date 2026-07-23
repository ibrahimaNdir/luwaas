<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logement;
use Illuminate\Http\Request;

class AdminLogementController extends Controller
{
    public function index(Request $request)
    {
        $logements = Logement::with('propriete.proprietaire.user')
            ->when($request->statut_publication, fn($q, $s) => $q->where('statut_publication', $s))
            ->when($request->statut_occupe, fn($q, $s) => $q->where('statut_occupe', $s))
            ->when($request->search, function ($q, $search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhereHas('propriete', fn($p) => $p->where('nom', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $logements]);
    }

    public function show(string $id)
    {
        $logement = Logement::with('propriete.proprietaire.user')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $logement]);
    }
}