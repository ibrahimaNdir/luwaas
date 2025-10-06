<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;


class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = [
            'Dakar',
            'Thiès',
        ];
        foreach ($regions as $region) {
            DB::table('regions')->insert([
                'nom' => $region,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }



    //

}
