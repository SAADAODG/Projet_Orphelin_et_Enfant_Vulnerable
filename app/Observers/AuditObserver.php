<?php

namespace App\Observers;

use App\Models\Commune;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\Plainte;
use App\Models\Province;
use App\Models\QuickLink;
use App\Models\Region;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * Journal d'audit automatique : création, modification et suppression des enregistrements sensibles
 * (utilisateurs, dossiers OEV, signalements, plaintes, paramètres, localités, rôles).
 *
 * Seules les actions d'un utilisateur connecté sont tracées (pas les imports en console ni les seeders).
 * Les valeurs saisies ne sont pas recopiées : seuls les noms des champs modifiés le sont, sauf pour les
 * changements d'étape (dossier, signalement, plainte) dont l'ancien et le nouvel état sont notés.
 * Le parrainage trace lui-même ses actions (JournalActivite::consigner) : il n'est pas observé ici.
 */
class AuditObserver
{
    /** Champs jamais signalés comme « modifiés » (techniques ou déjà tracés ailleurs). */
    private const CHAMPS_IGNORES = [
        'updated_at', 'created_at', 'remember_token', 'email_verified_at', 'lu_at',
        // Désactivation d'un OEV : tracée par DesactivationOevController
        'desactivation_etat', 'desactivation_motif', 'desactivation_commentaire', 'desactivation_demandee_at',
        'desactivation_demandee_par', 'desactive_at', 'desactive_par',
    ];

    /** Modèles observés, enregistrés dans AppServiceProvider. */
    public const MODELES = [
        User::class, Oev::class, Signalement::class, Plainte::class, SiteSetting::class, QuickLink::class, Service::class,
        Region::class, Province::class, Commune::class, Village::class, Role::class,
    ];

    public function created(Model $modele): void
    {
        $this->tracer($modele, 'creation', 'Création de ' . $this->designation($modele));
    }

    public function updated(Model $modele): void
    {
        $champs = array_values(array_diff(array_keys($modele->getChanges()), self::CHAMPS_IGNORES));
        if (! $champs) {
            return;
        }

        // Changement d'étape : ancien → nouvel état, lisibles
        [$champEtat, $libelles] = match (true) {
            $modele instanceof Oev => ['statut_dossier', Oev::ETATS],
            $modele instanceof Signalement => ['statut', Signalement::STATUTS],
            $modele instanceof Plainte => ['statut', Plainte::STATUTS],
            default => [null, []],
        };
        if ($champEtat && in_array($champEtat, $champs, true)) {
            $ancien = $modele->getOriginal($champEtat);
            $nouveau = $modele->{$champEtat};
            $this->tracer($modele, 'etat', ucfirst($this->designation($modele)) . ' : ' . ($libelles[$ancien] ?? $ancien) . ' → ' . ($libelles[$nouveau] ?? $nouveau), [
                'ancien_etat' => $ancien,
                'nouvel_etat' => $nouveau,
            ]);
            $champs = array_values(array_diff($champs, [$champEtat]));
            if (! $champs) {
                return;
            }
        }

        // Mot de passe : signalé sans jamais être recopié
        $champs = array_map(fn ($champ) => $champ === 'password' ? 'mot de passe' : $champ, $champs);
        $this->tracer($modele, 'modification', 'Modification de ' . $this->designation($modele), ['champs' => $champs]);
    }

    public function deleted(Model $modele): void
    {
        $this->tracer($modele, 'suppression', 'Suppression de ' . $this->designation($modele));
    }

    private function tracer(Model $modele, string $operation, string $description, array $details = []): void
    {
        if (! auth()->check()) {
            return;
        }

        JournalActivite::consigner($this->module($modele) . '.' . $operation, $modele, $description, $details);
    }

    private function module(Model $modele): string
    {
        return match (true) {
            $modele instanceof User => 'utilisateur',
            $modele instanceof Oev => 'oev',
            $modele instanceof Signalement => 'signalement',
            $modele instanceof Plainte => 'plainte',
            $modele instanceof Role => 'role',
            $modele instanceof Region, $modele instanceof Province, $modele instanceof Commune, $modele instanceof Village => 'localite',
            default => 'parametres',
        };
    }

    /** Ex : « l’utilisateur Awa (awa@…) », « le dossier DOS-2026-0001 (OUEDRAOGO Awa) ». */
    private function designation(Model $modele): string
    {
        return match (true) {
            $modele instanceof User => "l’utilisateur {$modele->name} ({$modele->email})",
            $modele instanceof Oev => "le dossier {$modele->reference()} ({$modele->nomComplet()})",
            $modele instanceof Signalement => "le signalement {$modele->recepisse}",
            $modele instanceof Plainte => "la plainte {$modele->reference}",
            $modele instanceof Role => "le rôle « {$modele->name} »",
            $modele instanceof Region => "la région {$modele->nom}",
            $modele instanceof Province => "la province {$modele->nom}",
            $modele instanceof Commune => "la commune {$modele->nom}",
            $modele instanceof Village => "le village / secteur {$modele->nom}",
            $modele instanceof QuickLink => "le lien rapide « {$modele->libelle} »",
            $modele instanceof Service => "le service « {$modele->libelle} »",
            default => 'les paramètres du site',
        };
    }
}
