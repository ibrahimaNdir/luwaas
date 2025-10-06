<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProprieteRequest;
use App\Models\Propriete;
use App\Services\Proprietaire\PropertyService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    protected $propertyService;
    /**
     * OffreController constructor.
     */
    public function __construct()
    {
        $this->propertyService = new PropertyService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offres =  $this->propertyService->index();
        return response()->json($offres,200);
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProprieteRequest $request)
    {

        $propriete  =  $this->propertyService->store($request->validated());
        return response()->json($propriete,201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Propriete::destroy($id);
        return response()->json("",204);
        //
    }
}
