<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_site',
        'slogan',
        'structure_nom',
        'structure_nom_complet',
        'structure_description',
        'ministere_tutelle',
        'police_admin',
        'police_public',
        'contact_adresse',
        'contact_telephone',
        'contact_email',
    ];

    /**
     * Table à ligne unique : retourne toujours le même enregistrement,
     * en le créant avec des valeurs par défaut s'il n'existe pas encore.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'nom_site' => 'Programme OEV',
            'slogan' => 'Programme OEV – Burkina Faso',
            'structure_nom' => 'DGFE',
            'ministere_tutelle' => 'Ministère de la Famille et de la Solidarité',
        ]);
    }

    /**
     * Entrée résolue de config('fonts') pour la police de l'interface admin — retombe
     * sur "systeme" si la valeur stockée ne correspond (plus) à aucune police vétée
     * (ex: après retrait d'une police de la liste).
     *
     * @return array{label: string, family: string, stylesheet: ?string}
     */
    public function getPoliceAdminConfigAttribute(): array
    {
        return config('fonts.' . $this->police_admin) ?? config('fonts.systeme');
    }

    /**
     * Même résolution que policeAdminConfig, pour la police du site public.
     *
     * @return array{label: string, family: string, stylesheet: ?string}
     */
    public function getPolicePublicConfigAttribute(): array
    {
        return config('fonts.' . $this->police_public) ?? config('fonts.systeme');
    }
}
