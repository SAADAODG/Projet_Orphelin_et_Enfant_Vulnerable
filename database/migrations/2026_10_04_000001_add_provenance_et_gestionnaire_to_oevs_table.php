<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fiche OEV :
 *  - lieu de provenance choisi parmi les communes (le texte reste pour un lieu hors référentiel / hors du pays) ;
 *  - gestionnaire du cas : agent qui suit l'enfant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->foreignId('lieu_provenance_commune_id')->nullable()->after('lieu_provenance')->constrained('communes')->restrictOnDelete();
            $table->string('gestionnaire_nom', 150)->nullable()->after('niveau_priorite');
            $table->string('gestionnaire_fonction', 150)->nullable()->after('gestionnaire_nom');
            $table->string('gestionnaire_contact', 20)->nullable()->after('gestionnaire_fonction');
        });

        // Provenance saisie en texte : rattachée à la commune du même nom quand elle existe
        $communes = [];
        foreach (DB::table('communes')->get(['id', 'nom']) as $commune) {
            $communes[$this->cle($commune->nom)] ??= $commune->id;
        }
        foreach (DB::table('oevs')->whereNotNull('lieu_provenance')->get(['id', 'lieu_provenance']) as $ligne) {
            if ($id = $communes[$this->cle($ligne->lieu_provenance)] ?? null) {
                DB::table('oevs')->where('id', $ligne->id)->update(['lieu_provenance_commune_id' => $id, 'lieu_provenance' => null]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('oevs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lieu_provenance_commune_id');
            $table->dropColumn(['gestionnaire_nom', 'gestionnaire_fonction', 'gestionnaire_contact']);
        });
    }

    private function cle(?string $nom): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $nom)));
    }
};
