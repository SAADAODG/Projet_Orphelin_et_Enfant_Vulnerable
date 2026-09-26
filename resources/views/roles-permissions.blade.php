@extends('layouts.app')

@section('title', 'Gestion des rôles et permissions | OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy"><span class="page-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><p class="eyebrow mb-1">Administration</p><h1 class="h3 mb-1">Gestion des rôles et permissions</h1><p class="text-muted mb-0">Définissez les accès accordés à chaque profil.</p></div></div>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#ajouterRoleModal" aria-label="Ajouter un rôle" title="Ajouter un rôle"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
  </div>

  @if (session('success')) <div class="alert alert-success mt-3">{{ session('success') }}</div> @endif
  @if (session('error')) <div class="alert alert-danger mt-3">{{ session('error') }}</div> @endif
  @if ($errors->any()) <div class="alert alert-danger mt-3">{{ $errors->first() }}</div> @endif

  <section class="panel mt-3">
    <div class="panel-header"><div><h2 class="h5 mb-1">Rôles existants</h2><p class="text-muted mb-0">{{ $roles->count() }} rôle(s) configuré(s).</p></div></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Rôle</th><th>Permissions</th><th class="text-end">Actions</th></tr></thead><tbody>
      @forelse ($roles as $role)
        <tr>
          <td><strong>{{ $role->name }}</strong></td>
          <td>
            <details class="permission-details">
              <summary class="text-primary">{{ $role->permissions->count() }} permission(s) <i class="fa-solid fa-chevron-down ms-1" aria-hidden="true"></i></summary>
              @if ($role->permissions->isNotEmpty())
                <ul class="list-group list-group-flush mt-2">
                  @foreach ($role->permissions as $permission)
                    <li class="list-group-item px-0 py-1"><i class="fa-solid fa-check text-success me-2" aria-hidden="true"></i>{{ $permission->name }}</li>
                  @endforeach
                </ul>
              @else
                <p class="text-muted small mt-2 mb-0">Aucune permission attribuée.</p>
              @endif
            </details>
          </td>
          <td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modifierRole{{ $role->id }}" aria-label="Modifier le rôle" title="Modifier"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>@if ($role->name !== 'superAdmin')<form method="POST" action="{{ route('roles-permissions.destroy', $role) }}" onsubmit="return confirm('Supprimer ce rôle ?');">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit" aria-label="Supprimer le rôle" title="Supprimer"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form>@endif</div></td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center text-muted py-4">Aucun rôle configuré.</td></tr>
      @endforelse
    </tbody></table></div>
  </section>
</div>

<div class="modal fade" id="ajouterRoleModal" tabindex="-1" aria-labelledby="ajouterRoleModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="ajouterRoleModalLabel">Ajouter un rôle</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><form method="POST" action="{{ route('roles-permissions.store') }}">@csrf<div class="modal-body"><label class="form-label" for="nouveau-role">Nom du rôle</label><input class="form-control mb-3" id="nouveau-role" name="name" required><fieldset><legend class="form-label">Permissions</legend><div class="row g-2">@foreach ($permissions as $permission)<div class="col-md-6"><div class="form-check"><input class="form-check-input" id="nouvelle-permission{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}" type="checkbox"><label class="form-check-label" for="nouvelle-permission{{ $permission->id }}">{{ $permission->name }}</label></div></div>@endforeach</div></fieldset></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal" aria-label="Annuler" title="Annuler"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><button class="btn btn-primary" type="submit" aria-label="Créer le rôle" title="Créer le rôle"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></button></div></form></div></div></div>

@foreach ($roles as $role)
<div class="modal fade" id="modifierRole{{ $role->id }}" tabindex="-1" aria-labelledby="modifierRoleLabel{{ $role->id }}" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="modifierRoleLabel{{ $role->id }}">Modifier le rôle</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><form method="POST" action="{{ route('roles-permissions.update', $role) }}">@csrf @method('PUT')<div class="modal-body"><label class="form-label" for="nom-role{{ $role->id }}">Nom du rôle</label><input class="form-control mb-3" id="nom-role{{ $role->id }}" name="name" value="{{ $role->name }}" required><fieldset><legend class="form-label">Permissions</legend><div class="row g-2">@foreach ($permissions as $permission)<div class="col-md-6"><div class="form-check"><input class="form-check-input" id="role{{ $role->id }}permission{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}" type="checkbox" @checked($role->permissions->contains($permission->id))><label class="form-check-label" for="role{{ $role->id }}permission{{ $permission->id }}">{{ $permission->name }}</label></div></div>@endforeach</div></fieldset></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal" aria-label="Annuler" title="Annuler"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button><button class="btn btn-primary" type="submit" aria-label="Enregistrer" title="Enregistrer"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></button></div></form></div></div></div>
@endforeach
@endsection
