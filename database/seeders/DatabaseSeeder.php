<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@oev.gov.bf',
            'password' => bcrypt('admin1234'),
        ]);
        $admin->assignRole('superAdmin');

        $responsable = User::factory()->create([
            'name' => 'Responsable DGFE',
            'email' => 'responsable@oev.gov.bf',
            'password' => bcrypt('admin1234'),
        ]);
        $responsable->assignRole('responsable DGFE');

        $agent = User::factory()->create([
            'name' => 'Agent DGFE',
            'email' => 'agent@oev.gov.bf',
            'password' => bcrypt('admin1234'),
        ]);
        $agent->assignRole('agent DGFE');

        $dr = User::factory()->create([
            'name' => 'Directeur Régional',
            'email' => 'dr@oev.gov.bf',
            'password' => bcrypt('admin1234'),
        ]);
        $dr->assignRole('DR');

        $dp = User::factory()->create([
            'name' => 'Directeur Provincial',
            'email' => 'dp@oev.gov.bf',
            'password' => bcrypt('admin1234'),
        ]);
        $dp->assignRole('DP');
    }
}
