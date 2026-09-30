<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'active', 'region_id', 'province_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /*
     * Niveau d'intervention dans le circuit du dossier enfant, déduit des permissions du rôle :
     * le niveau central intègre les OEV (tout le pays), le DR valide les dossiers de sa région,
     * le DP constitue ceux de sa province.
     */
    public const NIVEAU_CENTRAL = 'central';
    public const NIVEAU_REGION = 'region';
    public const NIVEAU_PROVINCE = 'province';

    /** Rôles de supervision : accès à tous les modules et à toutes les données, dans tout le pays. */
    public const ROLES_SUPERVISION = ['superAdmin', 'administrateur'];

    protected $table = 'users';

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** Niveau d'un rôle ; un rôle sans aucune permission du circuit relève du niveau central. */
    public static function niveauDuRole(?string $nom): string
    {
        $permissions = $nom
            ? (Role::where('name', $nom)->where('guard_name', 'web')->first()?->permissions->pluck('name') ?? collect())
            : collect();

        return match (true) {
            $permissions->contains('intégrer OEV') => self::NIVEAU_CENTRAL,
            $permissions->contains('valider dossiers') => self::NIVEAU_REGION,
            $permissions->contains('constituer dossiers') => self::NIVEAU_PROVINCE,
            default => self::NIVEAU_CENTRAL,
        };
    }

    public function supervise(): bool
    {
        return $this->hasAnyRole(self::ROLES_SUPERVISION);
    }

    /** Niveau de l'utilisateur : le plus large de ses rôles (un administrateur reste central). */
    public function niveau(): string
    {
        return match (true) {
            $this->supervise() => self::NIVEAU_CENTRAL,
            $this->can('intégrer OEV') => self::NIVEAU_CENTRAL,
            $this->can('valider dossiers') => self::NIVEAU_REGION,
            $this->can('constituer dossiers') => self::NIVEAU_PROVINCE,
            default => self::NIVEAU_CENTRAL,
        };
    }

    /** Ex : « Centre » pour un DR, « Kadiogo (Centre) » pour un DP, null au niveau central. */
    public function libelleRattachement(): ?string
    {
        return match (true) {
            $this->province !== null => "{$this->province->nom} ({$this->region?->nom})",
            $this->region !== null => $this->region->nom,
            default => null,
        };
    }
}
