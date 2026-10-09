<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Montant réservé à une région dans une session dont les quotas sont actifs (RG-04). */
class QuotaRegional extends Model
{
    protected $table = 'quotas_regionaux';

    protected $fillable = ['session_parrainage_id', 'region_id', 'montant'];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionParrainage::class, 'session_parrainage_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
