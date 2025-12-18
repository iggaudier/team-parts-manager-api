<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Team::create([
            'name' => 'Team A',
            'description' => 'Team A',
        ]);

        Team::create([
            'name' => 'Team B',
            'description' => 'Team B',
        ]);
    }
}
