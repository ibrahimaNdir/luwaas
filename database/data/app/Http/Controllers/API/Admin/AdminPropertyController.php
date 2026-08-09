<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Propriete;
use Illuminate\Http\Request;

class AdminPropertyController extends Controller
{
    public function index(Request $request)
    {
        $proprietes = Propriete::with('proprietaire.user')
            ->withCount('logements')
            ->when($request->search, function ($q, $search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('adresse', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $proprietes]);
    }

    public function show(string $id)
    {
        $propriete = Propriete::with(['proprietaire.user', 'logements'])
            ->withCount('logements')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $propriete]);
    }
}