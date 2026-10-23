<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nature d'appui « Autre » retirée : les natures se paramètrent dans Paramètres > Natures d'appui.
 * Supprimée si aucun appui ne l'utilise, sinon désactivée (les appuis existants gardent leur libellé).
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('natures_appui')->where('code', 'autre')->value('id');
        if (! $id) {
            return;
        }

        if (DB::table('appuis_partenaires')->where('nature_appui_id', $id)->exists()) {
            DB::table('natures_appui')->whereKey($id)->update(['actif' => false, 'updated_at' => now()]);

            return;
        }
        DB::table('natures_appui')->whereKey($id)->delete();
    }

    public function down(): void
    {
        if (DB::table('natures_appui')->where('code', 'autre')->exists()) {
            DB::table('natures_appui')->where('code', 'autre')->update(['actif' => true, 'updated_at' => now()]);

            return;
        }
        DB::table('natures_appui')->insert([
            'code' => 'autre', 'libelle' => 'Autre', 'scolaire' => false,
            'ordre' => (int) DB::table('natures_appui')->max('ordre') + 1, 'actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
