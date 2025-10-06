<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // On récupère les IDs des régions par leur nom
        $regionDakar = DB::table('regions')->where('nom', 'Dakar')->first();
        $regionThies = DB::table('regions')->where('nom', 'Thiès')->first();

        $departements = [
            // Région de Dakar
            ['nom' => 'Dakar', 'region_id' => $regionDakar->id],
            ['nom' => 'Guédiawaye', 'region_id' => $regionDakar->id],
            ['nom' => 'Pikine', 'region_id' => $regionDakar->id],
            ['nom' => 'Rufisque', 'region_id' => $regionDakar->id],

            // Région de Thiès
            ['nom' => 'Thiès', 'region_id' => $regionThies->id],
            ['nom' => 'Mbour', 'region_id' => $regionThies->id],
            ['nom' => 'Tivaouane', 'region_id' => $regionThies->id],
        ];

        DB::table('departements')->insert($departements);

        //
    }
}






