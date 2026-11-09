<?php

namespace App\Models\Concerns\Oev;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\OevDocument;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier OEV (App\Models\Oev) — Relations du dossier : pièces, auteurs des étapes, village, écoles, centre de formation, communes de naissance et de provenance.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait Relations
{
    public function documents(): HasMany
    {
        return $this->hasMany(OevDocument::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function soumetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soumis_par');
    }

    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    public function integrateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'integre_par');
    }

    public function demandeurComplement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complement_par');
    }

    public function auteurRejet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejete_par');
    }

    /** Village ou secteur de résidence (4ᵉ niveau de localité, facultatif). */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** École fréquentée, choisie dans le référentiel (Paramètres > Écoles) ; son nom est aussi dans etablissement_actuel. */
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }

    /** Centre de formation professionnelle fréquenté (référentiel des établissements). */
    public function centreFormation(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'centre_formation_id');
    }

    /** École de l'année précédente, choisie dans le référentiel ; son nom est aussi dans etablissement_precedent. */
    public function ecolePrecedente(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_precedente_id');
    }

    public function demandeurDesactivation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivation_demandee_par');
    }

    public function auteurDesactivation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactive_par');
    }

    public function lieuNaissanceCommune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'lieu_naissance_commune_id');
    }

    public function lieuProvenanceCommune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'lieu_provenance_commune_id');
    }
}
