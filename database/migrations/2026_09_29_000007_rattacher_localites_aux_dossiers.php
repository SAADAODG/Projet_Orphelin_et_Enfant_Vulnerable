<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Remplace la localité saisie en texte libre (colonnes region / province / commune) des
 * signalements, plaintes et OEV par des clés étrangères vers regions / provinces / communes.
 *
 * Les lignes existantes sont rattachées par correspondance de nom (sans tenir compte de la
 * casse, des accents ni de la ponctuation) ; une valeur introuvable reste à NULL.
 */
return new class extends Migration
{
    /** table => anciennes colonnes texte à convertir */
    private const TABLES = [
        'signalements' => ['region', 'province'],
        'plaintes' => ['region', 'province'],
        'oevs' => ['region', 'province', 'commune'],
    ];

    public function up(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
                $t->foreignId('province_id')->nullable()->constrained('provinces')->restrictOnDelete();
                $t->foreignId('commune_id')->nullable()->constrained('communes')->restrictOnDelete();
            });
        }

        [$regions, $provinces, $communes] = $this->referentiels();

        foreach (self::TABLES as $table => $colonnes) {
            DB::table($table)->select(array_merge(['id'], $colonnes))->orderBy('id')->each(function ($ligne) use ($table, $regions, $provinces, $communes) {
                $province = $provinces[$this->cle($ligne->province ?? null)] ?? null;
                $regionId = $province['region_id'] ?? ($regions[$this->cle($ligne->region ?? null)] ?? null);
                $communeId = $province ? ($communes[$province['id'] . '|' . $this->cle($ligne->commune ?? null)] ?? null) : null;

                DB::table($table)->where('id', $ligne->id)->update([
                    'region_id' => $regionId,
                    'province_id' => $province['id'] ?? null,
                    'commune_id' => $communeId,
                ]);
            });

            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($colonnes));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $colonnes) {
            Schema::table($table, function (Blueprint $t) use ($colonnes) {
                foreach ($colonnes as $colonne) {
                    $t->string($colonne, 100)->nullable();
                }
            });

            DB::table($table)->select(['id', 'region_id', 'province_id', 'commune_id'])->orderBy('id')->each(function ($ligne) use ($table, $colonnes) {
                $valeurs = [
                    'region' => DB::table('regions')->where('id', $ligne->region_id)->value('nom'),
                    'province' => DB::table('provinces')->where('id', $ligne->province_id)->value('nom'),
                    'commune' => DB::table('communes')->where('id', $ligne->commune_id)->value('nom'),
                ];
                DB::table($table)->where('id', $ligne->id)->update(array_intersect_key($valeurs, array_flip($colonnes)));
            });

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('commune_id');
                $t->dropConstrainedForeignId('province_id');
                $t->dropConstrainedForeignId('region_id');
            });
        }
    }

    /** Index nom normalisé => id, pour rattacher les anciennes valeurs texte. */
    private function referentiels(): array
    {
        $regions = [];
        foreach (DB::table('regions')->get() as $region) {
            $regions[$this->cle($region->nom)] = $region->id;
        }

        $provinces = [];
        foreach (DB::table('provinces')->get() as $province) {
            $entree = ['id' => $province->id, 'region_id' => $province->region_id];
            $provinces[$this->cle($province->nom)] = $entree;
            // « Koosin (Kossi) » doit aussi répondre à l'ancienne appellation « Kossi »
            if (preg_match('/\(([^)]+)\)/', $province->nom, $m)) {
                $provinces[$this->cle($m[1])] ??= $entree;
            }
        }

        $communes = [];
        foreach (DB::table('communes')->get() as $commune) {
            $communes[$commune->province_id . '|' . $this->cle($commune->nom)] = $commune->id;
        }

        return [$regions, $provinces, $communes];
    }

    private function cle(?string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $nom)));
    }
};
