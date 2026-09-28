@extends('layouts.app')

@use('App\Models\Oev')

@section('title', $oev->reference() . ' | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $totalPieces = count(Oev::DOCUMENTS);
  $pieces = $documents->count();
  $pourcentage = (int) round($pieces / $totalPieces * 100);
  $photo = $documents->get('photo');
  $utilisateur = auth()->user();
  $etat = $oev->statut_dossier;
  $peutModifier = $oev->peutEtreModifiePar($utilisateur);          // DP en constitution, ou DR sur demande de complément
  $estDp = $oev->estModifiable() && $utilisateur->can('constituer dossiers');
  $estDrADecider = $oev->attendDecisionDr() && $utilisateur->can('valider dossiers');
  $estCentral = $utilisateur->can('intégrer OEV');

  // Page de retour selon le rôle et l'étape du dossier
  $retour = match (true) {
      $oev->estIntegre()
          => ['route' => 'oevs.liste', 'label' => 'Liste des OEV', 'icone' => 'bi-person-lines-fill'],
      $estCentral && in_array($etat, [Oev::ETAT_VALIDE, Oev::ETAT_COMPLEMENT], true)
          => ['route' => 'oevs.integration', 'label' => 'Intégration des OEV', 'icone' => 'bi-person-check'],
      $utilisateur->can('valider dossiers') && in_array($etat, [Oev::ETAT_SOUMIS, Oev::ETAT_COMPLEMENT, Oev::ETAT_VALIDE, Oev::ETAT_NON_CONFORME], true)
          => ['route' => 'oevs.validation', 'label' => 'Validation des dossiers', 'icone' => 'bi-patch-check'],
      $utilisateur->can('constituer dossiers')
          => ['route' => 'oevs.index', 'label' => 'Constituer dossier enfant', 'icone' => 'bi-file-earmark-medical'],
      $utilisateur->can('valider dossiers')
          => ['route' => 'oevs.validation', 'label' => 'Validation des dossiers', 'icone' => 'bi-patch-check'],
      $utilisateur->can('intégrer OEV')
          => ['route' => 'oevs.integration', 'label' => 'Intégration des OEV', 'icone' => 'bi-person-check'],
      default => ['route' => 'dashboard', 'label' => 'Tableau de bord', 'icone' => 'bi-speedometer2'],
  };

  // Parcours : DP (constitution, soumission) → DR (vérification) → central (intégration)
  $verifie = in_array($oev->statut_dossier, [Oev::ETAT_VALIDE, Oev::ETAT_INTEGRE], true);
  $parcours = [
      [
          'niveau' => 'DP', 'titre' => 'Dossier constitué', 'icone' => 'bi-folder-plus', 'etat' => 'faite',
          'detail' => $oev->created_at?->format('d/m/Y') . ($oev->createur ? ' · ' . $oev->createur->name : ''),
      ],
      [
          'niveau' => 'DP', 'titre' => 'Soumis au DR', 'icone' => 'bi-send',
          'etat' => $oev->soumis_at && $etat !== Oev::ETAT_BROUILLON ? 'faite' : ($estDp ? 'courante' : 'a-venir'),
          'detail' => $oev->soumis_at ? $oev->soumis_at->format('d/m/Y') . ($oev->soumetteur ? ' · ' . $oev->soumetteur->name : '') : 'En attente de soumission',
      ],
      $etat === Oev::ETAT_COMPLEMENT
          ? [
              'niveau' => 'DR', 'icone' => 'bi-arrow-repeat', 'etat' => 'courante',
              'titre' => 'Complément demandé par le central',
              'detail' => $oev->complement_at?->format('d/m/Y') . ($oev->demandeurComplement ? ' · ' . $oev->demandeurComplement->name : '') . ' — en attente du DR',
          ]
          : [
              'niveau' => 'DR', 'icone' => $etat === Oev::ETAT_NON_CONFORME ? 'bi-x-lg' : 'bi-patch-check',
              'titre' => $etat === Oev::ETAT_NON_CONFORME ? 'Jugé non conforme' : ($verifie ? 'Conforme — validé' : 'Vérification de la conformité'),
              'etat' => $etat === Oev::ETAT_NON_CONFORME ? 'refusee' : ($verifie ? 'faite' : ($etat === Oev::ETAT_SOUMIS ? 'courante' : 'a-venir')),
              'detail' => $oev->verifie_at && $etat !== Oev::ETAT_SOUMIS
                  ? $oev->verifie_at->format('d/m/Y') . ($oev->verificateur ? ' · ' . $oev->verificateur->name : '')
                  : ($etat === Oev::ETAT_SOUMIS ? 'En cours de vérification' : 'Après soumission'),
          ],
      [
          'niveau' => 'Central', 'titre' => $oev->estIntegre() ? 'Intégré — ' . $oev->code : 'Intégration comme OEV', 'icone' => 'bi-person-check',
          'etat' => $oev->estIntegre() ? 'faite' : ($etat === Oev::ETAT_VALIDE ? 'courante' : 'a-venir'),
          'detail' => $oev->integre_at
              ? $oev->integre_at->format('d/m/Y') . ($oev->integrateur ? ' · ' . $oev->integrateur->name : '')
              : ($etat === Oev::ETAT_COMPLEMENT ? 'En attente du complément' : 'Après validation du DR'),
      ],
  ];
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
      <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route($retour['route']) }}"><i class="bi {{ $retour['icone'] }} me-1" aria-hidden="true"></i>{{ $retour['label'] }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $oev->reference() }}</li>
    </ol>
  </nav>

  @if (session('success')) <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> {{ session('success') }}</div> @endif
  @if ($errors->any()) <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i> {{ $errors->first() }}</div> @endif

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
          <span class="oev-code {{ $oev->code ? '' : 'oev-code--dossier' }}" title="{{ $oev->code ? 'Code OEV' : 'Numéro de dossier' }}">{{ $oev->reference() }}</span>
          <span class="badge rounded-pill text-bg-{{ $oev->couleurEtat() }}">{{ $oev->libelleEtat() }}</span>
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

      @if ($peutModifier)
        <div class="oev-hero-actions w-100 justify-content-end">
          <a class="btn btn-outline-primary" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Modifier / compléter</a>
          @if ($estDp)
            <form method="POST" action="{{ route('oevs.destroy', $oev) }}" onsubmit="return confirm('Supprimer ce dossier ?');">
              @csrf @method('DELETE')
              <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Supprimer</button>
            </form>
          @endif
        </div>
      @endif
    </div>
  </section>

  {{-- Action attendue selon l'étape du circuit et le rôle de l'utilisateur --}}
  @if ($estDp)
    @if ($etat === Oev::ETAT_NON_CONFORME)
      <section class="oev-action oev-action--danger mt-3">
        <div>
          <div class="fw-bold"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i> Dossier jugé non conforme par le DR{{ $oev->verificateur ? ' (' . $oev->verificateur->name . ')' : '' }}</div>
          <div class="small mt-1"><strong>Motif :</strong> {{ $oev->motif_non_conformite }}</div>
          <div class="small text-muted mt-1">Corrigez le dossier puis soumettez-le à nouveau.</div>
        </div>
      </section>
    @endif
    <section class="oev-action mt-3">
      <div>
        <div class="fw-bold"><i class="bi bi-send me-1" aria-hidden="true"></i> Étape suivante : soumettre le dossier au DR</div>
        <div class="small text-muted">
          @if ($oev->estComplet())
            Le dossier est complet. Une fois soumis, il ne pourra plus être modifié sauf s’il est jugé non conforme.
          @else
            Il manque {{ $totalPieces - $pieces }} pièce(s) : le dossier doit être complet pour être soumis.
          @endif
        </div>
      </div>
      <form method="POST" action="{{ route('oevs.soumettre', $oev) }}" onsubmit="return confirm('Soumettre ce dossier au DR ?');">
        @csrf
        <button class="btn btn-primary" type="submit" @disabled(! $oev->estComplet())><i class="bi bi-send" aria-hidden="true"></i> Soumettre au DR</button>
      </form>
    </section>
  @elseif ($estDrADecider)
    @if ($etat === Oev::ETAT_COMPLEMENT)
      <section class="oev-action oev-action--warning mt-3">
        <div>
          <div class="fw-bold"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Complément demandé par le niveau central{{ $oev->demandeurComplement ? ' (' . $oev->demandeurComplement->name . ')' : '' }}{{ $oev->complement_at ? ' le ' . $oev->complement_at->format('d/m/Y') : '' }}</div>
          <div class="small mt-1"><strong>Demande :</strong> {{ $oev->motif_complement }}</div>
          <div class="small text-muted mt-1">Complétez le dossier vous-même puis validez-le, ou renvoyez-le au DP si vous ne pouvez pas apporter le complément.</div>
        </div>
      </section>
    @endif
    <section class="oev-action mt-3">
      <div>
        @if ($etat === Oev::ETAT_COMPLEMENT)
          <div class="fw-bold"><i class="bi bi-patch-check me-1" aria-hidden="true"></i> Traitement du complément (DR)</div>
          <div class="small text-muted">Une fois le dossier complété, validez-le pour le renvoyer au niveau central.</div>
        @else
          <div class="fw-bold"><i class="bi bi-patch-check me-1" aria-hidden="true"></i> Vérification de la conformité (DR)</div>
          <div class="small text-muted">Contrôlez les informations et les pièces ci-dessous, puis rendez votre décision.</div>
        @endif
      </div>
      <div class="d-flex flex-wrap gap-2">
        @if ($etat === Oev::ETAT_COMPLEMENT)
          <a class="btn btn-outline-primary" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Compléter le dossier</a>
        @endif
        <form method="POST" action="{{ route('oevs.conforme', $oev) }}" onsubmit="return confirm('{{ $etat === Oev::ETAT_COMPLEMENT ? 'Valider ce dossier et le renvoyer au niveau central ?' : 'Déclarer ce dossier conforme et le valider ?' }}');">
          @csrf
          <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> {{ $etat === Oev::ETAT_COMPLEMENT ? 'Valider et renvoyer au central' : 'Conforme — valider' }}</button>
        </form>
        <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#formNonConforme" aria-expanded="{{ $errors->has('motif_non_conformite') ? 'true' : 'false' }}" aria-controls="formNonConforme">
          <i class="bi {{ $etat === Oev::ETAT_COMPLEMENT ? 'bi-arrow-return-left' : 'bi-x-circle' }}" aria-hidden="true"></i> {{ $etat === Oev::ETAT_COMPLEMENT ? 'Renvoyer au DP' : 'Non conforme' }}
        </button>
      </div>
      <form class="collapse w-100 {{ $errors->has('motif_non_conformite') ? 'show' : '' }}" id="formNonConforme" method="POST" action="{{ route('oevs.non-conforme', $oev) }}">
        @csrf
        <label class="form-label fw-semibold" for="motif_non_conformite">{{ $etat === Oev::ETAT_COMPLEMENT ? 'Ce que le DP doit compléter' : 'Motif de non-conformité' }} (transmis au DP) <span class="text-danger">*</span></label>
        <textarea class="form-control @error('motif_non_conformite') is-invalid @enderror" id="motif_non_conformite" name="motif_non_conformite" rows="3" maxlength="2000" required placeholder="ex : acte de naissance illisible, certificat de scolarité de l’année précédente…">{{ old('motif_non_conformite', $etat === Oev::ETAT_COMPLEMENT ? 'Complément demandé par le niveau central : ' . $oev->motif_complement : '') }}</textarea>
        <button class="btn btn-danger mt-2" type="submit"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Renvoyer au DP pour correction</button>
      </form>
    </section>
  @elseif ($etat === Oev::ETAT_VALIDE && $estCentral)
    <section class="oev-action oev-action--success mt-3">
      <div>
        <div class="fw-bold"><i class="bi bi-person-check me-1" aria-hidden="true"></i> Dossier validé par le DR : prêt pour l’intégration</div>
        <div class="small text-muted">En l’intégrant, l’enfant devient OEV et reçoit son code OEV. S’il manque une information ou une pièce, demandez un complément au DR.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('oevs.integrer', $oev) }}" onsubmit="return confirm('Intégrer cet enfant comme OEV ?');">
          @csrf
          <button class="btn btn-success" type="submit"><i class="bi bi-person-check" aria-hidden="true"></i> Intégrer comme OEV</button>
        </form>
        <button class="btn btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#formComplement" aria-expanded="{{ $errors->has('motif_complement') ? 'true' : 'false' }}" aria-controls="formComplement">
          <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Demander un complément
        </button>
      </div>
      <form class="collapse w-100 {{ $errors->has('motif_complement') ? 'show' : '' }}" id="formComplement" method="POST" action="{{ route('oevs.complement', $oev) }}">
        @csrf
        <label class="form-label fw-semibold" for="motif_complement">Complément attendu (transmis au DR) <span class="text-danger">*</span></label>
        <textarea class="form-control @error('motif_complement') is-invalid @enderror" id="motif_complement" name="motif_complement" rows="3" maxlength="2000" required placeholder="ex : fournir le RIB à jour de la structure, préciser la classe de l’année en cours…">{{ old('motif_complement') }}</textarea>
        <button class="btn btn-warning mt-2" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Envoyer la demande au DR</button>
      </form>
    </section>
  @elseif ($etat === Oev::ETAT_COMPLEMENT)
    {{-- Suivi (central, DP…) : le dossier est chez le DR --}}
    <section class="oev-action oev-action--warning mt-3">
      <div>
        <div class="fw-bold"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i> Complément demandé{{ $oev->complement_at ? ' le ' . $oev->complement_at->format('d/m/Y') : '' }} — en attente du DR</div>
        <div class="small mt-1"><strong>Demande :</strong> {{ $oev->motif_complement }}</div>
      </div>
    </section>
  @endif

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
            @foreach (['Région' => $oev->region, 'Province' => $oev->province, 'Commune' => $oev->commune] as $niveau => $lieu)
              <li>
                <span class="oev-lieu-point" aria-hidden="true"></span>
                <div><small class="text-muted d-block">{{ $niveau }}</small><strong>{{ $lieu }}</strong></div>
              </li>
            @endforeach
          </ul>
        </section>

        {{-- Parcours du dossier --}}
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-signpost-split" aria-hidden="true"></i> Parcours du dossier</h2>
          <ol class="oev-parcours">
            @foreach ($parcours as $etape)
              <li class="oev-etape is-{{ $etape['etat'] }}">
                <span class="oev-etape-point"><i class="bi {{ $etape['etat'] === 'faite' ? 'bi-check-lg' : $etape['icone'] }}" aria-hidden="true"></i></span>
                <div>
                  <div class="oev-etape-niveau">{{ $etape['niveau'] }}</div>
                  <div class="oev-etape-titre">{{ $etape['titre'] }}</div>
                  <div class="oev-etape-detail">{{ $etape['detail'] }}</div>
                </div>
              </li>
            @endforeach
          </ol>
        </section>
      </div>
    </div>

    {{-- Dossier --}}
    <div class="col-12">
      <section class="oev-carte" id="dossier">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h2 class="oev-carte-titre mb-1"><i class="bi bi-folder2-open" aria-hidden="true"></i> Pièces du dossier</h2>
            <p class="text-muted small mb-0">{{ $pieces }} pièce(s) sur {{ $totalPieces }}{{ $pieces >= $totalPieces ? ' — dossier complet' : '' }}.</p>
          </div>
          @if ($peutModifier && $pieces < $totalPieces)
            <a class="btn btn-primary btn-sm" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-folder-plus" aria-hidden="true"></i> Compléter le dossier</a>
          @endif
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
                  @if ($peutModifier)
                    <form class="flex-fill d-flex" method="POST" action="{{ route('oevs.documents.destroy', [$oev, $document]) }}" onsubmit="return confirm('Retirer cette pièce du dossier ?');">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger w-100" type="submit" aria-label="Retirer {{ $libelle }}"><i class="bi bi-trash" aria-hidden="true"></i> Retirer</button>
                    </form>
                  @endif
                </div>
              @else
                <div class="oev-doc-fichier">Pièce non encore fournie.</div>
                @if ($peutModifier)
                  <div class="oev-doc-actions">
                    <a class="btn btn-sm btn-light border" href="{{ route('oevs.edit', $oev) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</a>
                  </div>
                @endif
              @endif
            </article>
          @endforeach
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
