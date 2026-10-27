<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fonds d'une session de parrainage reçus par un établissement (école ou centre de formation) : montant et
 * nombre d'OEV au moment du paiement, date de réception, référence du virement.
 */
class PaiementEtablissement extends Model
{
    protected $table = 'paiements_etablissement';

    protected $fillable = [
        'session_parrainage_id', 'etablissement_id', 'montant', 'nombre_oev', 'recu_le', 'reference', 'saisi_par',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'nombre_oev' => 'integer',
            'recu_le' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionParrainage::class, 'session_parrainage_id');
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'etablissement_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
