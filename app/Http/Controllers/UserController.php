<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with(['roles', 'region', 'province'])->latest()->paginate(15);
        $roles = Role::with('permissions')->orderBy('name')->get();

        return view('users', [
            'users' => $users,
            'roles' => $roles,
            // Rattachement demandé selon le rôle : région pour un DR, province pour un DP
            'niveauxRoles' => $roles->mapWithKeys(fn (Role $role) => [$role->name => User::niveauDuRole($role->name)]),
            'regions' => Region::with(['provinces' => fn ($q) => $q->orderBy('nom')])->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'exists:roles,name'],
            ...$this->reglesLocalite($request->input('role')),
        ], $this->messagesLocalite());

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'active' => true,
            ...$this->localite($validated, $validated['role']),
        ]);
        $user->assignRole($validated['role']);

        return redirect()->route('users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function update(Request $request, User $user)
    {
        // Sans nouveau rôle choisi, le rattachement dépend du rôle actuel
        $role = $request->input('role') ?: $user->roles->first()?->name;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'role' => ['nullable', 'exists:roles,name'],
            'active' => ['required', 'boolean'],
            ...$this->reglesLocalite($role),
        ], $this->messagesLocalite());

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'active' => $validated['active'],
            ...$this->localite($validated, $role),
        ]);

        if (! empty($validated['role'])) {
            $user->syncRoles($validated['role']);
        }

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur supprimé avec succès.');
    }

    /** Un DR doit être rattaché à une région, un DP à une province. */
    private function reglesLocalite(?string $role): array
    {
        $niveau = User::niveauDuRole($role);

        return [
            'region_id' => [Rule::requiredIf($niveau === User::NIVEAU_REGION), 'nullable', 'integer', Rule::exists('regions', 'id')],
            'province_id' => [Rule::requiredIf($niveau === User::NIVEAU_PROVINCE), 'nullable', 'integer', Rule::exists('provinces', 'id')],
        ];
    }

    private function messagesLocalite(): array
    {
        return [
            'region_id.required' => 'Choisissez la région de ce directeur régional.',
            'province_id.required' => 'Choisissez la province de ce directeur provincial.',
        ];
    }

    /** Colonnes region_id / province_id à enregistrer : la région d'un DP est celle de sa province ; le niveau central n'a pas de rattachement. */
    private function localite(array $validated, ?string $role): array
    {
        return match (User::niveauDuRole($role)) {
            User::NIVEAU_REGION => ['region_id' => $validated['region_id'], 'province_id' => null],
            User::NIVEAU_PROVINCE => [
                'region_id' => Province::whereKey($validated['province_id'])->value('region_id'),
                'province_id' => $validated['province_id'],
            ],
            default => ['region_id' => null, 'province_id' => null],
        };
    }
}
