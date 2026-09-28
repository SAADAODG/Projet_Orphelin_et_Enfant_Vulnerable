@extends('layouts.app')

@use('App\Models\Oev')

@php
  $edition = $oev->exists;
  // Le DP peut enregistrer et soumettre au DR en une fois (création, ou dossier en constitution / non conforme)
  $peutSoumettre = auth()->user()->can('constituer dossiers') && (! $edition || $oev->estModifiable());
  $v = fn (string $champ) => old($champ, $oev->{$champ});
  $handicap = (string) old('handicap', $oev->handicap ? '1' : ($edition ? '0' : ''));

  $etapes = [
    1 => ['titre' => 'Enfant, tuteur & localité', 'icone' => 'bi-person-vcard'],
    2 => ['titre' => 'Scolarité', 'icone' => 'bi-mortarboard'],
    3 => ['titre' => 'Fichiers à charger', 'icone' => 'bi-folder2-open'],
    4 => ['titre' => 'Récapitulatif', 'icone' => 'bi-clipboard-check'],
  ];

  // Étape à ouvrir en cas d'erreur de validation côté serveur
  $champsParEtape = [
    1 => ['nom', 'prenom', 'sexe', 'date_naissance', 'statut', 'handicap', 'nature_handicap', 'systeme_educatif', 'nom_tuteur', 'prenom_tuteur', 'contact_tuteur', 'region', 'province', 'commune'],
    2 => ['etablissement_precedent', 'moyenne_annuelle', 'appreciation', 'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite'],
    3 => array_merge(array_keys(Oev::DOCUMENTS), ['nom_structure_rib']),
  ];
  $etapeInitiale = 1;
  foreach ($champsParEtape as $numero => $champs) {
    if ($errors->hasAny($champs)) { $etapeInitiale = $numero; break; }
  }

  $iconesPieces = [
    'acte_naissance' => 'bi-file-earmark-text',
    'certificat_scolarite' => 'bi-journal-bookmark',
    'photo' => 'bi-camera',
    'cnib_tuteur' => 'bi-person-badge',
    'rib' => 'bi-bank',
  ];

  // Limites de taille réelles (application ∩ configuration PHP du serveur)
  $maxFichiersKo = collect(Oev::DOCUMENTS)->keys()->mapWithKeys(fn ($t) => [$t => Oev::tailleMaxFichierKo($t)])->all();
  $maxEnvoiKo = Oev::tailleMaxEnvoiKo();
  $enMo = fn (int $ko) => str_replace('.', ',', (string) round($ko / 1024, 1)) . ' Mo';
@endphp

@section('title', ($edition ? 'Modifier ' . $oev->reference() : 'Constituer dossier enfant') . ' | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .oev-stepper { display: flex; list-style: none; padding: 0; margin: 0; gap: .5rem; }
  .oev-stepper li { flex: 1; position: relative; }
  .oev-stepper li:not(:last-child)::after {
    content: ""; position: absolute; top: 1.25rem; left: calc(50% + 1.6rem); right: calc(-50% + 1.6rem);
    height: 3px; border-radius: 3px; background: var(--admin-border); transition: background .3s;
  }
  .oev-stepper li.is-done:not(:last-child)::after { background: var(--admin-primary); }
  .oev-step-btn {
    display: flex; flex-direction: column; align-items: center; gap: .4rem; width: 100%;
    background: none; border: 0; padding: 0; color: var(--admin-muted); font-size: .85rem; font-weight: 600; text-align: center;
  }
  .oev-step-btn:disabled { cursor: default; }
  .oev-step-dot {
    width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-size: 1.1rem;
    background: var(--admin-surface); border: 2px solid var(--admin-border); transition: all .25s;
  }
  .oev-stepper li.is-active .oev-step-btn { color: var(--admin-primary); }
  .oev-stepper li.is-active .oev-step-dot { border-color: var(--admin-primary); color: var(--admin-primary); box-shadow: var(--admin-ring); }
  .oev-stepper li.is-done .oev-step-btn { color: var(--admin-text); }
  .oev-stepper li.is-done .oev-step-dot { background: var(--admin-primary); border-color: var(--admin-primary); color: #fff; }
  .oev-step-num { font-size: .72rem; font-weight: 500; color: var(--admin-muted); display: block; }

  .oev-bloc { border: 1px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; background: var(--admin-surface-soft); height: 100%; }
  .oev-bloc-titre { display: flex; align-items: center; gap: .6rem; font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
  .oev-bloc-titre i {
    width: 2rem; height: 2rem; border-radius: 10px; display: grid; place-items: center;
    background: color-mix(in srgb, var(--admin-primary) 12%, transparent); color: var(--admin-primary);
  }
  .oev-choix { display: flex; gap: .5rem; flex-wrap: wrap; }
  .oev-choix .btn { min-width: 5.5rem; }

  .oev-piece {
    position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .35rem;
    min-height: 170px; padding: 1.25rem; text-align: center; cursor: pointer; height: 100%;
    border: 2px dashed var(--admin-border); border-radius: 14px; background: var(--admin-surface-soft); transition: all .2s;
  }
  .oev-piece:hover, .oev-piece:focus-within { border-color: var(--admin-primary); background: color-mix(in srgb, var(--admin-primary) 5%, var(--admin-surface)); }
  .oev-piece.is-filled { border-style: solid; border-color: var(--admin-success); background: color-mix(in srgb, var(--admin-success) 6%, var(--admin-surface)); }
  .oev-piece.is-invalid { border-color: var(--admin-danger); }
  .oev-piece input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  .oev-piece-icone { font-size: 2rem; color: var(--admin-primary); line-height: 1; }
  .oev-piece.is-filled .oev-piece-icone { color: var(--admin-success); }
  .oev-piece-nom { font-weight: 700; }
  .oev-piece-etat { font-size: .8rem; color: var(--admin-muted); word-break: break-all; }
  .oev-piece-apercu { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; }

  .oev-recap-bloc { border: 1px solid var(--admin-border); border-radius: 14px; padding: 1rem 1.25rem; height: 100%; }
  .oev-recap-bloc h3 { font-size: .95rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; margin-bottom: .75rem; }
  .oev-recap-bloc dl { display: grid; grid-template-columns: minmax(8rem, 40%) 1fr; gap: .35rem .75rem; margin: 0; font-size: .875rem; }
  .oev-recap-bloc dt { color: var(--admin-muted); font-weight: 500; }
  .oev-recap-bloc dd { margin: 0; font-weight: 600; word-break: break-word; }
  .oev-indicatif { gap: .45rem; font-weight: 600; }
  .oev-drapeau { border-radius: 2px; box-shadow: 0 0 0 1px rgba(0, 0, 0, .12); flex-shrink: 0; }
  .oev-nav { position: sticky; bottom: 0; z-index: 5; background: var(--admin-surface); border-top: 1px solid var(--admin-border); }

  @media (max-width: 575.98px) {
    .oev-step-label { display: none; }
    .oev-stepper li:not(:last-child)::after { left: calc(50% + 1.5rem); right: calc(-50% + 1.5rem); }
  }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi {{ $edition ? 'bi-pencil-square' : 'bi-file-earmark-medical' }}" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">{{ ! $edition ? 'Gestion des demandes' : ($oev->statut_dossier === Oev::ETAT_COMPLEMENT ? 'Validation des dossiers · Complément' : 'Constituer dossier enfant') }}</p>
        <h1 class="h3 mb-1">{{ $edition ? 'Modifier le dossier ' . $oev->reference() : 'Constituer dossier enfant' }}</h1>
        <p class="text-muted mb-0">Remplissez les étapes puis vérifiez le récapitulatif avant d’enregistrer.</p>
      </div>
    </div>
    @if ($edition)
      <div class="heading-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('oevs.show', $oev) }}"><i class="bi bi-x-lg" aria-hidden="true"></i> Annuler</a>
      </div>
    @endif
  </div>

  @unless ($edition)
    <div class="mt-3">@include('oevs._onglets')</div>
  @endunless

  {{-- Rappel de la demande à traiter : non-conformité du DR (pour le DP) ou complément du central (pour le DR) --}}
  @if ($edition && $oev->statut_dossier === Oev::ETAT_COMPLEMENT)
    <div class="alert alert-warning mt-3">
      <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
      <strong>Complément demandé par le niveau central :</strong> {{ $oev->motif_complement }}
    </div>
  @elseif ($edition && $oev->statut_dossier === Oev::ETAT_NON_CONFORME && $oev->motif_non_conformite)
    <div class="alert alert-danger mt-3">
      <i class="bi bi-x-octagon me-1" aria-hidden="true"></i>
      <strong>Motif de non-conformité (DR) :</strong> {{ $oev->motif_non_conformite }}
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger mt-3">
      <strong>Veuillez corriger les erreurs suivantes :</strong>
      <ul class="mb-0 mt-1">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
      <div class="small mt-2"><i class="bi bi-info-circle" aria-hidden="true"></i> Les informations saisies ont été conservées, mais les fichiers choisis doivent être sélectionnés à nouveau (étape 3).</div>
    </div>
  @endif

  <form id="oevWizard" method="POST" action="{{ $edition ? route('oevs.update', $oev) : route('oevs.store') }}" enctype="multipart/form-data" class="mt-3" novalidate data-etape-initiale="{{ $etapeInitiale }}">
    @csrf
    @if ($edition) @method('PUT') @endif

    <section class="panel">
      {{-- Barre de progression --}}
      <ol class="oev-stepper mb-4" aria-label="Étapes du formulaire">
        @foreach ($etapes as $numero => $etape)
          <li data-stepper="{{ $numero }}">
            <button type="button" class="oev-step-btn" data-aller="{{ $numero }}" disabled>
              <span class="oev-step-dot"><i class="bi {{ $etape['icone'] }}" aria-hidden="true"></i></span>
              <span class="oev-step-label"><span class="oev-step-num">Étape {{ $numero }}</span>{{ $etape['titre'] }}</span>
            </button>
          </li>
        @endforeach
      </ol>

      {{-- ÉTAPE 1 : Enfant, tuteur, localité --}}
      <div data-etape="1">
        <div class="row g-3">
          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-person" aria-hidden="true"></i> Informations sur l’enfant</div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label" for="nom">Nom de l’enfant <span class="text-danger">*</span></label>
                  <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ $v('nom') }}" maxlength="100" required data-recap="nom">
                  <div class="invalid-feedback">Le nom est obligatoire.</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label" for="prenom">Prénom(s) de l’enfant <span class="text-danger">*</span></label>
                  <input class="form-control @error('prenom') is-invalid @enderror" id="prenom" name="prenom" value="{{ $v('prenom') }}" maxlength="150" required data-recap="prenom">
                  <div class="invalid-feedback">Le(s) prénom(s) sont obligatoires.</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="date_naissance">Date de naissance <span class="text-danger">*</span></label>
                  <input class="form-control @error('date_naissance') is-invalid @enderror" id="date_naissance" name="date_naissance" type="date"
                         min="{{ now()->subYears(25)->addDay()->toDateString() }}" max="{{ now()->toDateString() }}"
                         value="{{ old('date_naissance', $oev->date_naissance?->toDateString()) }}" required data-recap="date_naissance">
                  <div class="form-text" data-age-calcule></div>
                  <div class="invalid-feedback">Date valide requise (enfant de moins de 25 ans, pas de date future).</div>
                </div>
                <div class="col-md-4">
                  <span class="form-label d-block">Sexe <span class="text-danger">*</span></span>
                  <div class="oev-choix" role="radiogroup" aria-label="Sexe">
                    @foreach (Oev::SEXES as $cle => $label)
                      <input class="btn-check" type="radio" name="sexe" id="sexe-{{ $cle }}" value="{{ $cle }}" @checked($v('sexe') === $cle) required data-recap="sexe" data-libelle="{{ $label }}">
                      <label class="btn btn-outline-primary" for="sexe-{{ $cle }}"><i class="bi {{ $cle === 'F' ? 'bi-gender-female' : 'bi-gender-male' }}" aria-hidden="true"></i> {{ $label }}</label>
                    @endforeach
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="statut">Statut <span class="text-danger">*</span></label>
                  <select class="form-select @error('statut') is-invalid @enderror" id="statut" name="statut" required data-recap="statut">
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::STATUTS as $cle => $label)<option value="{{ $cle }}" @selected($v('statut') === $cle)>{{ $label }}</option>@endforeach
                  </select>
                  <div class="invalid-feedback">Choisissez le statut.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="systeme_educatif">Système éducatif <span class="text-danger">*</span></label>
                  <select class="form-select @error('systeme_educatif') is-invalid @enderror" id="systeme_educatif" name="systeme_educatif" required data-recap="systeme_educatif">
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::SYSTEMES_EDUCATIFS as $cle => $label)<option value="{{ $cle }}" @selected($v('systeme_educatif') === $cle)>{{ $label }}</option>@endforeach
                  </select>
                  <div class="invalid-feedback">Choisissez le système éducatif.</div>
                </div>
                <div class="col-md-4">
                  <span class="form-label d-block">Enfant en situation de handicap <span class="text-danger">*</span></span>
                  <div class="oev-choix" role="radiogroup" aria-label="Situation de handicap">
                    <input class="btn-check" type="radio" name="handicap" id="handicap-non" value="0" @checked($handicap === '0') required data-handicap data-recap="handicap" data-libelle="Non">
                    <label class="btn btn-outline-primary" for="handicap-non">Non</label>
                    <input class="btn-check" type="radio" name="handicap" id="handicap-oui" value="1" @checked($handicap === '1') data-handicap data-recap="handicap" data-libelle="Oui">
                    <label class="btn btn-outline-primary" for="handicap-oui">Oui</label>
                  </div>
                </div>
                <div class="col-md-8" id="bloc-nature-handicap" @if ($handicap !== '1') hidden @endif>
                  <label class="form-label" for="nature_handicap">Nature du handicap <span class="text-danger">*</span></label>
                  <input class="form-control @error('nature_handicap') is-invalid @enderror" id="nature_handicap" name="nature_handicap" value="{{ $v('nature_handicap') }}" maxlength="255" placeholder="ex : handicap moteur, visuel, auditif…" data-recap="nature_handicap">
                  <div class="invalid-feedback">Précisez la nature du handicap.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-people" aria-hidden="true"></i> Parent ou tuteur de l’enfant</div>
              <div class="row g-3">
                <div class="col-sm-5">
                  <label class="form-label" for="nom_tuteur">Nom <span class="text-danger">*</span></label>
                  <input class="form-control @error('nom_tuteur') is-invalid @enderror" id="nom_tuteur" name="nom_tuteur" value="{{ $v('nom_tuteur') }}" maxlength="100" required data-recap="nom_tuteur">
                  <div class="invalid-feedback">Le nom est obligatoire.</div>
                </div>
                <div class="col-sm-7">
                  <label class="form-label" for="prenom_tuteur">Prénom(s) <span class="text-danger">*</span></label>
                  <input class="form-control @error('prenom_tuteur') is-invalid @enderror" id="prenom_tuteur" name="prenom_tuteur" value="{{ $v('prenom_tuteur') }}" maxlength="150" required data-recap="prenom_tuteur">
                  <div class="invalid-feedback">Le(s) prénom(s) sont obligatoires.</div>
                </div>
                <div class="col-12">
                  <label class="form-label" for="contact_tuteur">Contact / numéro de téléphone <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text oev-indicatif" title="Burkina Faso">
                      <svg class="oev-drapeau" viewBox="0 0 30 20" width="24" height="16" role="img" aria-label="Drapeau du Burkina Faso">
                        <rect width="30" height="10" fill="#EF2B2D"/>
                        <rect y="10" width="30" height="10" fill="#009E49"/>
                        <polygon fill="#FCD116" points="15,6.7 15.74,8.98 18.14,8.98 16.2,10.39 16.94,12.67 15,11.26 13.06,12.67 13.8,10.39 11.86,8.98 14.26,8.98"/>
                      </svg>
                      <span>{{ Oev::INDICATIF }}</span>
                    </span>
                    <input class="form-control @error('contact_tuteur') is-invalid @enderror" id="contact_tuteur" name="contact_tuteur" type="tel" inputmode="numeric" autocomplete="tel-national"
                           value="{{ $v('contact_tuteur') ? implode(' ', str_split(Oev::numeroLocal($v('contact_tuteur')), 2)) : '' }}"
                           placeholder="70 12 34 56" pattern="\d{2} \d{2} \d{2} \d{2}" required data-recap="contact_tuteur">
                    <div class="invalid-feedback">Saisissez les 8 chiffres du numéro (ex : 70 12 34 56).</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-geo-alt" aria-hidden="true"></i> Localité</div>
              <div class="row g-3">
                @foreach (['region' => 'Région', 'province' => 'Province', 'commune' => 'Commune'] as $champ => $label)
                  <div class="{{ $champ === 'commune' ? 'col-12' : 'col-sm-6' }}">
                    <label class="form-label" for="{{ $champ }}">{{ $label }} <span class="text-danger">*</span></label>
                    <input class="form-control @error($champ) is-invalid @enderror" id="{{ $champ }}" name="{{ $champ }}" value="{{ $v($champ) }}" maxlength="100" required data-recap="{{ $champ }}">
                    <div class="invalid-feedback">{{ $label }} obligatoire.</div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ÉTAPE 2 : Scolarité --}}
      <div data-etape="2" hidden>
        <div class="row g-3">
          <div class="col-lg-5">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-clock-history" aria-hidden="true"></i> Année précédente</div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="etablissement_precedent">Établissement fréquenté</label>
                  <input class="form-control @error('etablissement_precedent') is-invalid @enderror" id="etablissement_precedent" name="etablissement_precedent" value="{{ $v('etablissement_precedent') }}" maxlength="255" data-recap="etablissement_precedent">
                </div>
                <div class="col-12">
                  <label class="form-label" for="moyenne_annuelle">Moyenne annuelle obtenue</label>
                  <div class="input-group">
                    <input class="form-control @error('moyenne_annuelle') is-invalid @enderror" id="moyenne_annuelle" name="moyenne_annuelle" type="number" step="0.01" min="0" max="20" value="{{ $v('moyenne_annuelle') }}" data-recap="moyenne_annuelle">
                    <span class="input-group-text">/ 20</span>
                    <div class="invalid-feedback">Moyenne entre 0 et 20.</div>
                  </div>
                </div>
                <div class="col-12">
                  <span class="form-label d-block">Appréciation</span>
                  <div class="oev-choix" role="radiogroup" aria-label="Appréciation">
                    @foreach (Oev::APPRECIATIONS as $cle => $label)
                      <input class="btn-check" type="radio" name="appreciation" id="appreciation-{{ $cle }}" value="{{ $cle }}" @checked($v('appreciation') === $cle) data-recap="appreciation" data-libelle="{{ $label }}">
                      <label class="btn btn-outline-{{ $cle === 'admis' ? 'success' : ($cle === 'redouble' ? 'warning' : 'danger') }}" for="appreciation-{{ $cle }}">{{ $label }}</label>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-mortarboard" aria-hidden="true"></i> Année en cours</div>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label" for="etablissement_actuel">Établissement fréquenté <span class="text-danger">*</span></label>
                  <input class="form-control @error('etablissement_actuel') is-invalid @enderror" id="etablissement_actuel" name="etablissement_actuel" value="{{ $v('etablissement_actuel') }}" maxlength="255" required data-recap="etablissement_actuel">
                  <div class="invalid-feedback">L’établissement est obligatoire.</div>
                </div>
                <div class="col-md-4">
                  <span class="form-label d-block">Public ou privé <span class="text-danger">*</span></span>
                  <div class="oev-choix" role="radiogroup" aria-label="Type d’établissement">
                    @foreach (Oev::TYPES_ETABLISSEMENT as $cle => $label)
                      <input class="btn-check" type="radio" name="type_etablissement" id="etab-{{ $cle }}" value="{{ $cle }}" @checked($v('type_etablissement') === $cle) required data-recap="type_etablissement" data-libelle="{{ $label }}">
                      <label class="btn btn-outline-primary" for="etab-{{ $cle }}">{{ $label }}</label>
                    @endforeach
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="classe">Classe <span class="text-danger">*</span></label>
                  <input class="form-control @error('classe') is-invalid @enderror" id="classe" name="classe" value="{{ $v('classe') }}" maxlength="50" placeholder="ex : CM2, 6e, 2nde C…" required data-recap="classe">
                  <div class="invalid-feedback">La classe est obligatoire.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="frais_scolarite">Frais de scolarité <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input class="form-control @error('frais_scolarite') is-invalid @enderror" id="frais_scolarite" name="frais_scolarite" type="number" min="0" step="1" value="{{ $v('frais_scolarite') }}" required data-recap="frais_scolarite">
                    <span class="input-group-text">FCFA</span>
                    <div class="invalid-feedback">Indiquez les frais (0 si aucun).</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ÉTAPE 3 : Fichiers --}}
      <div data-etape="3" hidden>
        <p class="text-muted mb-3">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          Cliquez sur une carte (ou glissez-y un fichier) : PDF, JPG ou PNG, <strong>{{ $enMo($maxFichiersKo['acte_naissance']) }} max par fichier</strong>
          (photo : JPG ou PNG, {{ $enMo($maxFichiersKo['photo']) }} max), <strong>{{ $enMo($maxEnvoiKo) }} au total</strong>.
          Les pièces sont facultatives : le dossier pourra être complété plus tard.
        </p>
        <div class="alert alert-danger py-2" data-alerte-taille hidden></div>
        <div class="row g-3">
          @foreach (Oev::DOCUMENTS as $type => $libelle)
            @php($existant = $documents->get($type))
            <div class="col-sm-6 col-xl-4">
              <label class="oev-piece {{ $existant ? 'is-filled' : '' }} @error($type) is-invalid @enderror" data-piece="{{ $type }}" data-existant="{{ $existant?->nom_original }}">
                <input type="file" id="{{ $type }}" name="{{ $type }}" accept="{{ $type === 'photo' ? '.jpg,.jpeg,.png' : '.pdf,.jpg,.jpeg,.png' }}" aria-label="{{ $libelle }}">
                @if ($type === 'photo' && $existant)
                  <img class="oev-piece-apercu" src="{{ route('oevs.documents.show', [$oev, $existant]) }}" alt="" data-apercu>
                  <i class="bi {{ $iconesPieces[$type] }} oev-piece-icone" aria-hidden="true" hidden data-icone></i>
                @else
                  <img class="oev-piece-apercu" alt="" data-apercu hidden>
                  <i class="bi {{ $existant ? 'bi-check-circle-fill' : $iconesPieces[$type] }} oev-piece-icone" aria-hidden="true" data-icone data-icone-base="{{ $iconesPieces[$type] }}"></i>
                @endif
                <span class="oev-piece-nom">{{ $libelle }}</span>
                <span class="oev-piece-etat" data-etat>{{ $existant ? 'Déjà fourni : ' . $existant->nom_original . ' (cliquer pour remplacer)' : 'Cliquer pour choisir un fichier' }}</span>
                @error($type)<span class="text-danger small">{{ $message }}</span>@enderror
              </label>
            </div>
          @endforeach
          <div class="col-sm-6 col-xl-4">
            <div class="oev-bloc d-flex flex-column justify-content-center">
              <div class="oev-bloc-titre mb-2"><i class="bi bi-building" aria-hidden="true"></i> Structure du RIB</div>
              <label class="form-label" for="nom_structure_rib">Nom de la structure <span class="text-danger" data-rib-requis hidden>*</span></label>
              <input class="form-control @error('nom_structure_rib') is-invalid @enderror" id="nom_structure_rib" name="nom_structure_rib" value="{{ $v('nom_structure_rib') }}" maxlength="255" placeholder="Obligatoire si un RIB est chargé" data-recap="nom_structure_rib">
              <div class="invalid-feedback">Précisez le nom de la structure titulaire du RIB.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ÉTAPE 4 : Récapitulatif --}}
      <div data-etape="4" hidden>
        <div class="alert alert-info d-flex gap-2 align-items-center">
          <i class="bi bi-eye fs-5" aria-hidden="true"></i>
          <span>Vérifiez les informations ci-dessous. Utilisez « Modifier » pour revenir sur une étape.</span>
        </div>
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="oev-recap-bloc">
              <h3><span><i class="bi bi-person me-1" aria-hidden="true"></i> Enfant</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="1">Modifier</button></h3>
              <dl>
                <dt>Nom</dt><dd data-r="nom"></dd>
                <dt>Prénom(s)</dt><dd data-r="prenom"></dd>
                <dt>Sexe</dt><dd data-r="sexe"></dd>
                <dt>Date de naissance</dt><dd data-r="date_naissance" data-format="date"></dd>
                <dt>Statut</dt><dd data-r="statut"></dd>
                <dt>Handicap</dt><dd data-r="handicap"></dd>
                <dt data-si-handicap>Nature du handicap</dt><dd data-r="nature_handicap" data-si-handicap></dd>
                <dt>Système éducatif</dt><dd data-r="systeme_educatif"></dd>
              </dl>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="row g-3">
              <div class="col-12">
                <div class="oev-recap-bloc">
                  <h3><span><i class="bi bi-people me-1" aria-hidden="true"></i> Parent / tuteur</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="1">Modifier</button></h3>
                  <dl>
                    <dt>Nom</dt><dd data-r="nom_tuteur"></dd>
                    <dt>Prénom(s)</dt><dd data-r="prenom_tuteur"></dd>
                    <dt>Contact</dt><dd data-r="contact_tuteur" data-prefixe="{{ Oev::INDICATIF }} "></dd>
                  </dl>
                </div>
              </div>
              <div class="col-12">
                <div class="oev-recap-bloc">
                  <h3><span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i> Localité</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="1">Modifier</button></h3>
                  <dl>
                    <dt>Région</dt><dd data-r="region"></dd>
                    <dt>Province</dt><dd data-r="province"></dd>
                    <dt>Commune</dt><dd data-r="commune"></dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="oev-recap-bloc">
              <h3><span><i class="bi bi-mortarboard me-1" aria-hidden="true"></i> Scolarité</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="2">Modifier</button></h3>
              <dl>
                <dt>Établ. précédent</dt><dd data-r="etablissement_precedent"></dd>
                <dt>Moyenne annuelle</dt><dd data-r="moyenne_annuelle" data-format="decimal" data-suffixe=" / 20"></dd>
                <dt>Appréciation</dt><dd data-r="appreciation"></dd>
                <dt>Établ. en cours</dt><dd data-r="etablissement_actuel"></dd>
                <dt>Public / privé</dt><dd data-r="type_etablissement"></dd>
                <dt>Classe</dt><dd data-r="classe"></dd>
                <dt>Frais de scolarité</dt><dd data-r="frais_scolarite" data-format="fcfa"></dd>
              </dl>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="oev-recap-bloc">
              <h3><span><i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Pièces du dossier</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="3">Modifier</button></h3>
              <ul class="list-unstyled mb-2" id="recapPieces"></ul>
              <div class="small text-muted" data-recap-rib></div>
            </div>
          </div>
        </div>
      </div>

      {{-- Navigation --}}
      <div class="oev-nav d-flex justify-content-between align-items-center gap-2 mt-4 pt-3 pb-1">
        <button type="button" class="btn btn-outline-secondary" data-precedent hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Précédent</button>
        <span class="text-muted small ms-auto me-2 d-none d-sm-inline" data-compteur></span>
        <button type="button" class="btn btn-primary px-4" data-suivant>Suivant <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        <span class="d-flex flex-wrap gap-2" data-enregistrer hidden>
          <input type="hidden" name="soumettre" value="0" data-champ-soumettre>
          <button type="submit" class="btn {{ $peutSoumettre ? 'btn-outline-success' : 'btn-success' }} px-4"><i class="bi bi-save" aria-hidden="true"></i> {{ $edition ? 'Enregistrer les modifications' : 'Enregistrer le dossier' }}</button>
          @if ($peutSoumettre)
            <button type="submit" class="btn btn-success px-4" data-avec-soumission title="Le dossier doit comporter les {{ count(Oev::DOCUMENTS) }} pièces"><i class="bi bi-send-check" aria-hidden="true"></i> Enregistrer et soumettre au DR</button>
          @endif
        </span>
      </div>
    </section>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('oevWizard');
  var total = 4;
  var courante = 1;
  var atteinte = 1;
  var pieces = @json(Oev::DOCUMENTS);

  /* ----- Limites de taille des fichiers (en Ko, fournies par le serveur) ----- */
  var limites = { fichiers: @json($maxFichiersKo), envoi: {{ min($maxEnvoiKo, PHP_INT_MAX >> 11) }} };
  var zoneAlerteTaille = form.querySelector('[data-alerte-taille]');
  function enMo(ko) { return (ko / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' Mo'; }
  function alerteTaille(message) { zoneAlerteTaille.textContent = message; zoneAlerteTaille.hidden = !message; }
  function tailleTotaleKo() {
    var octets = 0;
    form.querySelectorAll('input[type=file]').forEach(function (i) { if (i.files[0]) { octets += i.files[0].size; } });
    return octets / 1024;
  }

  var panneaux = form.querySelectorAll('[data-etape]');
  var puces = form.querySelectorAll('[data-stepper]');
  var btnPrec = form.querySelector('[data-precedent]');
  var btnSuiv = form.querySelector('[data-suivant]');
  var btnEnr = form.querySelector('[data-enregistrer]');
  var compteur = form.querySelector('[data-compteur]');

  /* ----- Handicap ----- */
  var blocHandicap = document.getElementById('bloc-nature-handicap');
  var natureHandicap = document.getElementById('nature_handicap');
  function majHandicap() {
    var oui = document.getElementById('handicap-oui').checked;
    blocHandicap.hidden = !oui;
    natureHandicap.required = oui;
  }
  form.querySelectorAll('[data-handicap]').forEach(function (r) { r.addEventListener('change', majHandicap); });
  majHandicap();

  /* ----- Date de naissance : âge calculé (années révolues) ----- */
  var champDate = document.getElementById('date_naissance');
  var ageCalcule = form.querySelector('[data-age-calcule]');
  function ageDepuis(dateIso) {
    var p = dateIso.split('-').map(Number);
    var auj = new Date();
    var age = auj.getFullYear() - p[0];
    if (auj.getMonth() + 1 < p[1] || (auj.getMonth() + 1 === p[1] && auj.getDate() < p[2])) { age--; }
    return Math.max(0, age);
  }
  function majAge() {
    var ok = champDate.value && champDate.checkValidity();
    ageCalcule.textContent = ok ? 'Âge : ' + ageDepuis(champDate.value) + ' an(s)' : '';
  }
  champDate.addEventListener('input', majAge);
  champDate.addEventListener('change', majAge);
  majAge();

  /* ----- Téléphone : 8 chiffres groupés par deux (l'indicatif +226 est affiché à part) ----- */
  var champTel = document.getElementById('contact_tuteur');
  champTel.addEventListener('input', function () {
    var chiffres = champTel.value.replace(/\D/g, '');
    if (chiffres.length > 8 && chiffres.indexOf('226') === 0) { chiffres = chiffres.slice(3); }
    champTel.value = chiffres.slice(0, 8).replace(/(\d{2})(?=\d)/g, '$1 ');
  });

  /* ----- RIB : nom de structure obligatoire si RIB fourni ----- */
  var nomStructure = document.getElementById('nom_structure_rib');
  function majRib() {
    var carteRib = form.querySelector('[data-piece="rib"]');
    var ribFourni = document.getElementById('rib').files.length > 0 || carteRib.dataset.existant;
    nomStructure.required = !!ribFourni;
    form.querySelector('[data-rib-requis]').hidden = !ribFourni;
  }

  /* ----- Cartes de pièces ----- */
  form.querySelectorAll('[data-piece]').forEach(function (carte) {
    var input = carte.querySelector('input[type=file]');
    var etat = carte.querySelector('[data-etat]');
    var icone = carte.querySelector('[data-icone]');
    var apercu = carte.querySelector('[data-apercu]');
    input.addEventListener('change', function () {
      var fichier = input.files[0];
      carte.classList.remove('is-invalid');
      if (fichier && fichier.size > limites.fichiers[carte.dataset.piece] * 1024) {
        alerteTaille('« ' + fichier.name + ' » pèse ' + enMo(fichier.size / 1024) + ' : le maximum pour « ' + pieces[carte.dataset.piece] + ' » est de ' + enMo(limites.fichiers[carte.dataset.piece]) + '. Choisissez un fichier plus léger.');
        input.value = '';
        carte.classList.add('is-invalid');
        fichier = null;
      } else {
        alerteTaille('');
      }
      if (fichier) {
        carte.classList.add('is-filled');
        etat.textContent = fichier.name + ' (' + Math.max(1, Math.round(fichier.size / 1024)) + ' Ko)';
        if (fichier.type.indexOf('image/') === 0 && carte.dataset.piece === 'photo') {
          apercu.src = URL.createObjectURL(fichier);
          apercu.hidden = false;
          icone.hidden = true;
        } else {
          icone.className = 'bi bi-check-circle-fill oev-piece-icone';
        }
      } else if (!carte.dataset.existant) {
        carte.classList.remove('is-filled');
        etat.textContent = 'Cliquer pour choisir un fichier';
        icone.className = 'bi ' + icone.dataset.iconeBase + ' oev-piece-icone';
      }
      if (carte.dataset.piece === 'rib') { majRib(); }
    });
  });
  majRib();

  /* ----- Validation d'une étape ----- */
  function champsEtape(n) {
    return Array.prototype.filter.call(
      form.querySelector('[data-etape="' + n + '"]').querySelectorAll('input:not([type=file]), select, textarea'),
      function (c) { return !c.closest('[hidden]'); }
    );
  }
  function validerEtape(n) {
    var premier = null;
    champsEtape(n).forEach(function (c) {
      var ok = c.checkValidity();
      c.classList.toggle('is-invalid', !ok);
      if (c.type === 'radio') {
        form.querySelectorAll('input[name="' + c.name + '"] + label').forEach(function (l) { l.classList.toggle('border-danger', !ok); });
      }
      if (!ok && !premier) { premier = c; }
    });
    if (premier) { premier.focus({ preventScroll: false }); }
    return !premier;
  }
  form.addEventListener('input', function (e) { if (e.target.classList.contains('is-invalid') && e.target.checkValidity()) { e.target.classList.remove('is-invalid'); } });
  form.addEventListener('change', function (e) {
    if (e.target.type === 'radio') {
      form.querySelectorAll('input[name="' + e.target.name + '"] + label').forEach(function (l) { l.classList.remove('border-danger'); });
    }
  });

  /* ----- Récapitulatif ----- */
  function valeurRecap(nom) {
    var champs = form.querySelectorAll('[name="' + nom + '"]');
    if (!champs.length) { return ''; }
    var c = champs[0];
    if (c.type === 'radio') {
      var coche = form.querySelector('[name="' + nom + '"]:checked');
      return coche ? (coche.dataset.libelle || coche.value) : '';
    }
    if (c.tagName === 'SELECT') { return c.value ? c.options[c.selectedIndex].text : ''; }
    return c.value.trim();
  }
  function construireRecap() {
    form.querySelectorAll('[data-r]').forEach(function (dd) {
      var val = valeurRecap(dd.dataset.r);
      if (val && dd.dataset.format === 'fcfa') { val = Number(val).toLocaleString('fr-FR') + ' FCFA'; }
      else if (val && dd.dataset.format === 'date') { val = val.split('-').reverse().join('/') + ' (' + ageDepuis(val) + ' ans)'; }
      else if (val && dd.dataset.format === 'decimal') { val = Number(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      if (val && dd.dataset.suffixe) { val += dd.dataset.suffixe; }
      if (val && dd.dataset.prefixe) { val = dd.dataset.prefixe + val; }
      dd.textContent = val || '—';
      dd.classList.toggle('text-muted', !val);
    });
    var handicap = document.getElementById('handicap-oui').checked;
    form.querySelectorAll('[data-si-handicap]').forEach(function (el) { el.hidden = !handicap; });

    var liste = document.getElementById('recapPieces');
    liste.innerHTML = '';
    var fournies = 0;
    Object.keys(pieces).forEach(function (type) {
      var carte = form.querySelector('[data-piece="' + type + '"]');
      var fichier = document.getElementById(type).files[0];
      var nom = fichier ? fichier.name : carte.dataset.existant;
      if (nom) { fournies++; }
      var li = document.createElement('li');
      li.className = 'd-flex gap-2 align-items-start py-1';
      var ic = document.createElement('i');
      ic.className = 'bi ' + (nom ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-secondary');
      var txt = document.createElement('span');
      txt.innerHTML = '<strong></strong><br><small class="text-muted"></small>';
      txt.querySelector('strong').textContent = pieces[type];
      txt.querySelector('small').textContent = nom ? (fichier ? 'Nouveau : ' : 'Déjà fourni : ') + nom : 'Non fourni';
      li.appendChild(ic); li.appendChild(txt);
      liste.appendChild(li);
    });
    var rib = form.querySelector('[data-recap-rib]');
    rib.textContent = (nomStructure.value.trim() ? 'Structure du RIB : ' + nomStructure.value.trim() + ' — ' : '') +
      fournies + '/' + Object.keys(pieces).length + ' pièce(s) : ' + (fournies === Object.keys(pieces).length ? 'dossier complet.' : 'dossier incomplet (complétable plus tard).');
  }

  /* ----- Navigation ----- */
  function afficher(n, sansDefilement) {
    courante = n;
    atteinte = Math.max(atteinte, n);
    panneaux.forEach(function (p) { p.hidden = Number(p.dataset.etape) !== n; });
    puces.forEach(function (li) {
      var i = Number(li.dataset.stepper);
      li.classList.toggle('is-active', i === n);
      li.classList.toggle('is-done', i < n);
      var btn = li.querySelector('button');
      btn.disabled = i > atteinte || i === n;
      if (i === n) { btn.setAttribute('aria-current', 'step'); } else { btn.removeAttribute('aria-current'); }
    });
    btnPrec.hidden = n === 1;
    btnSuiv.hidden = n === total;
    btnEnr.hidden = n !== total;
    compteur.textContent = 'Étape ' + n + ' sur ' + total;
    if (n === total) { construireRecap(); }
    if (!sansDefilement) { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  }
  function aller(n) {
    // On peut toujours revenir en arrière ; pour avancer, chaque étape intermédiaire doit être valide.
    for (var i = courante; i < n; i++) {
      if (!validerEtape(i)) { if (i !== courante) { afficher(i); validerEtape(i); } return; }
    }
    afficher(n);
  }
  btnSuiv.addEventListener('click', function () { aller(courante + 1); });
  btnPrec.addEventListener('click', function () { afficher(courante - 1); });
  form.querySelectorAll('[data-aller]').forEach(function (b) { b.addEventListener('click', function () { aller(Number(b.dataset.aller)); }); });

  // Entrée dans un champ = étape suivante (pas d'envoi prématuré)
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && courante < total) { e.preventDefault(); aller(courante + 1); }
  });
  form.addEventListener('submit', function (e) {
    for (var i = 1; i < total; i++) {
      if (!validerEtape(i)) { e.preventDefault(); afficher(i); validerEtape(i); return; }
    }
    // Marge de 256 Ko pour les champs texte de la requête
    var totalKo = tailleTotaleKo();
    if (totalKo > limites.envoi - 256) {
      e.preventDefault();
      afficher(3);
      alerteTaille('Les fichiers sélectionnés totalisent ' + enMo(totalKo) + ' : le maximum autorisé en un seul envoi est de ' + enMo(limites.envoi - 256) + '. Retirez ou allégez certaines pièces (elles pourront être ajoutées plus tard via « Modifier / compléter »).');
      return;
    }
    // « Enregistrer et soumettre au DR » : le serveur soumet le dossier s'il est complet
    var avecSoumission = e.submitter && e.submitter.hasAttribute('data-avec-soumission');
    form.querySelector('[data-champ-soumettre]').value = avecSoumission ? '1' : '0';
    btnEnr.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    if (e.submitter) {
      e.submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ' + (avecSoumission ? 'Soumission…' : 'Enregistrement…');
    }
  });

  var initiale = Number(form.dataset.etapeInitiale) || 1;
  atteinte = @json($edition || $errors->any()) ? total : initiale;
  afficher(initiale, true);
});
</script>
@endpush
