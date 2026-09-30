<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cohérence du formulaire :
 * - « Le tuteur possède-t-il une CNIB ? » : conditionne le numéro et la pièce CNIB ;
 * - le lien de parenté du tuteur devient une liste (codes) + précision si « autre ».
 */
return new class extends Migration
{
    /** Texte libre saisi jusqu'ici → code de la liste. */
    private const LIENS = [
        'mere' => 'mere', 'maman' => 'mere', 'pere' => 'pere', 'papa' => 'pere',
        'grand-mere' => 'grand_parent', 'grand-pere' => 'grand_parent', 'grand mere' => 'grand_parent', 'grand pere' => 'grand_parent',
        'oncle' => 'oncle_tante', 'tante' => 'oncle_tante', 'frere' => 'frere_soeur', 'soeur' => 'frere_soeur', 'sœur' => 'frere_soeur',
    ];

    public function up(): void
    {
        Schema::table('oevs', function (Blueprint $table): void {
            $table->boolean('tuteur_a_cnib')->nullable();
            $table->string('tuteur_lien_precision', 100)->nullable();
        });

        foreach (DB::table('oevs')->get(['id', 'tuteur_lien', 'tuteur_cnib']) as $ligne) {
            $maj = [];
            if ($ligne->tuteur_cnib) {
                $maj['tuteur_a_cnib'] = true;
            }
            if ($ligne->tuteur_lien) {
                $texte = mb_strtolower(trim($ligne->tuteur_lien));
                $texte = strtr($texte, ['é' => 'e', 'è' => 'e', 'ê' => 'e']);
                $maj['tuteur_lien'] = self::LIENS[$texte] ?? 'autre_parent';
                if (! isset(self::LIENS[$texte])) {
                    $maj['tuteur_lien_precision'] = $ligne->tuteur_lien;
                }
            }
            if ($maj) {
                DB::table('oevs')->where('id', $ligne->id)->update($maj);
            }
        }
    }

    public function down(): void
    {
        $colonnes = array_values(array_filter(['tuteur_a_cnib', 'tuteur_lien_precision'], fn ($c) => Schema::hasColumn('oevs', $c)));
        if ($colonnes) {
            Schema::table('oevs', fn (Blueprint $table) => $table->dropColumn($colonnes));
        }
    }
};
