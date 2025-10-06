<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommuneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // On récupère les IDs des départements par leur nom
        $depDakar       = DB::table('departements')->where('nom', 'Dakar')->first();
        $depGuediawaye  = DB::table('departements')->where('nom', 'Guédiawaye')->first();
        $depPikine      = DB::table('departements')->where('nom', 'Pikine')->first();
        $depRufisque    = DB::table('departements')->where('nom', 'Rufisque')->first();

        $depThies       = DB::table('departements')->where('nom', 'Thiès')->first();
        $depMbour       = DB::table('departements')->where('nom', 'Mbour')->first();
        $depTivaouane   = DB::table('departements')->where('nom', 'Tivaouane')->first();

        $communes = [
            // Département de Dakar
            ['nom' => 'Plateau', 'departement_id' => $depDakar->id],
            ['nom' => 'Médina', 'departement_id' => $depDakar->id],
            ['nom' => 'Grand Dakar', 'departement_id' => $depDakar->id],
            ['nom' => 'Parcelles Assainies', 'departement_id' => $depDakar->id],
            ['nom' => 'Yoff', 'departement_id' => $depDakar->id],
            ['nom' => 'Ngor', 'departement_id' => $depDakar->id],

            // Département de Guédiawaye
            ['nom' => 'Golf Sud', 'departement_id' => $depGuediawaye->id],
            ['nom' => 'Sam Notaire', 'departement_id' => $depGuediawaye->id],
            ['nom' => 'Ndiarème Limamoulaye', 'departement_id' => $depGuediawaye->id],
            ['nom' => 'Wakhinane Nimzatt', 'departement_id' => $depGuediawaye->id],
            ['nom' => 'Médina Gounass', 'departement_id' => $depGuediawaye->id],

            // Département de Pikine
            ['nom' => 'Pikine Nord', 'departement_id' => $depPikine->id],
            ['nom' => 'Pikine Est', 'departement_id' => $depPikine->id],
            ['nom' => 'Guinaw Rail', 'departement_id' => $depPikine->id],
            ['nom' => 'Thiaroye', 'departement_id' => $depPikine->id],
            ['nom' => 'Yeumbeul', 'departement_id' => $depPikine->id],

            // Département de Rufisque
            ['nom' => 'Rufisque Est', 'departement_id' => $depRufisque->id],
            ['nom' => 'Rufisque Ouest', 'departement_id' => $depRufisque->id],
            ['nom' => 'Rufisque Nord', 'departement_id' => $depRufisque->id],
            ['nom' => 'Bargny', 'departement_id' => $depRufisque->id],
            ['nom' => 'Sébikotane', 'departement_id' => $depRufisque->id],
            ['nom' => 'Sangalkam', 'departement_id' => $depRufisque->id],

            // Département de Thiès
            ['nom' => 'Thiès Est', 'departement_id' => $depThies->id],
            ['nom' => 'Thiès Ouest', 'departement_id' => $depThies->id],
            ['nom' => 'Thiès Nord', 'departement_id' => $depThies->id],
            ['nom' => 'Khombole', 'departement_id' => $depThies->id],
            ['nom' => 'Pout', 'departement_id' => $depThies->id],
            ['nom' => 'Fandène', 'departement_id' => $depThies->id],

            // Département de Mbour
            ['nom' => 'Mbour', 'departement_id' => $depMbour->id],
            ['nom' => 'Saly', 'departement_id' => $depMbour->id],
            ['nom' => 'Joal-Fadiouth', 'departement_id' => $depMbour->id],
            ['nom' => 'Ngaparou', 'departement_id' => $depMbour->id],
            ['nom' => 'Somone', 'departement_id' => $depMbour->id],
            ['nom' => 'Popenguine', 'departement_id' => $depMbour->id],

            // Département de Tivaouane
            ['nom' => 'Tivaouane', 'departement_id' => $depTivaouane->id],
            ['nom' => 'Mékhé', 'departement_id' => $depTivaouane->id],
            ['nom' => 'Mboro', 'departement_id' => $depTivaouane->id],
            ['nom' => 'Pékesse', 'departement_id' => $depTivaouane->id],
            ['nom' => 'Chérif Lô', 'departement_id' => $depTivaouane->id],
        ];

        DB::table('communes')->insert($communes);

        //
    }
}
