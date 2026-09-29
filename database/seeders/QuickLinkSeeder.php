<?php

namespace Database\Seeders;

use App\Models\QuickLink;
use Illuminate\Database\Seeder;

class QuickLinkSeeder extends Seeder
{
    public function run(): void
    {
        $liens = [
            ['libelle' => 'Signaler un OEV', 'url' => '/signaler'],
            ['libelle' => 'Suivi du signalement', 'url' => '/suivi'],
            ['libelle' => 'Déposer une plainte', 'url' => '/plainte'],
            ['libelle' => 'Décrets & Éligibilité', 'url' => '/a-propos'],
            ['libelle' => 'Connexion Agent', 'url' => '/login'],
        ];

        foreach ($liens as $ordre => $lien) {
            QuickLink::updateOrCreate(
                ['libelle' => $lien['libelle']],
                ['url' => $lien['url'], 'ordre' => $ordre]
            );
        }
    }
}
