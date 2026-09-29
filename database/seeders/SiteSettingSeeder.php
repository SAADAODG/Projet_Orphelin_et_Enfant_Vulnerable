<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Identité de la plateforme OEV (Orphelins et Enfants Vulnérables), reprise
     * des textes affichés jusqu'ici en dur dans les layouts — à ajuster ensuite
     * via l'écran Paramètres généraux.
     */
    public function run(): void
    {
        SiteSetting::current()->update([
            'nom_site' => 'Programme OEV',
            'slogan' => 'Programme OEV – Burkina Faso',
            'structure_nom' => 'DGFE',
            'structure_nom_complet' => 'Direction Générale de la Famille et de l\'Enfant',
            'structure_description' => 'Plateforme de recensement, de protection et de suivi des droits des orphelins et enfants vulnérables au Burkina Faso.',
            'ministere_tutelle' => 'Ministère de la Famille et de la Solidarité',
            'police_admin' => 'systeme',
            'police_public' => 'inter',
            'contact_adresse' => 'Ouagadougou, Burkina Faso',
            'contact_telephone' => '+226 25 30 00 00',
            'contact_email' => 'contact@oev.gov.bf',
        ]);
    }
}
