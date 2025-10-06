<?php

namespace App\Services\Proprietaire;

use App\Models\Propriete;

class PropertyService
{
    public function index()
    {
        $proprietes = Propriete::all();
        return $proprietes ;
    }

    public function store(array $request)
    {
        $propriete= Propriete::create($request);
        return   $propriete;
    }

    public function show($id)
    {
        $propriete= Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        return  $propriete;
    }

    public function update(array $data, $id)
    {
        $propriete = Propriete::find($id);

        if (! $propriete) {
            return null;
        }

        $propriete->update($data);
        return  $propriete;
    }

    public function destroy($id)
    {
        $propriete = Propriete::find($id);

        if (! $propriete) {
            return false;
        }

        $propriete->delete();
        return true;
    }

}
