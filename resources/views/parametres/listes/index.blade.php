@extends('layouts.app')

@section('title', $config['titre'] . ' | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi {{ $config['icone'] }}" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Paramètres · fiche OEV</p>
        <h1 class="h3 mb-1">{{ $config['titre'] }}</h1>
        <p class="text-muted mb-0">{{ $config['aide'] }}</p>
      </div>
    </div>
  </div>

  <section class="row g-3 mt-0">
    <div class="col-12">
      <div class="panel">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-list-ul" aria-hidden="true"></i><span>Choix proposés</span></h2>
            <p class="text-muted mb-0">
              Un choix désactivé n’est plus proposé à la saisie, mais reste affiché dans les dossiers qui l’ont déjà.
              <i class="bi bi-lock" aria-hidden="true"></i> : choix utilisé par les règles de l’application (libellé modifiable, ni suppression ni désactivation).
            </p>
          </div>
        </div>

        <form class="d-flex flex-wrap gap-2 mb-3" method="POST" action="{{ route('parametres.listes.store', $liste) }}">
          @csrf
          <div style="flex: 1 1 18rem; max-width: 32rem">
            <input class="form-control @error('libelle') is-invalid @enderror" type="text" name="libelle" maxlength="255" value="{{ old('libelle') }}" placeholder="Nouveau choix (libellé)" aria-label="Libellé du nouveau choix" required>
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
              @foreach ($elements as $element)
                @php($formulaire = 'element-' . $element->id)
                <tr @class(['text-muted' => ! $element->actif])>
                  <td><input class="form-control form-control-sm" type="number" name="ordre" min="0" max="999" value="{{ $element->ordre }}" form="{{ $formulaire }}" aria-label="Ordre" required></td>
                  <td><input class="form-control form-control-sm" type="text" name="libelle" maxlength="255" value="{{ $element->libelle }}" form="{{ $formulaire }}" aria-label="Libellé" required></td>
                  <td><code>{{ $element->code }}</code> @if ($element->systeme)<i class="bi bi-lock ms-1" title="Utilisé par les règles de l’application" aria-label="Choix système"></i>@endif</td>
                  <td>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" name="actif" value="1" form="{{ $formulaire }}" aria-label="Actif" @checked($element->actif) @disabled($element->systeme)>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <form id="{{ $formulaire }}" method="POST" action="{{ route('parametres.listes.update', [$liste, $element]) }}">
                        @csrf
                        @method('PUT')
                        <button class="btn btn-light btn-sm" type="submit" aria-label="Enregistrer"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                      </form>
                      @unless ($element->systeme)
                        <form method="POST" action="{{ route('parametres.listes.destroy', [$liste, $element]) }}" data-confirm="Supprimer « {{ $element->libelle }} » de la liste ?" data-confirm-danger>
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
