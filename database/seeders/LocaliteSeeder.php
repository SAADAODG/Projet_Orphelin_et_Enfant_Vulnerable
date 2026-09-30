<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class LocaliteSeeder extends Seeder
{
    /**
     * Découpage administratif du Burkina Faso : 17 régions et 47 provinces
     * (réforme territoriale de juin 2025).
     *
     * Source : Liste des 17 Régions et des 47 Provinces avec leurs Chefs-lieux,
     * Burkina Faso, juin 2025 (avec anciennes appellations des régions).
     *
     * Les communes ne sont pas pré-remplies (plus de 350, à saisir via l'écran
     * de gestion des localités au fil de l'eau).
     */
    private array $regions = [
        [
            'nom' => 'Sourou',
            'ancien_nom' => 'Boucle du Mouhoun',
            'provinces' => [
                ['nom' => 'Koosin (Kossi)', 'chef_lieu' => 'Nouna'],
                ['nom' => 'Nayala', 'chef_lieu' => 'Toma'],
                ['nom' => 'Sourou', 'chef_lieu' => 'Tougan'],
            ],
        ],
        [
            'nom' => 'Bankui',
            'ancien_nom' => 'Boucle du Mouhoun',
            'provinces' => [
                ['nom' => 'Mouhoun', 'chef_lieu' => 'Dédougou'],
                ['nom' => 'Balé', 'chef_lieu' => 'Boromo'],
                ['nom' => 'Banwa', 'chef_lieu' => 'Solenzo'],
            ],
        ],
        [
            'nom' => 'Guiriko',
            'ancien_nom' => 'Hauts Bassins',
            'provinces' => [
                ['nom' => 'Houet', 'chef_lieu' => 'Bobo-Dioulasso'],
                ['nom' => 'Kénédougou', 'chef_lieu' => 'Orodara'],
                ['nom' => 'Tuy', 'chef_lieu' => 'Houndé'],
            ],
        ],
        [
            'nom' => 'Tannounyan',
            'ancien_nom' => 'Cascades',
            'provinces' => [
                ['nom' => 'Comoé', 'chef_lieu' => 'Banfora'],
                ['nom' => 'Léraba', 'chef_lieu' => 'Sindou'],
            ],
        ],
        [
            'nom' => 'Djoro',
            'ancien_nom' => 'Sud Ouest',
            'provinces' => [
                ['nom' => 'Bougouriba', 'chef_lieu' => 'Diébougou'],
                ['nom' => 'Ioba', 'chef_lieu' => 'Dano'],
                ['nom' => 'Noumbiel', 'chef_lieu' => 'Batié'],
                ['nom' => 'Poni', 'chef_lieu' => 'Gaoua'],
            ],
        ],
        [
            'nom' => 'Yaagda',
            'ancien_nom' => 'Nord',
            'provinces' => [
                ['nom' => 'Loroum', 'chef_lieu' => 'Titao'],
                ['nom' => 'Passoré', 'chef_lieu' => 'Yako'],
                ['nom' => 'Yatenga', 'chef_lieu' => 'Ouahigouya'],
                ['nom' => 'Zondoma', 'chef_lieu' => 'Gourcy'],
            ],
        ],
        [
            'nom' => 'Nando',
            'ancien_nom' => 'Centre Ouest',
            'provinces' => [
                ['nom' => 'Boulkiemdé', 'chef_lieu' => 'Koudougou'],
                ['nom' => 'Sanguié', 'chef_lieu' => 'Réo'],
                ['nom' => 'Sissili', 'chef_lieu' => 'Léo'],
                ['nom' => 'Ziro', 'chef_lieu' => 'Sapouy'],
            ],
        ],
        [
            'nom' => 'Soum',
            'ancien_nom' => 'Sahel',
            'provinces' => [
                ['nom' => 'Djelgodji', 'chef_lieu' => 'Djibo'],
                ['nom' => 'Karo-Peli', 'chef_lieu' => 'Arbinda'],
            ],
        ],
        [
            'nom' => 'Kuilsé',
            'ancien_nom' => 'Centre Nord',
            'provinces' => [
                ['nom' => 'Bam', 'chef_lieu' => 'Kongoussi'],
                ['nom' => 'Namentenga', 'chef_lieu' => 'Boulsa'],
                ['nom' => 'Sandbondtenga', 'chef_lieu' => 'Kaya'],
            ],
        ],
        [
            'nom' => 'Oubri',
            'ancien_nom' => 'Plateau Central',
            'provinces' => [
                ['nom' => 'Bassitenga', 'chef_lieu' => 'Ziniaré'],
                ['nom' => 'Ganzourgou', 'chef_lieu' => 'Zorgho'],
                ['nom' => 'Kourwéogo', 'chef_lieu' => 'Boussé'],
            ],
        ],
        [
            'nom' => 'Kadiogo',
            'ancien_nom' => 'Centre',
            'provinces' => [
                ['nom' => 'Kadiogo', 'chef_lieu' => 'Ouagadougou'],
            ],
        ],
        [
            'nom' => 'Nazinon',
            'ancien_nom' => 'Centre Sud',
            'provinces' => [
                ['nom' => 'Bazèga', 'chef_lieu' => 'Kombissiri'],
                ['nom' => 'Nahouri', 'chef_lieu' => 'Pô'],
                ['nom' => 'Zoundwéogo', 'chef_lieu' => 'Manga'],
            ],
        ],
        [
            'nom' => 'Nakambé',
            'ancien_nom' => 'Centre Est',
            'provinces' => [
                ['nom' => 'Boulgou', 'chef_lieu' => 'Tenkodogo'],
                ['nom' => 'Koulpélogo', 'chef_lieu' => 'Ouargaye'],
                ['nom' => 'Kourittenga', 'chef_lieu' => 'Koupèla'],
            ],
        ],
        [
            'nom' => 'Liptako',
            'ancien_nom' => 'Sahel',
            'provinces' => [
                ['nom' => 'Oudalan', 'chef_lieu' => 'Gorom-Gorom'],
                ['nom' => 'Séno', 'chef_lieu' => 'Dori'],
                ['nom' => 'Yagha', 'chef_lieu' => 'Sebba'],
            ],
        ],
        [
            'nom' => 'Goulmou',
            'ancien_nom' => 'Est',
            'provinces' => [
                ['nom' => 'Gourma', 'chef_lieu' => 'Fada N\'Gourma'],
                ['nom' => 'Kompienga', 'chef_lieu' => 'Pama'],
            ],
        ],
        [
            'nom' => 'Tapoa',
            'ancien_nom' => 'Est',
            'provinces' => [
                ['nom' => 'Dyamongou', 'chef_lieu' => 'Kantchari'],
                ['nom' => 'Gobnangou', 'chef_lieu' => 'Diapaga'],
            ],
        ],
        [
            'nom' => 'Sirba',
            'ancien_nom' => 'Est',
            'provinces' => [
                ['nom' => 'Gnagna', 'chef_lieu' => 'Bogandé'],
                ['nom' => 'Komondjari', 'chef_lieu' => 'Gayéri'],
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->regions as $regionData) {
            $region = Region::updateOrCreate(
                ['nom' => $regionData['nom']],
                ['ancien_nom' => $regionData['ancien_nom']]
            );

            foreach ($regionData['provinces'] as $provinceData) {
                $region->provinces()->updateOrCreate(
                    ['nom' => $provinceData['nom']],
                    ['chef_lieu' => $provinceData['chef_lieu']]
                );
            }
        }
    }
}
