<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zone d'intervention d'un parrain : une région entière ou une province. */
class ParrainZone extends Model
{
    protected $table = 'parrain_zones';

    protected $fillable = ['parrain_id', 'region_id', 'province_id'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function libelle(): string
    {
        return $this->province ? "{$this->province->nom} (province)" : (string) $this->region?->nom;
    }
}
