<?php

namespace App\Models;

use App\Models\Concerns\EstUtiliseeParDesDossiers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
