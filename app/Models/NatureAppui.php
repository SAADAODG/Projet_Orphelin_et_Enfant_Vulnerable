<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Nature d'un appui (table de référence, paramétrable dans Paramètres > Natures d'appui). Les codes
 * « scolaire » et « formation_professionnelle » correspondent aux types d'appui des sessions de l'État
 * (SessionParrainage::TYPES_APPUI).
 */
class NatureAppui extends Model
{
    /** Natures utilisées par les règles de l'application : ni supprimées ni désactivées. */
    public const SYSTEME = [SessionParrainage::TYPE_SCOLAIRE, SessionParrainage::TYPE_FORMATION];

    /** Natures dont le libellé reprend celui des types d'appui des sessions : non modifiable. */
    public const LIBELLE_FIXE = [SessionParrainage::TYPE_SCOLAIRE, SessionParrainage::TYPE_FORMATION];

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

    public function estSysteme(): bool
    {
        return in_array($this->code, self::SYSTEME, true);
    }

    /** Nombre d'appuis et d'engagements de parrains qui utilisent cette nature. */
    public function nombreUtilisations(): int
    {
        return AppuiPartenaire::where('nature_appui_id', $this->id)->count()
            + EngagementParrain::pluck('natures_appui')->filter(fn ($ids) => in_array($this->id, array_map('intval', $ids ?? []), true))->count();
    }

    /** Nature à partir de son identifiant ou de son code (« scolaire », « sante »…). */
    public static function trouver(int|string $nature): ?self
    {
        return is_int($nature) || ctype_digit((string) $nature)
            ? static::find((int) $nature)
            : static::where('code', $nature)->first();
    }
}
