<?php

namespace App\Listeners;

use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Journal d'audit : connexions, déconnexions, échecs de connexion, et rôles / permissions attribués ou
 * retirés (événements de spatie/laravel-permission, activés dans AppServiceProvider).
 */
class JournalAudit
{
    public function connexion(Login $evenement): void
    {
        if ($evenement->user instanceof User) {
            JournalActivite::consigner('auth.connexion', $evenement->user, "Connexion de {$evenement->user->name}", [], $evenement->user);
        }
    }

    public function deconnexion(Logout $evenement): void
    {
        if ($evenement->user instanceof User) {
            JournalActivite::consigner('auth.deconnexion', $evenement->user, "Déconnexion de {$evenement->user->name}", [], $evenement->user);
        }
    }

    /** Mot de passe erroné ou compte inconnu : l'adresse saisie est notée, jamais le mot de passe. */
    public function echec(Failed $evenement): void
    {
        $email = (string) ($evenement->credentials['email'] ?? '');
        JournalActivite::consigner('auth.echec', $evenement->user instanceof User ? $evenement->user : null, "Échec de connexion pour « {$email} »", ['email' => $email]);
    }

    public function roleAttribue(RoleAttachedEvent $evenement): void
    {
        $this->changementDeDroits($evenement->model, $this->noms(Role::class, $evenement->rolesOrIds), 'role.attribution', 'Rôle attribué');
    }

    public function roleRetire(RoleDetachedEvent $evenement): void
    {
        $this->changementDeDroits($evenement->model, $this->noms(Role::class, $evenement->rolesOrIds), 'role.retrait', 'Rôle retiré');
    }

    public function permissionAttribuee(PermissionAttachedEvent $evenement): void
    {
        $this->changementDeDroits($evenement->model, $this->noms(Permission::class, $evenement->permissionsOrIds), 'role.permission_ajoutee', 'Permission ajoutée');
    }

    public function permissionRetiree(PermissionDetachedEvent $evenement): void
    {
        $this->changementDeDroits($evenement->model, $this->noms(Permission::class, $evenement->permissionsOrIds), 'role.permission_retiree', 'Permission retirée');
    }

    private function changementDeDroits(Model $modele, array $noms, string $action, string $libelle): void
    {
        // Seeders et commandes : pas d'auteur, pas de trace
        if (! auth()->check() || ! $noms) {
            return;
        }

        $cible = $modele instanceof User ? "{$modele->name} ({$modele->email})" : "rôle « {$modele->name} »";
        JournalActivite::consigner($action, $modele, "{$libelle} à {$cible} : " . implode(', ', $noms), ['droits' => $noms]);
    }

    /** Noms des rôles / permissions reçus sous forme d'identifiants, de modèles ou de collection. */
    private function noms(string $classe, mixed $elements): array
    {
        $elements = $elements instanceof Collection ? $elements->all() : (array) $elements;
        $ids = array_map(fn ($e) => $e instanceof Model ? $e->getKey() : $e, $elements);

        return $classe::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
}
