@extends('layouts.app')
@use('App\Support\Montant')

@section('title', $parrain->nom . ' | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $info = fn (string $label, $valeur) => '<div class="oev-info"><span class="oev-info-label">' . e($label) . '</span><span class="oev-info-valeur' . (($valeur === null || $valeur === '') ? ' is-vide' : '') . '">' . e(($valeur === null || $valeur === '') ? 'Non renseigné' : $valeur) . '</span></div>';
  $gere = auth()->user()->can('gérer parrains');
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Parrains</p>
        <h1 class="h3 mb-1">{{ $parrain->nom }}</h1>
        <p class="text-muted mb-0">
          {{ $parrain->libelleType() }} ·
          <span class="badge rounded-pill text-bg-{{ $parrain->actif ? 'success' : 'secondary' }}">{{ $parrain->actif ? 'Actif' : 'Inactif' }}</span>
        </p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.parrains.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Répertoire</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.appuis.index', ['parrain' => $parrain->id]) }}"><i class="bi bi-gift" aria-hidden="true"></i> Ses appuis</a>
      @if ($gere)
        <a class="btn btn-outline-primary btn-sm" href="{{ route('parrainage.parrains.edit', $parrain) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</a>
        @if ($parrain->estSupprimable())
          <form method="POST" action="{{ route('parrainage.parrains.destroy', $parrain) }}" data-confirm="Supprimer le parrain « {{ $parrain->nom }} » ?" data-confirm-danger>
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Supprimer</button>
          </form>
        @endif
      @endif
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12 col-xl-6">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-telephone" aria-hidden="true"></i> Contact et zone</h2>
        <div class="oev-infos">
          {!! $info('Personne de contact', $parrain->contact_nom) !!}
          {!! $info('Téléphone', $parrain->telephone) !!}
          {!! $info('E-mail', $parrain->email) !!}
          {!! $info('Adresse', $parrain->adresse) !!}
          {!! $info('Zone d’intervention', $parrain->libelleZones()) !!}
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-6">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-graph-up" aria-hidden="true"></i> Appuis</h2>
        <p class="mb-3"><span class="fs-3 fw-bold">{{ $oevAppuyes }}</span> OEV appuyé(s) @unless (auth()->user()->niveau() === \App\Models\User::NIVEAU_CENTRAL) <span class="text-muted small">dans votre zone</span> @endunless</p>
        @if ($parAnnee->isEmpty())
          <p class="text-muted mb-0">Aucun appui enregistré.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th scope="col">Année</th><th scope="col" class="text-end">OEV</th><th scope="col" class="text-end">Appuis</th><th scope="col" class="text-end">Montant</th></tr></thead>
              <tbody>
                @foreach ($parAnnee as $ligne)
                  <tr>
                    <td>{{ $ligne->annee }}</td>
                    <td class="text-end">{{ $ligne->oev }}</td>
                    <td class="text-end">{{ $ligne->appuis }}</td>
                    <td class="text-end text-nowrap">{{ Montant::fcfa($ligne->montant) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>
    </div>

    <div class="col-12">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Engagements</h2>
        @if ($parrain->engagements->isEmpty())
          <p class="text-muted">Aucun engagement enregistré.</p>
        @else
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Durée</th>
                  <th scope="col">Natures d’appui</th>
                  <th scope="col" class="text-end">OEV prévus</th>
                  <th scope="col" class="text-end">Montant prévu</th>
                  <th scope="col">Convention</th>
                  @if ($gere)<th scope="col" class="text-end">Action</th>@endif
                </tr>
              </thead>
              <tbody>
                @foreach ($parrain->engagements as $engagement)
                  <tr>
                    <td>{{ $engagement->date_engagement->format('d/m/Y') }}</td>
                    <td>{{ $engagement->duree_mois ? $engagement->duree_mois . ' mois' : '—' }}</td>
                    <td class="small">{{ $engagement->natures()->pluck('libelle')->implode(', ') ?: '—' }}</td>
                    <td class="text-end">{{ $engagement->nombre_oev_prevu ?? '—' }}</td>
                    <td class="text-end text-nowrap">{{ $engagement->montant_prevu !== null ? Montant::fcfa($engagement->montant_prevu) : '—' }}</td>
                    <td>
                      @if ($engagement->convention_chemin)
                        <a href="{{ route('parrainage.parrains.engagements.convention', [$parrain, $engagement]) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip" aria-hidden="true"></i> {{ $engagement->convention_nom }}</a>
                      @else
                        —
                      @endif
                    </td>
                    @if ($gere)
                      <td class="text-end">
                        <form method="POST" action="{{ route('parrainage.parrains.engagements.destroy', [$parrain, $engagement]) }}" data-confirm="Supprimer cet engagement ?" data-confirm-danger>
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Supprimer l’engagement"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                      </td>
                    @endif
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        @if ($gere)
          @php($erreurs = $errors->getBag('engagement'))
          <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formEngagement" aria-expanded="{{ $erreurs->any() ? 'true' : 'false' }}" aria-controls="formEngagement">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter un engagement
          </button>
          <form class="collapse mt-3 {{ $erreurs->any() ? 'show' : '' }}" id="formEngagement" method="POST" action="{{ route('parrainage.parrains.engagements.store', $parrain) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
              <div class="col-sm-4">
                <label class="form-label" for="date_engagement">Date <span class="text-danger">*</span></label>
                <input class="form-control @if ($erreurs->has('date_engagement')) is-invalid @endif" id="date_engagement" name="date_engagement" type="date" value="{{ old('date_engagement') }}" required>
                @if ($erreurs->has('date_engagement'))<div class="invalid-feedback">{{ $erreurs->first('date_engagement') }}</div>@endif
              </div>
              <div class="col-sm-4">
                <label class="form-label" for="duree_mois">Durée (mois)</label>
                <input class="form-control @if ($erreurs->has('duree_mois')) is-invalid @endif" id="duree_mois" name="duree_mois" type="number" min="1" max="600" value="{{ old('duree_mois') }}">
                @if ($erreurs->has('duree_mois'))<div class="invalid-feedback">{{ $erreurs->first('duree_mois') }}</div>@endif
              </div>
              <div class="col-sm-4">
                <label class="form-label" for="nombre_oev_prevu">Nombre d’OEV prévu</label>
                <input class="form-control @if ($erreurs->has('nombre_oev_prevu')) is-invalid @endif" id="nombre_oev_prevu" name="nombre_oev_prevu" type="number" min="1" value="{{ old('nombre_oev_prevu') }}">
                @if ($erreurs->has('nombre_oev_prevu'))<div class="invalid-feedback">{{ $erreurs->first('nombre_oev_prevu') }}</div>@endif
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="montant_prevu">Montant prévu (FCFA)</label>
                <input class="form-control @if ($erreurs->has('montant_prevu')) is-invalid @endif" id="montant_prevu" name="montant_prevu" type="text" inputmode="numeric" value="{{ old('montant_prevu') }}">
                @if ($erreurs->has('montant_prevu'))<div class="invalid-feedback">{{ $erreurs->first('montant_prevu') }}</div>@endif
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="convention">Convention <span class="text-muted small">(PDF, Word ou image, 5 Mo max)</span></label>
                <input class="form-control @if ($erreurs->has('convention')) is-invalid @endif" id="convention" name="convention" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                @if ($erreurs->has('convention'))<div class="invalid-feedback">{{ $erreurs->first('convention') }}</div>@endif
              </div>
              <div class="col-12">
                <span class="form-label d-block">Natures d’appui proposées</span>
                <div class="d-flex flex-wrap gap-3">
                  @foreach ($natures as $nature)
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="nature-{{ $nature->id }}" name="natures_appui[]" value="{{ $nature->id }}" @checked(in_array($nature->id, array_map('intval', old('natures_appui', [])), true))>
                      <label class="form-check-label" for="nature-{{ $nature->id }}">{{ $nature->libelle }}</label>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
            <button class="btn btn-primary btn-sm mt-3" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer l’engagement</button>
          </form>
        @endif
      </section>
    </div>

    <div class="col-12">
      <section class="oev-carte">
        <h2 class="oev-carte-titre"><i class="bi bi-clock-history" aria-hidden="true"></i> Historique</h2>
        @if ($journal->isEmpty())
          <p class="text-muted mb-0">Aucune action enregistrée.</p>
        @else
          <ul class="list-unstyled mb-0">
            @foreach ($journal as $entree)
              <li class="py-2 @unless ($loop->last) border-bottom @endunless">
                <span class="fw-semibold">{{ $entree->description }}</span>
                <span class="d-block text-muted small">{{ $entree->created_at->format('d/m/Y à H:i') }} · {{ $entree->utilisateur?->name ?? 'Système' }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </section>
    </div>
  </div>
</div>
@endsection
