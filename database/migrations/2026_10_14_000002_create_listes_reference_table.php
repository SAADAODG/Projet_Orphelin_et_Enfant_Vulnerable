<?php

use App\Models\ListeReference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listes de choix de la fiche OEV paramétrables dans Paramètres : groupes de population, lien du tuteur,
 * lieux de vie (conditions de vie) et situations de vulnérabilité. Remplies avec les valeurs d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listes_reference', function (Blueprint $table) {
            $table->id();
            $table->string('liste', 40)->comment('groupes-population, liens-tuteur, lieux-de-vie, vulnerabilites');
            $table->string('code', 60)->comment('Valeur enregistrée dans le dossier');
            $table->string('libelle');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('systeme')->default(false)->comment('Utilisé par les règles de l’application : ni supprimable ni désactivable');
            $table->timestamps();

            $table->unique(['liste', 'code']);
        });

        $maintenant = now();
        DB::table('listes_reference')->insert(array_map(
            fn ($ligne) => $ligne + ['created_at' => $maintenant, 'updated_at' => $maintenant],
            ListeReference::valeursInitiales(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('listes_reference');
    }
};
