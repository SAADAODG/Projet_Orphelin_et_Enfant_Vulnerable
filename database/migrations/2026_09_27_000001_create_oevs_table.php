<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oevs', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();

            // Informations sur l'enfant
            $table->string('nom', 100);
            $table->string('prenom', 150);
            $table->char('sexe', 1);
            $table->date('date_naissance');
            $table->string('statut', 40);
            $table->boolean('handicap')->default(false);
            $table->string('nature_handicap')->nullable();
            $table->string('systeme_educatif', 40);

            // Informations sur le parent ou tuteur
            $table->string('nom_tuteur', 100);
            $table->string('prenom_tuteur', 150);
            $table->string('contact_tuteur', 30);

            // Scolarité de l'année précédente
            $table->string('etablissement_precedent')->nullable();
            $table->decimal('moyenne_annuelle', 4, 2)->nullable();
            $table->string('appreciation', 20)->nullable();

            // Scolarité de l'année en cours
            $table->string('etablissement_actuel');
            $table->string('type_etablissement', 10);
            $table->string('classe', 50);
            $table->unsignedInteger('frais_scolarite')->default(0);

            // Localité
            $table->string('region', 100);
            $table->string('province', 100);
            $table->string('commune', 100);

            // Structure bénéficiaire du RIB
            $table->string('nom_structure_rib')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('oev_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oev_id')->constrained('oevs')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['oev_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oev_documents');
        Schema::dropIfExists('oevs');
    }
};
