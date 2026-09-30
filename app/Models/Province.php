<?php

namespace App\Models;

use App\Models\Concerns\EstUtiliseeParDesDossiers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use EstUtiliseeParDesDossiers, HasFactory;

    protected $fillable = [
        'region_id',
        'nom',
        'chef_lieu',
    ];

    /**
     * La région à laquelle appartient cette province.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Les communes rattachées à cette province.
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
