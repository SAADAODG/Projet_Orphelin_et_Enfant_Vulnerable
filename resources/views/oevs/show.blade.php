@extends('layouts.app')

@use('App\Models\Oev')

@section('title', $oev->code . ' | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $totalPieces = count(Oev::DOCUMENTS);
  $pieces = $documents->count();
  $pourcentage = (int) round($pieces / $totalPieces * 100);
  $photo = $documents->get('photo');
  $iconesPieces = [
    'acte_naissance' => 'bi-file-earmark-text',
    'certificat_scolarite' => 'bi-journal-bookmark',
    'photo' => 'bi-camera',
    'cnib_tuteur' => 'bi-person-badge',
    'rib' => 'bi-bank',
  ];
  $info = function (string $label, $valeur) {
      $vide = $valeur === null || $valeur === '';
      return '<div class="oev-info"><span class="oev-info-label">' . e($label) . '</span><span class="oev-info-valeur' . ($vide ? ' is-vide' : '') . '">' . ($vide ? 'Non renseigné' : e($valeur)) . '</span></div>';
  };
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <nav aria-label="Fil d’Ariane" class="mb-3">
    <ol class="breadcrumb mb-0 small">
      <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route('oevs.index') }}"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Liste des OEV</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $oev->code }}</li>
    </ol>
  </nav>

  @if (session('success')) <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> {{ session('success') }}</div> @endif

  {{-- En-tête --}}
  <section class="oev-hero">
    <div class="oev-hero-banniere"></div>
    <div class="oev-hero-corps">
      @if ($photo)
        <img class="oev-avatar" src="{{ route('oevs.documents.show', [$oev, $photo]) }}" alt="Photo de {{ $oev->nomComplet() }}">
      @else
        <span class="oev-avatar oev-avatar--{{ $oev->sexe }}" aria-hidden="true">{{ $oev->initiales() }}</span>
      @endif

      <div class="oev-hero-identite">
        <h1>{{ $oev->nom }} <span>{{ $oev->prenom }}</span></h1>
        <div class="oev-puces">
          <span class="oev-code">{{ $oev->code }}</span>
          <span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span>
          <span class="oev-puce"><i class="bi {{ $oev->sexe === 'F' ? 'bi-gender-female' : 'bi-gender-male' }}" aria-hidden="true"></i>{{ $oev->libelle('sexe', Oev::SEXES) }}</span>
          <span class="oev-puce"><i class="bi bi-cake2" aria-hidden="true"></i>{{ $oev->age() }} ans</span>
          @if ($oev->handicap)
            <span class="oev-puce"><i class="bi bi-universal-access" aria-hidden="true"></i>{{ $oev->nature_handicap }}</span>
          @endif
        </div>
      </div>

      <div class="d-flex align-items-center gap-3">
        <div class="oev-anneau {{ $pieces >= $totalPieces ? 'is-complet' : '' }}" style="--p: {{ $pourcentage }}" role="img" aria-label="Dossier : {{ $pieces }} pièce(s) sur {{ $totalPieces }}">
          <span>{{ $pieces }}/{{ $totalPieces }}</span>
        </div>
        <div class="small">
          <div class="fw-bold">{{ $pieces >= $totalPieces ? 'Dossier complet' : ($pieces ? 'Dossier incomplet' : 'Aucune pièce') }}</div>
          <div class="text-muted">pièces fournies</div>
        </div>
      </div>

      @can('enregistrer OEV')
        <div class="oev-hero-actions w-100 justify-content-end">
          <a class="btn btn-primary" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Modifier / compléter</a>
          <form method="POST" action="{{ route('oevs.destroy', $oev) }}" onsubmit="return confirm('Supprimer cet OEV et son dossier ?');">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Supprimer</button>
          </form>
        </div>
      @endcan
    </div>
  </section>

  <div class="row g-3 mt-1">
    <div class="col-12 col-xl-8">
      <div class="d-flex flex-column gap-3 h-100">
        {{-- Enfant --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-person" aria-hidden="true"></i> Informations sur l’enfant</h2>
          <div class="oev-infos">
            {!! $info('Nom', $oev->nom) !!}
            {!! $info('Prénom(s)', $oev->prenom) !!}
            {!! $info('Sexe', $oev->libelle('sexe', Oev::SEXES)) !!}
            {!! $info('Date de naissance', $oev->date_naissance->format('d/m/Y') . ' (' . $oev->age() . ' ans)') !!}
            {!! $info('Statut', $oev->libelle('statut', Oev::STATUTS)) !!}
            {!! $info('Système éducatif', $oev->libelle('systeme_educatif', Oev::SYSTEMES_EDUCATIFS)) !!}
            {!! $info('Situation de handicap', $oev->handicap ? 'Oui' : 'Non') !!}
            @if ($oev->handicap)
              {!! $info('Nature du handicap', $oev->nature_handicap) !!}
            @endif
          </div>
        </section>

        {{-- Scolarité --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-mortarboard" aria-hidden="true"></i> Scolarité</h2>
          <div class="oev-annees">
            <div class="oev-annee">
              <h3>Année précédente</h3>
              <dl>
                <div><dt>Établissement fréquenté</dt><dd>{{ $oev->etablissement_precedent ?: '—' }}</dd></div>
                <div><dt>Moyenne annuelle</dt><dd>{{ $oev->moyenne_annuelle !== null ? number_format((float) $oev->moyenne_annuelle, 2, ',', ' ') . ' / 20' : '—' }}</dd></div>
                <div>
                  <dt>Appréciation</dt>
                  <dd>
                    @if ($oev->appreciation)
                      <span class="badge rounded-pill text-bg-{{ ['admis' => 'success', 'redouble' => 'warning', 'exclu' => 'danger'][$oev->appreciation] ?? 'secondary' }}">{{ $oev->libelle('appreciation', Oev::APPRECIATIONS) }}</span>
                    @else
                      —
                    @endif
                  </dd>
                </div>
              </dl>
            </div>
            <div class="oev-annee-fleche" aria-hidden="true"><i class="bi bi-arrow-right-circle"></i></div>
            <div class="oev-annee is-courante">
              <h3>Année en cours</h3>
              <dl>
                <div><dt>Établissement fréquenté</dt><dd>{{ $oev->etablissement_actuel }} <span class="badge rounded-pill text-bg-light border ms-1">{{ $oev->libelle('type_etablissement', Oev::TYPES_ETABLISSEMENT) }}</span></dd></div>
                <div><dt>Classe</dt><dd>{{ $oev->classe }}</dd></div>
                <div><dt>Frais de scolarité</dt><dd>{{ number_format($oev->frais_scolarite, 0, ',', ' ') }} FCFA</dd></div>
              </dl>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="d-flex flex-column gap-3 h-100">
        {{-- Tuteur --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-people" aria-hidden="true"></i> Parent / tuteur</h2>
          <div class="oev-contact">
            <span class="oev-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($oev->nom_tuteur, 0, 1) . mb_substr($oev->prenom_tuteur, 0, 1)) }}</span>
            <div class="min-w-0">
              <div class="fw-bold">{{ $oev->nom_tuteur }} {{ $oev->prenom_tuteur }}</div>
              <a class="oev-tel" href="tel:{{ str_replace(' ', '', $oev->contact_tuteur) }}"><i class="bi bi-telephone-fill text-success" aria-hidden="true"></i>{{ $oev->contact_tuteur }}</a>
            </div>
          </div>
        </section>

        {{-- Localité --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-geo-alt" aria-hidden="true"></i> Localité</h2>
          <ul class="oev-lieu">
            @foreach (['Région' => $oev->region?->nom, 'Province' => $oev->province?->nom, 'Commune' => $oev->commune?->nom] as $niveau => $lieu)
              <li>
                <span class="oev-lieu-point" aria-hidden="true"></span>
                <div><small class="text-muted d-block">{{ $niveau }}</small><strong>{{ $lieu ?? '—' }}</strong></div>
              </li>
            @endforeach
          </ul>
        </section>

        {{-- Traçabilité --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-clock-history" aria-hidden="true"></i> Suivi de l’enregistrement</h2>
          <div class="small d-grid gap-2">
            <div class="d-flex justify-content-between gap-2"><span class="text-muted">Enregistré le</span><strong>{{ $oev->created_at->format('d/m/Y à H:i') }}</strong></div>
            <div class="d-flex justify-content-between gap-2"><span class="text-muted">Par</span><strong>{{ $oev->createur?->name ?? '—' }}</strong></div>
            @if ($oev->updated_at && $oev->updated_at->ne($oev->created_at))
              <div class="d-flex justify-content-between gap-2"><span class="text-muted">Dernière modification</span><strong>{{ $oev->updated_at->format('d/m/Y à H:i') }}</strong></div>
            @endif
          </div>
        </section>
      </div>
    </div>

    {{-- Dossier --}}
    <div class="col-12">
      <section class="oev-carte" id="dossier">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h2 class="oev-carte-titre mb-1"><i class="bi bi-folder2-open" aria-hidden="true"></i> Dossier de l’OEV</h2>
            <p class="text-muted small mb-0">{{ $pieces }} pièce(s) sur {{ $totalPieces }}{{ $pieces >= $totalPieces ? ' — dossier complet' : '' }}.</p>
          </div>
          @can('enregistrer OEV')
            @if ($pieces < $totalPieces)
              <a class="btn btn-primary btn-sm" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-folder-plus" aria-hidden="true"></i> Compléter le dossier</a>
            @endif
          @endcan
        </div>

        <div class="oev-docs">
          @foreach (Oev::DOCUMENTS as $type => $libelle)
            @php($document = $documents->get($type))
            <article class="oev-doc {{ $document ? 'is-fourni' : 'is-manquant' }}">
              <div class="oev-doc-entete">
                <span class="oev-doc-icone"><i class="bi {{ $iconesPieces[$type] }}" aria-hidden="true"></i></span>
                <span class="badge rounded-pill text-bg-{{ $document ? 'success' : 'secondary' }}">
                  <i class="bi {{ $document ? 'bi-check-lg' : 'bi-dash' }}" aria-hidden="true"></i> {{ $document ? 'Fournie' : 'Manquante' }}
                </span>
              </div>
              <div class="oev-doc-nom">{{ $libelle }}</div>
              @if ($type === 'rib' && $oev->nom_structure_rib)
                <div class="small"><i class="bi bi-building me-1 text-muted" aria-hidden="true"></i>{{ $oev->nom_structure_rib }}</div>
              @endif
              @if ($document)
                <div class="oev-doc-fichier">{{ $document->nom_original }} · {{ number_format($document->taille / 1024, 0, ',', ' ') }} Ko<br>Ajouté le {{ $document->created_at->format('d/m/Y') }}</div>
                <div class="oev-doc-actions">
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('oevs.documents.show', [$oev, $document]) }}" target="_blank" rel="noopener"><i class="bi bi-eye" aria-hidden="true"></i> Ouvrir</a>
                  @can('enregistrer OEV')
                    <form class="flex-fill d-flex" method="POST" action="{{ route('oevs.documents.destroy', [$oev, $document]) }}" onsubmit="return confirm('Retirer cette pièce du dossier ?');">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger w-100" type="submit" aria-label="Retirer {{ $libelle }}"><i class="bi bi-trash" aria-hidden="true"></i> Retirer</button>
                    </form>
                  @endcan
                </div>
              @else
                <div class="oev-doc-fichier">Pièce non encore fournie.</div>
                @can('enregistrer OEV')
                  <div class="oev-doc-actions">
                    <a class="btn btn-sm btn-light border" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</a>
                  </div>
                @endcan
              @endif
            </article>
          @endforeach
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
