@extends('layouts.app')

@use('App\Models\Oev')

@section('title', $oev->code . ' | OEV')

@php
  $totalPieces = count(Oev::DOCUMENTS);
  $pieces = $documents->count();
  $photo = $documents->get('photo');
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1"><a class="text-decoration-none" href="{{ route('oevs.index') }}">Liste des OEV</a> / {{ $oev->code }}</p>
        <h1 class="h3 mb-1">{{ $oev->nomComplet() }}</h1>
        <p class="text-muted mb-0">
          <span class="badge text-bg-{{ $pieces >= $totalPieces ? 'success' : ($pieces ? 'warning' : 'secondary') }}">Dossier : {{ $pieces }}/{{ $totalPieces }} pièce(s)</span>
          <span class="ms-2">Enregistré le {{ $oev->created_at->format('d/m/Y') }}{{ $oev->createur ? ' par ' . $oev->createur->name : '' }}</span>
        </p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('oevs.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Liste</a>
      @can('enregistrer OEV')
        <a class="btn btn-outline-primary btn-sm" href="{{ route('oevs.edit', $oev) }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Modifier / compléter</a>
        <form method="POST" action="{{ route('oevs.destroy', $oev) }}" onsubmit="return confirm('Supprimer cet OEV et son dossier ?');">
          @csrf @method('DELETE')
          <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i> Supprimer</button>
        </form>
      @endcan
    </div>
  </div>

  @if (session('success')) <div class="alert alert-success mt-3">{{ session('success') }}</div> @endif

  <div class="row g-3 mt-1">
    <div class="col-12 col-xl-6">
      <section class="panel h-100">
        <div class="panel-header"><h2 class="h5 mb-0">Informations sur l’enfant</h2></div>
        <div class="d-flex gap-3 align-items-start">
          @if ($photo)
            <img class="rounded border object-fit-cover flex-shrink-0" src="{{ route('oevs.documents.show', [$oev, $photo]) }}" alt="Photo de {{ $oev->nomComplet() }}" width="96" height="96">
          @else
            <span class="rounded border bg-light d-inline-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width:96px;height:96px"><i class="bi bi-person fs-1" aria-hidden="true"></i></span>
          @endif
          <dl class="row mb-0 flex-grow-1 small">
            <dt class="col-5">Code OEV</dt><dd class="col-7">{{ $oev->code }}</dd>
            <dt class="col-5">Nom</dt><dd class="col-7">{{ $oev->nom }}</dd>
            <dt class="col-5">Prénom(s)</dt><dd class="col-7">{{ $oev->prenom }}</dd>
            <dt class="col-5">Sexe</dt><dd class="col-7">{{ $oev->libelle('sexe', Oev::SEXES) }}</dd>
            <dt class="col-5">Date de naissance</dt><dd class="col-7">{{ $oev->date_naissance->format('d/m/Y') }} ({{ $oev->age() }} ans)</dd>
            <dt class="col-5">Statut</dt><dd class="col-7">{{ $oev->libelle('statut', Oev::STATUTS) }}</dd>
            <dt class="col-5">Handicap</dt><dd class="col-7">{{ $oev->handicap ? 'Oui — ' . $oev->nature_handicap : 'Non' }}</dd>
            <dt class="col-5">Système éducatif</dt><dd class="col-7">{{ $oev->libelle('systeme_educatif', Oev::SYSTEMES_EDUCATIFS) }}</dd>
          </dl>
        </div>
      </section>
    </div>

    <div class="col-12 col-xl-6">
      <section class="panel h-100">
        <div class="panel-header"><h2 class="h5 mb-0">Parent / tuteur et localité</h2></div>
        <dl class="row mb-0 small">
          <dt class="col-5">Nom du parent/tuteur</dt><dd class="col-7">{{ $oev->nom_tuteur }}</dd>
          <dt class="col-5">Prénom(s)</dt><dd class="col-7">{{ $oev->prenom_tuteur }}</dd>
          <dt class="col-5">Contact</dt><dd class="col-7">{{ $oev->contact_tuteur }}</dd>
          <dt class="col-5">Région</dt><dd class="col-7">{{ $oev->region }}</dd>
          <dt class="col-5">Province</dt><dd class="col-7">{{ $oev->province }}</dd>
          <dt class="col-5">Commune</dt><dd class="col-7">{{ $oev->commune }}</dd>
        </dl>
      </section>
    </div>

    <div class="col-12">
      <section class="panel">
        <div class="panel-header"><h2 class="h5 mb-0">Scolarité</h2></div>
        <div class="row g-3">
          <div class="col-12 col-md-5">
            <h3 class="h6 text-primary">Année précédente</h3>
            <dl class="row mb-0 small">
              <dt class="col-5">Établissement</dt><dd class="col-7">{{ $oev->etablissement_precedent ?: '—' }}</dd>
              <dt class="col-5">Moyenne annuelle</dt><dd class="col-7">{{ $oev->moyenne_annuelle !== null ? number_format((float) $oev->moyenne_annuelle, 2, ',', ' ') . ' / 20' : '—' }}</dd>
              <dt class="col-5">Appréciation</dt><dd class="col-7">{{ $oev->appreciation ? $oev->libelle('appreciation', Oev::APPRECIATIONS) : '—' }}</dd>
            </dl>
          </div>
          <div class="col-12 col-md-7">
            <h3 class="h6 text-primary">Année en cours</h3>
            <dl class="row mb-0 small">
              <dt class="col-5">Établissement</dt><dd class="col-7">{{ $oev->etablissement_actuel }} ({{ $oev->libelle('type_etablissement', Oev::TYPES_ETABLISSEMENT) }})</dd>
              <dt class="col-5">Classe</dt><dd class="col-7">{{ $oev->classe }}</dd>
              <dt class="col-5">Frais de scolarité</dt><dd class="col-7">{{ number_format($oev->frais_scolarite, 0, ',', ' ') }} FCFA</dd>
            </dl>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12">
      <section class="panel" id="dossier">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1">Dossier de l’OEV</h2>
            <p class="text-muted mb-0">{{ $pieces }}/{{ $totalPieces }} pièce(s) fournie(s){{ $pieces >= $totalPieces ? ' — dossier complet' : '' }}.</p>
          </div>
          @can('enregistrer OEV')
            @if ($pieces < $totalPieces)
              <a class="btn btn-primary btn-sm" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-folder-plus" aria-hidden="true"></i> Compléter le dossier</a>
            @endif
          @endcan
        </div>
        <div class="progress mb-3" role="progressbar" aria-label="Complétude du dossier" aria-valuenow="{{ $pieces }}" aria-valuemin="0" aria-valuemax="{{ $totalPieces }}" style="height:8px">
          <div class="progress-bar {{ $pieces >= $totalPieces ? 'bg-success' : 'bg-warning' }}" style="width: {{ round($pieces / $totalPieces * 100) }}%"></div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Pièce</th><th>Statut</th><th>Fichier</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              @foreach (Oev::DOCUMENTS as $type => $libelle)
                @php($document = $documents->get($type))
                <tr>
                  <td>
                    <strong>{{ $libelle }}</strong>
                    @if ($type === 'rib' && $oev->nom_structure_rib)<br><small class="text-muted">Structure : {{ $oev->nom_structure_rib }}</small>@endif
                  </td>
                  <td><span class="badge text-bg-{{ $document ? 'success' : 'secondary' }}">{{ $document ? 'Fournie' : 'Manquante' }}</span></td>
                  <td class="small">
                    @if ($document)
                      {{ $document->nom_original }}<br>
                      <span class="text-muted">{{ number_format($document->taille / 1024, 0, ',', ' ') }} Ko · {{ $document->created_at->format('d/m/Y') }}{{ $document->auteur ? ' · ' . $document->auteur->name : '' }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-end">
                    @if ($document)
                      <div class="btn-group btn-group-sm">
                        <a class="btn btn-light" href="{{ route('oevs.documents.show', [$oev, $document]) }}" target="_blank" rel="noopener" title="Ouvrir" aria-label="Ouvrir {{ $libelle }}"><i class="fa-solid fa-eye" aria-hidden="true"></i></a>
                        @can('enregistrer OEV')
                          <form method="POST" action="{{ route('oevs.documents.destroy', [$oev, $document]) }}" onsubmit="return confirm('Retirer cette pièce du dossier ?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm" type="submit" title="Retirer" aria-label="Retirer {{ $libelle }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                          </form>
                        @endcan
                      </div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
