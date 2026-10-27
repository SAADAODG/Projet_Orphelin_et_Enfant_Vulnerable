@extends('layouts.app')
@use('App\Models\NatureAppui')

@section('title', 'Natures d’appui | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-gift" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Paramètres · parrainage</p>
        <h1 class="h3 mb-1">Natures d’appui</h1>
        <p class="text-muted mb-0">Natures proposées pour les appuis des partenaires, les engagements des parrains et les extractions.</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-0">
    <div class="col-12">
      <div class="panel">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-list-ul" aria-hidden="true"></i><span>Natures proposées</span></h2>
            <p class="text-muted mb-0">
              Une nature désactivée n’est plus proposée à la saisie, mais reste affichée dans les appuis qui l’ont déjà.
              <i class="bi bi-lock" aria-hidden="true"></i> : « Scolaire » et « Formation professionnelle » sont les types d’appui des sessions
              (libellé fixe, ni suppression ni désactivation).
            </p>
          </div>
        </div>

        <form class="d-flex flex-wrap gap-2 mb-3" method="POST" action="{{ route('parametres.natures-appui.store') }}">
          @csrf
          <div style="flex: 1 1 18rem; max-width: 32rem">
            <input class="form-control @error('libelle') is-invalid @enderror" type="text" name="libelle" maxlength="100" value="{{ old('libelle') }}" placeholder="Nouvelle nature d’appui (libellé)" aria-label="Libellé de la nouvelle nature" required>
            @error('libelle')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</button>
        </form>

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col" style="width: 6rem">Ordre</th>
                <th scope="col">Libellé</th>
                <th scope="col">Code</th>
                <th scope="col">Actif</th>
                <th scope="col" class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($natures as $nature)
                @php($formulaire = 'nature-' . $nature->id)
                <tr @class(['text-muted' => ! $nature->actif])>
                  <td><input class="form-control form-control-sm" type="number" name="ordre" min="0" max="999" value="{{ $nature->ordre }}" form="{{ $formulaire }}" aria-label="Ordre" required></td>
                  <td><input class="form-control form-control-sm" type="text" name="libelle" maxlength="100" value="{{ $nature->libelle }}" form="{{ $formulaire }}" aria-label="Libellé" required @readonly(in_array($nature->code, NatureAppui::LIBELLE_FIXE, true))></td>
                  <td><code>{{ $nature->code }}</code> @if ($nature->estSysteme())<i class="bi bi-lock ms-1" title="Utilisée par les règles de l’application" aria-label="Nature système"></i>@endif</td>
                  <td>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" name="actif" value="1" form="{{ $formulaire }}" aria-label="Active" @checked($nature->actif) @disabled($nature->estSysteme())>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <form id="{{ $formulaire }}" method="POST" action="{{ route('parametres.natures-appui.update', $nature) }}">
                        @csrf
                        @method('PUT')
                        <button class="btn btn-light btn-sm" type="submit" aria-label="Enregistrer"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                      </form>
                      @unless ($nature->estSysteme())
                        <form method="POST" action="{{ route('parametres.natures-appui.destroy', $nature) }}" data-confirm="Supprimer la nature « {{ $nature->libelle }} » ?" data-confirm-danger>
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                      @endunless
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
