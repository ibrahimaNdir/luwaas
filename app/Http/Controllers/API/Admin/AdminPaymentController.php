<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index(Request $request)
    {
        $paiements = Paiement::with(['bail.logement', 'locataire.user'])
            ->when($request->statut, fn($q, $statut) => $q->where('statut', $statut))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->orderByDesc('date_echeance')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $paiements]);
    }
}