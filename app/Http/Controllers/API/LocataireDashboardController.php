<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocataireDashboardResource;
use App\Services\LocataireDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocataireDashboardController extends Controller
{
    public function __construct(
        private readonly LocataireDashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locataireId = $request->user()->locataire?->id;

        abort_unless($locataireId, 403, 'Profil locataire introuvable.');

        $data = $this->dashboardService->dashboard($locataireId);

        return (new LocataireDashboardResource($data))
            ->response()
            ->setStatusCode(200);
    }
}
