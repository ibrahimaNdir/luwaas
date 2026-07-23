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
        $depDakar      = DB::table('departements')->where('nom', 'Dakar')->first();
        $depGuediawaye = DB::table('departements')->where('nom', 'Guédiawaye')->first();
        $depPikine     = DB::table('departements')->where('nom', 'Pikine')->first();
        $depRufisque   = DB::table('departements')->where('nom', 'Rufisque')->first();

       

        $communes = [

            // -------------------------------------------------------
            // Département de Dakar (19 communes)
            // -------------------------------------------------------
            ['nom' => 'Plateau',                    'departement_id' => $depDakar->id],
            ['nom' => 'Médina',                     'departement_id' => $depDakar->id],
            ['nom' => 'Grand Dakar',                'departement_id' => $depDakar->id],
            ['nom' => 'Parcelles Assainies',        'departement_id' => $depDakar->id],
            ['nom' => 'Yoff',                       'departement_id' => $depDakar->id],
            ['nom' => 'Ngor',                       'departement_id' => $depDakar->id],
            ['nom' => 'Ouakam',                     'departement_id' => $depDakar->id],
            ['nom' => 'Almadies',                   'departement_id' => $depDakar->id],
            ['nom' => 'Mermoz-Sacré-Cœur',         'departement_id' => $depDakar->id],
            ['nom' => 'Fann-Point E-Amitié',        'departement_id' => $depDakar->id],
            ['nom' => 'Gueule Tapée-Fass-Colobane', 'departement_id' => $depDakar->id],
            ['nom' => 'Dieuppeul-Derklé',           'departement_id' => $depDakar->id],
            ['nom' => 'Sicap Liberté',              'departement_id' => $depDakar->id],
            ['nom' => 'HLM',                        'departement_id' => $depDakar->id],
            ['nom' => 'Biscuiterie',                'departement_id' => $depDakar->id],
            ['nom' => 'Hann Bel-Air',              'departement_id' => $depDakar->id],
            ['nom' => 'Patte d\'Oie',              'departement_id' => $depDakar->id],
            ['nom' => 'Cambérène',                  'departement_id' => $depDakar->id],
            ['nom' => 'Gorée',                      'departement_id' => $depDakar->id],

            // -------------------------------------------------------
            // Département de Guédiawaye (5 communes)
            // -------------------------------------------------------
            ['nom' => 'Golf Sud',                   'departement_id' => $depGuediawaye->id],
            ['nom' => 'Sam Notaire',                'departement_id' => $depGuediawaye->id],
            ['nom' => 'Ndiarème Limamoulaye',       'departement_id' => $depGuediawaye->id],
            ['nom' => 'Wakhinane Nimzatt',          'departement_id' => $depGuediawaye->id],
            ['nom' => 'Médina Gounass',             'departement_id' => $depGuediawaye->id],

            // -------------------------------------------------------
            // Département de Pikine (16 communes)
            // -------------------------------------------------------
            ['nom' => 'Pikine Nord',                'departement_id' => $depPikine->id],
            ['nom' => 'Pikine Est',                 'departement_id' => $depPikine->id],
            ['nom' => 'Pikine Ouest',               'departement_id' => $depPikine->id],
            ['nom' => 'Guinaw Rail Nord',           'departement_id' => $depPikine->id],
            ['nom' => 'Guinaw Rail Sud',            'departement_id' => $depPikine->id],
            ['nom' => 'Thiaroye sur Mer',           'departement_id' => $depPikine->id],
            ['nom' => 'Thiaroye Gare',              'departement_id' => $depPikine->id],
            ['nom' => 'Djida Thiaroye Kao',         'departement_id' => $depPikine->id],
            ['nom' => 'Tivaouane Diacksao',         'departement_id' => $depPikine->id],
            ['nom' => 'Dalifort',                   'departement_id' => $depPikine->id],
            ['nom' => 'Diamaguène Sicap Mbao',      'departement_id' => $depPikine->id],
            ['nom' => 'Mbao',                       'departement_id' => $depPikine->id],
            ['nom' => 'Yeumbeul Nord',              'departement_id' => $depPikine->id],
            ['nom' => 'Yeumbeul Sud',               'departement_id' => $depPikine->id],
            ['nom' => 'Keur Massar',                'departement_id' => $depPikine->id],
            ['nom' => 'Malika',                     'departement_id' => $depPikine->id],

            // -------------------------------------------------------
            // Département de Rufisque (9 communes)
            // -------------------------------------------------------
            ['nom' => 'Rufisque Est',               'departement_id' => $depRufisque->id],
            ['nom' => 'Rufisque Ouest',             'departement_id' => $depRufisque->id],
            ['nom' => 'Rufisque Nord',              'departement_id' => $depRufisque->id],
            ['nom' => 'Bargny',                     'departement_id' => $depRufisque->id],
            ['nom' => 'Sébikotane',                 'departement_id' => $depRufisque->id],
            ['nom' => 'Sangalkam',                  'departement_id' => $depRufisque->id],
            ['nom' => 'Bambilor',                   'departement_id' => $depRufisque->id],
            ['nom' => 'Yène',                       'departement_id' => $depRufisque->id],
            ['nom' => 'Diamniadio',                 'departement_id' => $depRufisque->id],

            // -------------------------------------------------------
            // Département de Thiès (10 communes)
            // -------------------------------------------------------
           
        ];

        DB::table('communes')->insert($communes);
    }
}