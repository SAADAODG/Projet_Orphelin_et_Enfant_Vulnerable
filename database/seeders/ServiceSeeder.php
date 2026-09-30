<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['libelle' => 'Prise en charge santé', 'url' => '#'],
            ['libelle' => "Bourses d'études", 'url' => '#'],
            ['libelle' => 'Carte ou attestation OEV', 'url' => '#'],
            ['libelle' => 'Support Usagers', 'url' => '#'],
        ];

        foreach ($services as $ordre => $service) {
            Service::updateOrCreate(
                ['libelle' => $service['libelle']],
                ['url' => $service['url'], 'ordre' => $ordre]
            );
        }
    }
}
