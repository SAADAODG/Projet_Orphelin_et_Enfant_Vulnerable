<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\Plainte;
use App\Models\Signalement;
use Database\Seeders\CommuneSeeder;
use Database\Seeders\LocaliteSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Recherche par nom de localité (scope ouLocaliteContient) : la nouvelle écriture (identifiants des localités
 * lus d'abord, « IN (…) ») trouve exactement les mêmes enregistrements que l'ancienne (« OR EXISTS »), pour
 * des termes visant une région, une province, une commune, plusieurs à la fois, ou aucune.
 */
class RechercheLocaliteEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    /** Ancienne écriture, conservée telle quelle. */
    private function ancienne(Builder $query, string $terme): Builder
    {
        foreach (['region', 'province', 'commune'] as $relation) {
            $query->orWhereHas($relation, fn (Builder $q) => $q->whereLike('nom', $terme));
        }

        return $query;
    }

    public function test_la_recherche_par_localite_equivaut_a_l_ancienne_ecriture(): void
    {
        $this->seed([LocaliteSeeder::class, CommuneSeeder::class]);
        $communes = Commune::with('province.region')->orderBy('id')->take(40)->get();
        foreach ($communes as $i => $commune) {
            $champs = ['region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id];
            // Localité incomplète : sans commune, sans province, ou sans aucune localité
            $champs = match ($i % 4) {
                1 => ['commune_id' => null] + $champs,
                2 => ['commune_id' => null, 'province_id' => null] + $champs,
                3 => ['commune_id' => null, 'province_id' => null, 'region_id' => null],
                default => $champs,
            };
            Oev::create($champs + [
                'nom' => 'ENFANT', 'prenom' => Str::random(6), 'sexe' => 'F', 'date_naissance' => '2015-01-01', 'statut' => 'vulnerable',
                'handicap' => false, 'nom_tuteur' => 'TUTEUR', 'prenom_tuteur' => 'Issa', 'contact_tuteur' => '+226 70 00 00 00',
                'systeme_educatif' => 'classique', 'statut_dossier' => Oev::ETAT_INTEGRE,
            ]);
        }
        foreach ($communes->take(10) as $commune) {
            Signalement::create(['recepisse' => Str::random(10), 'enfant_nom' => 'X', 'enfant_prenom' => 'Y', 'enfant_age' => 10, 'vulnerabilite' => 'orphelin',
                'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id, 'localite' => 'Q',
                'declarant_nom' => 'D', 'declarant_prenom' => 'P', 'declarant_telephone' => '70000000', 'declarant_adresse' => 'A',
                'declarant_profession' => 'C', 'declarant_lien' => 'voisin', 'statut' => 'en_attente']);
            Plainte::create(['reference' => Str::random(10), 'objet' => 'contribution', 'description' => 'D', 'anonyme' => true, 'statut' => 'nouvelle',
                'region_id' => $commune->province->region_id, 'province_id' => $commune->province_id, 'commune_id' => $commune->id]);
        }

        // Termes : un nom de région, de province, de commune (complets ou partiels, casse changée), des lettres
        // communes à plusieurs niveaux, aucun résultat
        $premiere = $communes->first();
        $termes = collect([
            $premiere->province->region->nom, $premiere->province->nom, $premiere->nom,
            mb_strtolower(mb_substr($premiere->nom, 0, 4)), mb_strtoupper(mb_substr($premiere->province->region->nom, 1, 3)),
            'a', 'ou', 'zzzz-aucune',
        ])->merge($communes->pluck('nom')->take(8))->unique()->values();

        foreach ([Oev::class, Signalement::class, Plainte::class] as $modele) {
            foreach ($termes as $mot) {
                $terme = "%{$mot}%";
                // Comme dans les écrans : à l'intérieur d'un groupe « OU » avec un autre critère
                $attendu = $modele::query()->where(fn ($q) => $this->ancienne($q->where('id', 0), $terme))->orderBy('id')->pluck('id')->all();
                $obtenu = $modele::query()->where(fn ($q) => $q->where('id', 0)->ouLocaliteContient($terme))->orderBy('id')->pluck('id')->all();
                $this->assertSame($attendu, $obtenu, class_basename($modele) . " : écart pour « {$mot} »");
            }
        }
        // Le jeu exerce bien la recherche (au moins un terme trouve des dossiers, un autre aucun)
        $this->assertNotEmpty(Oev::where(fn ($q) => $q->ouLocaliteContient("%{$premiere->nom}%"))->pluck('id'));
        $this->assertEmpty(Oev::where(fn ($q) => $q->where('id', 0)->ouLocaliteContient('%zzzz-aucune%'))->pluck('id'));
    }
}
