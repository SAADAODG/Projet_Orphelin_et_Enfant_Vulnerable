<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Nature d'un appui (table de référence). Les codes « scolaire » et « formation_professionnelle »
 * correspondent aux types d'appui des sessions de l'État (SessionParrainage::TYPES_APPUI).
 */
class NatureAppui extends Model
{
    public const AUTRE = 'autre';

    protected $table = 'natures_appui';

    protected $fillable = ['code', 'libelle', 'scolaire', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['scolaire' => 'boolean', 'actif' => 'boolean'];
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('libelle');
    }

    /** Nature à partir de son identifiant ou de son code (« scolaire », « sante »…). */
    public static function trouver(int|string $nature): ?self
    {
        return is_int($nature) || ctype_digit((string) $nature)
            ? static::find((int) $nature)
            : static::where('code', $nature)->first();
    }
}
