<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // System Admin
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'system_admin',
        ]);

        // Team Admin for Team A
        User::create([
            'name' => 'Team A Admin',
            'email' => 'a-admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'team_admin',
            'team_id' => 1,
        ]);

        // Team Member for Team A
        User::create([
            'name' => 'Team A Member',
            'email' => 'a-member@example.com',
            'password' => Hash::make('password'),
            'role' => 'team_member',
            'team_id' => 1,
        ]);
    }
}
