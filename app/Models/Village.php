<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id',
        'nom',
    ];

    /**
     * La commune à laquelle appartient ce village.
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** Seuls les dossiers OEV sont rattachés à un village. */
    public function estUtiliseeParDesDossiers(): bool
    {
        return Oev::withTrashed()->where('village_id', $this->getKey())->exists();
    }
}
