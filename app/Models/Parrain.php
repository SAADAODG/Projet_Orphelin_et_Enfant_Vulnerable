<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Parrain : partenaire qui finance une session ou appuie directement des OEV. */
class Parrain extends Model
{
    public const TYPES = [
        'ong' => 'ONG',
        'association' => 'Association',
        'entreprise' => 'Entreprise',
        'institution' => 'Institution',
        'particulier' => 'Particulier',
    ];

    protected $fillable = [
        'type', 'nom', 'contact_nom', 'telephone', 'email', 'adresse', 'actif', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(SessionParrainage::class, 'contributions_session')->withPivot('montant')->withTimestamps();
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ParrainZone::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(EngagementParrain::class)->latest('date_engagement');
    }

    public function appuis(): HasMany
    {
        return $this->hasMany(AppuiPartenaire::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /** Parrains qui interviennent dans la région (la région entière ou l'une de ses provinces). */
    public function scopeIntervenantDans(Builder $query, Region $region): Builder
    {
        // Une zone « province » porte aussi sa région : la région suffit à les trouver toutes
        return $query->whereHas('zones', fn ($q) => $q->where('region_id', $region->id));
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function libelleZones(): string
    {
        return $this->zones->map->libelle()->sort()->implode(', ') ?: 'Non précisée';
    }

    /** Un parrain qui a des appuis ou des contributions se désactive, il ne se supprime pas. */
    public function estSupprimable(): bool
    {
        return ! $this->appuis()->exists() && ! $this->sessions()->exists();
    }
}
