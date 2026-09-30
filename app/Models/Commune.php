<?php

namespace App\Models;

use App\Models\Concerns\EstUtiliseeParDesDossiers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    use EstUtiliseeParDesDossiers, HasFactory;

    protected $fillable = [
        'province_id',
        'nom',
    ];

    /**
     * La province à laquelle appartient cette commune.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * Les villages (ou secteurs) de cette commune.
     */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    /** Une commune qui a des villages ne peut pas être supprimée (clé étrangère en restrictOnDelete). */
    public function aDesVillages(): bool
    {
        return $this->villages()->exists();
    }
}
