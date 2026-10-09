@extends('layouts.app')

@use('App\Models\Oev')

@php
  $edition = $oev->exists;
  // Le DP peut enregistrer et soumettre au DR en une fois (création, ou dossier en constitution / non conforme)
  $peutSoumettre = auth()->user()->can('constituer dossiers') && (! $edition || $oev->estModifiable());

  // Valeurs à afficher : saisie précédente (après erreur) sinon valeur enregistrée
  $v = fn (string $champ) => old($champ, $oev->{$champ});
  $vDate = fn (string $champ) => old($champ, $oev->{$champ}?->toDateString());
  $vBool = function (string $champ) use ($oev) {
      $valeur = old($champ, $oev->{$champ});
      return $valeur === null || $valeur === '' ? null : ((string) $valeur === '1' || $valeur === true ? '1' : '0');
  };
  $vListe = fn (string $champ) => old($champ, $oev->{$champ} ?? []);
  // Lieu de naissance : id de commune, « autre » si un lieu hors référentiel est saisi
  // Lieu de naissance saisi librement (un ancien dossier rattaché à une commune affiche son nom)
  $lieuNaissance = old('lieu_naissance', $oev->lieuDeNaissance());
  $lieuProvenanceChoisi = old('lieu_provenance_commune_id', $oev->lieu_provenance_commune_id ?? ($oev->lieu_provenance ? 'autre' : ''));
  // Gestionnaire du cas : l'agent qui saisit le dossier, par défaut
  $gestionnaireNom = old('gestionnaire_nom', $oev->exists ? $oev->gestionnaire_nom : auth()->user()->name);
  // Classes dont la moyenne est notée sur 10 (préscolaire, primaire)
  $classesSur10 = collect(Oev::NIVEAUX_NOTES_SUR_10)->flatMap(fn ($niveau) => array_keys(Oev::classesDuNiveau($niveau)))->values();

  $etapes = [
    1 => ['titre' => 'Enfant & localité', 'icone' => 'bi-person-vcard'],
    2 => ['titre' => 'Parents & tuteur', 'icone' => 'bi-people'],
    3 => ['titre' => 'Situation & santé', 'icone' => 'bi-house-heart'],
    4 => ['titre' => 'Scolarité', 'icone' => 'bi-mortarboard'],
    5 => ['titre' => 'Pièces du dossier', 'icone' => 'bi-folder2-open'],
    6 => ['titre' => 'Récapitulatif', 'icone' => 'bi-clipboard-check'],
  ];
  $etapeFichiers = 5;

  // Étape à ouvrir en cas d'erreur de validation côté serveur
  $champsParEtape = [
    1 => ['nom', 'prenom', 'sexe', 'date_naissance', 'date_naissance_estimee', 'lieu_naissance', 'nationalite', 'a_acte_naissance', 'numero_acte_naissance', 'groupe_population',
          'region_id', 'province_id', 'commune_id', 'village_id', 'quartier', 'lieu_provenance_commune_id', 'lieu_provenance'],
    2 => ['mere_nom', 'mere_prenoms', 'mere_vivante', 'mere_date_deces', 'mere_deces_confirme', 'pere_nom', 'pere_prenoms', 'pere_vivant', 'pere_date_deces', 'pere_deces_confirme',
          'tuteur_lien', 'tuteur_lien_precision', 'nom_tuteur', 'prenom_tuteur', 'contact_tuteur', 'tuteur_sexe', 'tuteur_a_cnib', 'tuteur_cnib', 'tuteur_pret_continuer', 'tuteur_raison_arret'],
    3 => ['lieu_de_vie', 'lieu_de_vie_precision', 'vulnerabilites', 'vulnerabilite_precision', 'handicap', 'types_handicap', 'nature_handicap', 'maladie_chronique', 'maladie_nom', 'suivi_clinique',
          'source_revenu', 'niveau_revenu', 'logement', 'date_identification', 'identifie_par', 'niveau_priorite',
          'gestionnaire_nom', 'gestionnaire_fonction', 'gestionnaire_contact'],
    4 => ['situation_scolaire', 'systeme_educatif', 'niveau_etude', 'raison_non_scolarisation', 'raison_non_scolarisation_precision', 'etablissement_precedent', 'classe_precedente', 'moyenne_annuelle',
          'appreciation', 'performance_scolaire', 'performance_difficultes', 'etablissement_actuel', 'type_etablissement', 'classe', 'frais_scolarite',
          'formation_professionnelle', 'formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois'],
    5 => array_merge(array_keys(Oev::DOCUMENTS), ['nom_structure_rib']),
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
  // Pièce demandée seulement si la condition est remplie (mêmes règles que Oev::pieceRequise)
  $conditionsPieces = [
    'acte_naissance' => 'a_acte_naissance:1',
    'certificat_scolarite' => 'situation_scolaire:scolarise',
    'cnib_tuteur' => 'tuteur_a_cnib:1',
  ];

  // Limites de taille réelles (application ∩ configuration PHP du serveur)
  $maxFichiersKo = collect(Oev::DOCUMENTS)->keys()->mapWithKeys(fn ($t) => [$t => Oev::tailleMaxFichierKo($t)])->all();
  $maxEnvoiKo = Oev::tailleMaxEnvoiKo();
  $enMo = fn (int $ko) => str_replace('.', ',', (string) round($ko / 1024, 1)) . ' Mo';

  $groupesMobiles = implode('|', Oev::GROUPES_MOBILES);
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
  .oev-sous-bloc { border-top: 1px dashed var(--admin-border); padding-top: 1rem; margin-top: .25rem; }
  .oev-choix { display: flex; gap: .5rem; flex-wrap: wrap; }
  .oev-choix .btn { min-width: 5.5rem; }
  .oev-cases { display: flex; gap: .4rem; flex-wrap: wrap; }
  .oev-cases .btn .bi-check2 { display: none; }
  .oev-cases .btn-check:checked + .btn .bi-check2 { display: inline; }
  .oev-cases .btn-check:disabled + .btn { opacity: 1; cursor: not-allowed; border-style: dashed; }
  .oev-cases .btn-check:disabled:checked + .btn { border-style: solid; }
  .oev-auto { color: var(--admin-primary); }
  .btn-check:checked + .btn .oev-auto { color: #fff; }
  .oev-statut-calcule { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; padding: .75rem 1rem; border-radius: 12px; background: var(--admin-surface); border: 1px solid var(--admin-border); }
  .oev-note { display: flex; gap: .6rem; align-items: flex-start; padding: .75rem 1rem; border-radius: 12px; font-size: .85rem;
    background: var(--admin-surface); border: 1px dashed var(--admin-border); color: var(--admin-muted); height: 100%; }

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
  .d-contents { display: contents; }
  .oev-indicatif { gap: .45rem; font-weight: 600; }
  .oev-drapeau { border-radius: 2px; box-shadow: 0 0 0 1px rgba(0, 0, 0, .12); flex-shrink: 0; }
  .oev-nav { position: sticky; bottom: 0; z-index: 5; background: var(--admin-surface); border-top: 1px solid var(--admin-border); }

  @media (max-width: 991.98px) {
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
        <p class="eyebrow mb-1">{{ ! $edition ? 'Dossiers enfants' : ($oev->statut_dossier === Oev::ETAT_COMPLEMENT ? 'Validation des dossiers · Complément' : 'Constituer dossier enfant') }}</p>
        <h1 class="h3 mb-1">{{ $edition ? 'Modifier le dossier ' . $oev->reference() : 'Constituer dossier enfant' }}</h1>
        <p class="text-muted mb-0">Remplissez les étapes puis vérifiez le récapitulatif. Les champs marqués <span class="text-danger">*</span> sont obligatoires ; les questions s’adaptent à vos réponses.</p>
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

  {{-- Erreurs affichées par SweetAlert (partials/alertes), avec ce rappel sur les fichiers --}}
  @php
    $noteErreursFormulaire = "Les informations saisies ont été conservées, mais les fichiers choisis doivent être sélectionnés à nouveau (étape {$etapeFichiers}).";
  @endphp

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

      {{-- ================= ÉTAPE 1 : Enfant et localité ================= --}}
      <div data-etape="1">
        <div class="row g-3">
          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-person" aria-hidden="true"></i> Identité de l’enfant</div>
              <div class="row g-3">
                <div class="col-md-4">@include('oevs.champs._texte', ['nom' => 'nom', 'label' => 'Nom de l’enfant', 'valeur' => $v('nom'), 'max' => 100, 'requis' => true, 'erreur' => 'Le nom est obligatoire.'])</div>
                <div class="col-md-5">@include('oevs.champs._texte', ['nom' => 'prenom', 'label' => 'Prénom(s) de l’enfant', 'valeur' => $v('prenom'), 'max' => 150, 'requis' => true, 'erreur' => 'Le(s) prénom(s) sont obligatoires.'])</div>
                <div class="col-md-3">
                  @include('oevs.champs._texte', ['nom' => 'date_naissance', 'label' => 'Date de naissance', 'valeur' => $vDate('date_naissance'), 'type' => 'date', 'requis' => true,
                    'attributs' => 'min="' . now()->subYears(25)->addDay()->toDateString() . '" max="' . now()->toDateString() . '"',
                    'erreur' => 'Date valide requise (moins de 25 ans, pas de date future).'])
                  <div class="form-check mt-1">
                    <input type="hidden" name="date_naissance_estimee" value="0">
                    <input class="form-check-input" type="checkbox" id="date_naissance_estimee" name="date_naissance_estimee" value="1" @checked($vBool('date_naissance_estimee') === '1')>
                    <label class="form-check-label small" for="date_naissance_estimee">Date estimée (inscrire 01/01/année)</label>
                  </div>
                  <div class="form-text" data-age-calcule></div>
                </div>
                <div class="col-md-4">
                  <span class="form-label d-block">Sexe <span class="text-danger">*</span></span>
                  <div class="oev-choix" role="radiogroup" aria-label="Sexe">
                    @foreach (Oev::SEXES as $cle => $label)
                      <input class="btn-check" type="radio" name="sexe" id="sexe-{{ $cle }}" value="{{ $cle }}" @checked($v('sexe') === $cle) required data-libelle="{{ $label }}">
                      <label class="btn btn-outline-primary" for="sexe-{{ $cle }}"><i class="bi {{ $cle === 'F' ? 'bi-gender-female' : 'bi-gender-male' }}" aria-hidden="true"></i> {{ $label }}</label>
                    @endforeach
                  </div>
                  <div class="text-danger small mt-1" data-erreur-groupe="sexe" hidden>Ce choix est obligatoire.</div>
                </div>
                <div class="col-md-4">@include('oevs.champs._texte', ['nom' => 'lieu_naissance', 'label' => 'Lieu de naissance', 'valeur' => $lieuNaissance, 'max' => 150, 'placeholder' => 'Ville ou village, pays'])</div>
                <div class="col-md-4">@include('oevs.champs._texte', ['nom' => 'nationalite', 'label' => 'Nationalité', 'valeur' => $v('nationalite') ?? ($edition ? null : 'Burkinabè'), 'max' => 100])</div>
                <div class="col-md-4">@include('oevs.champs._choix', ['nom' => 'a_acte_naissance', 'label' => 'L’enfant a-t-il un acte de naissance ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('a_acte_naissance'), 'requis' => true, 'aide' => 'Détermine si l’acte de naissance est demandé dans les pièces.'])</div>
                <div class="col-md-4" data-si="a_acte_naissance:1">@include('oevs.champs._texte', ['nom' => 'numero_acte_naissance', 'label' => 'N° de l’acte de naissance', 'valeur' => $v('numero_acte_naissance'), 'max' => 50, 'requis' => true, 'placeholder' => 'Numéro inscrit sur l’acte', 'erreur' => 'Saisissez le numéro de l’acte de naissance.'])</div>
                <div class="col-md-4">@include('oevs.champs._choix', ['nom' => 'groupe_population', 'label' => 'Groupe de population', 'options' => Oev::GROUPES_POPULATION, 'valeur' => $v('groupe_population'), 'mode' => 'liste'])</div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-geo-alt" aria-hidden="true"></i> Adresse actuelle de l’enfant</div>
              <div class="row g-3">
                {{-- Région > Province > Commune > Village : listes du référentiel des localités (limitées à la zone de l'agent) --}}
                <div class="col-12">
                  @include('partials.localite-selects', [
                    'localites' => $localitesZone,
                    'avecVillage' => true,
                    'valeurs' => $oev->only(['region_id', 'province_id', 'commune_id', 'village_id']),
                    'colonnes' => ['region_id' => 'col-md-3', 'province_id' => 'col-md-3', 'commune_id' => 'col-md-3', 'village_id' => 'col-md-3'],
                  ])
                </div>
                <div class="col-md-6">@include('oevs.champs._texte', ['nom' => 'quartier', 'label' => 'Quartier / précision de l’adresse', 'valeur' => $v('quartier'), 'max' => 150, 'placeholder' => 'Quartier, hameau, repère… (ou village non encore enregistré)'])</div>
                {{-- Lieu de provenance (enfant déplacé, réfugié…) : commune du référentiel, ou « autre lieu » à préciser --}}
                <div class="col-md-6" data-si="groupe_population:{{ $groupesMobiles }}">
                  <label class="form-label" for="lieu_provenance_commune_id">Lieu de provenance de l’enfant</label>
                  <select class="form-select @error('lieu_provenance_commune_id') is-invalid @enderror" id="lieu_provenance_commune_id" name="lieu_provenance_commune_id">
                    <option value="">Sélectionner la commune d’origine…</option>
                    @foreach ($localites as $regionLieu)
                      @foreach ($regionLieu['provinces'] as $provinceLieu)
                        <optgroup label="{{ $provinceLieu['nom'] }} ({{ $regionLieu['nom'] }})">
                          @foreach ($provinceLieu['communes'] as $communeLieu)
                            <option value="{{ $communeLieu['id'] }}" @selected((string) $lieuProvenanceChoisi === (string) $communeLieu['id'])>{{ $communeLieu['nom'] }}</option>
                          @endforeach
                        </optgroup>
                      @endforeach
                    @endforeach
                    <option value="autre" @selected($lieuProvenanceChoisi === 'autre')>Autre lieu / hors du Burkina Faso</option>
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('lieu_provenance_commune_id') ?: 'Choisissez le lieu de provenance dans la liste.' }}</div>
                </div>
                <div class="col-md-6" data-si="groupe_population:{{ $groupesMobiles }}&&lieu_provenance_commune_id:autre">@include('oevs.champs._texte', ['nom' => 'lieu_provenance', 'label' => 'Précisez le lieu de provenance', 'valeur' => $v('lieu_provenance'), 'max' => 150, 'requis' => true, 'placeholder' => 'Ville, pays', 'erreur' => 'Précisez le lieu de provenance.'])</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ================= ÉTAPE 2 : Parents et tuteur ================= --}}
      <div data-etape="2" hidden>
        <div class="row g-3">
          @foreach (['mere' => ['titre' => 'Mère', 'vivant' => 'mere_vivante', 'question' => 'La mère est-elle vivante ?', 'icone' => 'bi-gender-female'],
                     'pere' => ['titre' => 'Père', 'vivant' => 'pere_vivant', 'question' => 'Le père est-il vivant ?', 'icone' => 'bi-gender-male']] as $parent => $p)
            <div class="col-lg-6">
              <div class="oev-bloc">
                <div class="oev-bloc-titre"><i class="bi {{ $p['icone'] }}" aria-hidden="true"></i> {{ $p['titre'] }}</div>
                <div class="row g-3">
                  {{-- Obligatoires quand ce parent s'occupe de l'enfant : ils sont repris comme nom du tuteur --}}
                  <div class="col-sm-5">@include('oevs.champs._texte', ['nom' => "{$parent}_nom", 'label' => 'Nom de famille', 'valeur' => $v("{$parent}_nom"), 'max' => 100, 'requisSi' => "tuteur_lien:{$parent}|parents", 'erreur' => 'Obligatoire : ce parent s’occupe de l’enfant.'])</div>
                  <div class="col-sm-7">@include('oevs.champs._texte', ['nom' => "{$parent}_prenoms", 'label' => 'Prénoms', 'valeur' => $v("{$parent}_prenoms"), 'max' => 150, 'requisSi' => "tuteur_lien:{$parent}|parents", 'erreur' => 'Obligatoire : ce parent s’occupe de l’enfant.'])</div>
                  <div class="col-12">@include('oevs.champs._choix', ['nom' => $p['vivant'], 'label' => $p['question'], 'options' => Oev::PARENT_VIVANT, 'valeur' => $v($p['vivant']), 'requis' => true])</div>
                  <div class="col-12" data-si="{{ $p['vivant'] }}:non">
                    <div class="row g-3 oev-sous-bloc">
                      <div class="col-sm-6">
                        @include('oevs.champs._texte', ['nom' => "{$parent}_date_deces", 'label' => 'Date du décès', 'valeur' => $vDate("{$parent}_date_deces"), 'type' => 'date', 'attributs' => 'max="' . now()->toDateString() . '" data-deces="' . $parent . '"', 'erreur' => $parent === 'mere' ? 'La mère ne peut pas être décédée avant la naissance de l’enfant.' : 'Le père ne peut pas être décédé plus de 9 mois avant la naissance de l’enfant.'])
                      </div>
                      <div class="col-sm-6">@include('oevs.champs._choix', ['nom' => "{$parent}_deces_confirme", 'label' => 'Décès confirmé par une autre source que l’enfant ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool("{$parent}_deces_confirme")])</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endforeach

          <div class="col-12">
            <div class="oev-statut-calcule">
              <i class="bi bi-magic text-primary" aria-hidden="true"></i>
              <span class="small text-muted">Statut OEV déterminé automatiquement :</span>
              <span class="oev-statut" data-statut-calcule>—</span>
            </div>
          </div>

          <div class="col-12">
            <div class="oev-bloc">
              {{-- Titre adapté aux parents encore en vie : « Père ou tuteur… », « Mère ou tuteur… » ou « Tuteur… » --}}
              <div class="oev-bloc-titre"><i class="bi bi-person-heart" aria-hidden="true"></i> <span data-titre-tuteur>Parent ou tuteur qui s’occupe de l’enfant</span></div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label" for="tuteur_lien">Qui s’occupe de l’enfant ? <span class="text-danger">*</span></label>
                  <select class="form-select @error('tuteur_lien') is-invalid @enderror" id="tuteur_lien" name="tuteur_lien" required>
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::LIENS_TUTEUR as $cle => $label)
                      {{-- Les parents ne sont proposés que s'ils sont déclarés vivants --}}
                      <option value="{{ $cle }}" @selected($v('tuteur_lien') === $cle)
                        @if ($cle === 'mere') data-masquer-si="mere_vivante!:oui" @elseif ($cle === 'pere') data-masquer-si="pere_vivant!:oui" @elseif ($cle === 'parents') data-masquer-si="mere_vivante!:oui||pere_vivant!:oui" @endif>{{ $label }}</option>
                    @endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('tuteur_lien') ?: 'Indiquez qui s’occupe de l’enfant.' }}</div>
                </div>
                <div class="col-md-4" data-si="tuteur_lien:autre_parent">@include('oevs.champs._texte', ['nom' => 'tuteur_lien_precision', 'label' => 'Quel membre de la famille ?', 'valeur' => $v('tuteur_lien_precision'), 'max' => 100, 'requis' => true, 'placeholder' => 'ex : cousin, belle-mère…', 'erreur' => 'Précisez le lien.'])</div>

                {{-- Parent(s) : identité déjà saisie plus haut, simplement rappelée --}}
                <div class="col-md-8" data-si="tuteur_lien:parents|mere|pere">
                  <span class="form-label d-block">Nom et prénoms</span>
                  <div class="oev-note align-items-center">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <strong class="text-body" data-rappel-tuteur>—</strong>
                  </div>
                </div>

                {{-- Tuteur autre qu'un parent : identité à saisir --}}
                <div class="col-sm-5 col-md-4" data-si="tuteur_lien!:parents|mere|pere">@include('oevs.champs._texte', ['nom' => 'nom_tuteur', 'label' => 'Nom du tuteur', 'valeur' => $v('nom_tuteur'), 'max' => 100, 'requis' => true, 'erreur' => 'Le nom est obligatoire.'])</div>
                <div class="col-sm-7 col-md-4" data-si="tuteur_lien!:parents|mere|pere">@include('oevs.champs._texte', ['nom' => 'prenom_tuteur', 'label' => 'Prénom(s) du tuteur', 'valeur' => $v('prenom_tuteur'), 'max' => 150, 'requis' => true, 'erreur' => 'Le(s) prénom(s) sont obligatoires.'])</div>
                <div class="col-md-4" data-si="tuteur_lien!:parents|mere|pere">@include('oevs.champs._choix', ['nom' => 'tuteur_sexe', 'label' => 'Sexe du tuteur', 'options' => ['M' => 'Homme', 'F' => 'Femme'], 'valeur' => $v('tuteur_sexe'), 'requis' => true])</div>

                <div class="col-md-4">
                  <label class="form-label" for="contact_tuteur">Téléphone du parent / tuteur <span class="text-danger">*</span></label>
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
                           placeholder="70 12 34 56" pattern="\d{2} \d{2} \d{2} \d{2}" required>
                    <div class="invalid-feedback">{{ $errors->first('contact_tuteur') ?: 'Saisissez les 8 chiffres du numéro (ex : 70 12 34 56).' }}</div>
                  </div>
                </div>
                <div class="col-md-4">@include('oevs.champs._choix', ['nom' => 'tuteur_a_cnib', 'label' => 'Possède une CNIB ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('tuteur_a_cnib'), 'requis' => true, 'aide' => 'Pour les deux parents : celle du parent référent. Détermine si la CNIB est demandée dans les pièces.'])</div>
                <div class="col-md-4" data-si="tuteur_a_cnib:1">@include('oevs.champs._texte', ['nom' => 'tuteur_cnib', 'label' => 'N° CNIB', 'valeur' => $v('tuteur_cnib'), 'max' => 50, 'requis' => true, 'erreur' => 'Saisissez le numéro de CNIB.'])</div>
                <div class="col-md-4">@include('oevs.champs._choix', ['nom' => 'tuteur_pret_continuer', 'label' => 'Prêt à continuer à s’occuper de l’enfant ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('tuteur_pret_continuer'), 'requis' => true])</div>
                <div class="col-md-8" data-si="tuteur_pret_continuer:0">@include('oevs.champs._texte', ['nom' => 'tuteur_raison_arret', 'label' => 'Pourquoi ne peut-il (elle) pas continuer ?', 'valeur' => $v('tuteur_raison_arret'), 'max' => 255, 'requis' => true, 'placeholder' => 'ex : manque de moyens, maladie, départ…', 'erreur' => 'Indiquez la raison.'])</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ================= ÉTAPE 3 : Situation et santé ================= --}}
      <div data-etape="3" hidden>
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-house-heart" aria-hidden="true"></i> Conditions de vie</div>
              <div class="row g-3">
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'lieu_de_vie', 'label' => 'Où vit l’enfant ?', 'options' => Oev::LIEUX_DE_VIE, 'valeur' => $v('lieu_de_vie'), 'mode' => 'liste'])</div>
                <div class="col-12" data-si="lieu_de_vie:autre">@include('oevs.champs._texte', ['nom' => 'lieu_de_vie_precision', 'label' => 'Précisez le lieu de vie', 'valeur' => $v('lieu_de_vie_precision'), 'max' => 150, 'requis' => true, 'erreur' => 'Précisez le lieu de vie.'])</div>
                <div class="col-12">
                  @include('oevs.champs._cases', ['nom' => 'vulnerabilites', 'label' => 'Situations de vulnérabilité', 'options' => Oev::VULNERABILITES, 'valeurs' => $vListe('vulnerabilites'),
                    'automatiques' => ['orphelin' => 'mere_vivante:non||pere_vivant:non', 'handicap' => 'handicap:1', 'sans_acte_naissance' => 'a_acte_naissance:0'],
                    'conditions' => ['grossesse' => 'sexe:F']])
                </div>
                <div class="col-12" data-si="vulnerabilites[]:autre">@include('oevs.champs._texte', ['nom' => 'vulnerabilite_precision', 'label' => 'Autre situation (précisez)', 'valeur' => $v('vulnerabilite_precision'), 'max' => 255, 'requis' => true, 'erreur' => 'Précisez la situation.'])</div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Santé</div>
              <div class="row g-3">
                <div class="col-sm-6">@include('oevs.champs._choix', ['nom' => 'handicap', 'label' => 'Enfant en situation de handicap ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('handicap') ?? ($edition ? '0' : null), 'requis' => true])</div>
                <div class="col-sm-6">@include('oevs.champs._choix', ['nom' => 'maladie_chronique', 'label' => 'L’enfant souffre-t-il d’une maladie ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('maladie_chronique')])</div>
                <div class="col-12" data-si="handicap:1">
                  <div class="row g-3 oev-sous-bloc">
                    <div class="col-12">@include('oevs.champs._cases', ['nom' => 'types_handicap', 'label' => 'Type de handicap', 'options' => Oev::TYPES_HANDICAP, 'valeurs' => $vListe('types_handicap'), 'requis' => true])</div>
                    <div class="col-12">@include('oevs.champs._texte', ['nom' => 'nature_handicap', 'label' => 'Détails sur le handicap', 'valeur' => $v('nature_handicap'), 'max' => 255])</div>
                  </div>
                </div>
                <div class="col-12" data-si="maladie_chronique:1">
                  <div class="row g-3 oev-sous-bloc">
                    <div class="col-sm-7">@include('oevs.champs._texte', ['nom' => 'maladie_nom', 'label' => 'Nom de la maladie', 'valeur' => $v('maladie_nom'), 'max' => 150, 'requis' => true, 'placeholder' => 'ex : drépanocytose, asthme, VIH…', 'erreur' => 'Indiquez le nom de la maladie.'])</div>
                    <div class="col-sm-5">@include('oevs.champs._choix', ['nom' => 'suivi_clinique', 'label' => 'Suivi clinique ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('suivi_clinique'), 'requis' => true])</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-wallet2" aria-hidden="true"></i> Situation du ménage</div>
              <div class="row g-3">
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'source_revenu', 'label' => 'Source du revenu', 'options' => Oev::SOURCES_REVENU, 'valeur' => $v('source_revenu'), 'mode' => 'liste',
                  'masquerSi' => array_fill_keys(Oev::SOURCES_REVENU_PARENTS_VIVANTS, 'mere_vivante:non&&pere_vivant:non')])</div>
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'niveau_revenu', 'label' => 'Niveau du revenu', 'options' => Oev::NIVEAUX, 'valeur' => $v('niveau_revenu')])</div>
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'logement', 'label' => 'Logement', 'options' => Oev::LOGEMENTS, 'valeur' => $v('logement'), 'mode' => 'liste'])</div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-clipboard-data" aria-hidden="true"></i> Identification du cas</div>
              <div class="row g-3">
                <div class="col-sm-6">@include('oevs.champs._texte', ['nom' => 'date_identification', 'label' => 'Date d’identification', 'valeur' => $vDate('date_identification'), 'type' => 'date', 'attributs' => 'max="' . now()->toDateString() . '" data-apres-naissance', 'erreur' => 'Entre la naissance de l’enfant et aujourd’hui.'])</div>
                <div class="col-sm-6">@include('oevs.champs._choix', ['nom' => 'identifie_par', 'label' => 'Qui a identifié l’enfant ?', 'options' => Oev::IDENTIFIE_PAR, 'valeur' => $v('identifie_par'), 'mode' => 'liste'])</div>
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'niveau_priorite', 'label' => 'Niveau de priorité du cas', 'options' => Oev::NIVEAUX, 'valeur' => $v('niveau_priorite')])</div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-person-badge" aria-hidden="true"></i> Gestionnaire du cas</div>
              <div class="row g-3">
                <div class="col-md-4">@include('oevs.champs._texte', ['nom' => 'gestionnaire_nom', 'label' => 'Nom et prénom(s)', 'valeur' => $gestionnaireNom, 'max' => 150, 'placeholder' => 'Agent qui suit l’enfant'])</div>
                <div class="col-md-4">@include('oevs.champs._texte', ['nom' => 'gestionnaire_fonction', 'label' => 'Fonction / structure', 'valeur' => $v('gestionnaire_fonction'), 'max' => 150, 'placeholder' => 'ex : travailleur social, service social de…'])</div>
                <div class="col-md-4">
                  <label class="form-label" for="gestionnaire_contact">Téléphone</label>
                  <div class="input-group">
                    <span class="input-group-text">{{ Oev::INDICATIF }}</span>
                    <input class="form-control @error('gestionnaire_contact') is-invalid @enderror" id="gestionnaire_contact" name="gestionnaire_contact" type="tel" inputmode="numeric" data-telephone
                           value="{{ $v('gestionnaire_contact') ? implode(' ', str_split(Oev::numeroLocal($v('gestionnaire_contact')), 2)) : '' }}"
                           placeholder="70 12 34 56" pattern="\d{2} \d{2} \d{2} \d{2}">
                    <div class="invalid-feedback">{{ $errors->first('gestionnaire_contact') ?: 'Saisissez les 8 chiffres du numéro (ex : 70 12 34 56).' }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ================= ÉTAPE 4 : Scolarité ================= --}}
      <div data-etape="4" hidden>
        <div class="row g-3">
          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-backpack" aria-hidden="true"></i> Situation scolaire</div>
              <div class="row g-3">
                <div class="col-12">@include('oevs.champs._choix', ['nom' => 'situation_scolaire', 'label' => 'Scolarisation de l’enfant', 'options' => Oev::SITUATIONS_SCOLAIRES, 'valeur' => $v('situation_scolaire'), 'requis' => true])</div>
                <div class="col-md-4" data-si="situation_scolaire:scolarise|descolarise">
                  <label class="form-label" for="niveau_etude"><span data-si="situation_scolaire:scolarise">Niveau d’étude actuel</span><span data-si="situation_scolaire:descolarise">Dernier niveau d’étude</span> <span class="text-danger">*</span></label>
                  <select class="form-select @error('niveau_etude') is-invalid @enderror" id="niveau_etude" name="niveau_etude" required>
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::NIVEAUX_ETUDE as $cle => $label)<option value="{{ $cle }}" @selected($v('niveau_etude') === $cle)>{{ $label }}</option>@endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('niveau_etude') ?: 'Indiquez le niveau d’étude.' }}</div>
                </div>
                <div class="col-md-4" data-si="situation_scolaire:non_scolarise|descolarise">
                  <label class="form-label" for="raison_non_scolarisation">Raison principale <span class="text-danger">*</span></label>
                  <select class="form-select @error('raison_non_scolarisation') is-invalid @enderror" id="raison_non_scolarisation" name="raison_non_scolarisation" required>
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::RAISONS_NON_SCOLARISATION as $cle => $label)<option value="{{ $cle }}" @selected($v('raison_non_scolarisation') === $cle)>{{ $label }}</option>@endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('raison_non_scolarisation') ?: 'Indiquez pourquoi l’enfant n’est pas scolarisé.' }}</div>
                </div>
                <div class="col-md-4" data-si="situation_scolaire:non_scolarise|descolarise&&raison_non_scolarisation:autre">@include('oevs.champs._texte', ['nom' => 'raison_non_scolarisation_precision', 'label' => 'Précisez la raison', 'valeur' => $v('raison_non_scolarisation_precision'), 'max' => 255, 'requis' => true, 'erreur' => 'Précisez la raison.'])</div>
              </div>
            </div>
          </div>

          <div class="col-12" data-si="situation_scolaire!:non_scolarise">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-clock-history" aria-hidden="true"></i> Année précédente</div>
              <div class="row g-3">
                <div class="col-md-6">@include('oevs.champs._texte', ['nom' => 'etablissement_precedent', 'label' => 'Établissement fréquenté', 'valeur' => $v('etablissement_precedent'), 'max' => 255, 'placeholder' => 'La classe, la moyenne et l’appréciation seront demandées ensuite'])</div>
                <div class="col-md-3" data-si="etablissement_precedent:*">
                  <label class="form-label" for="classe_precedente">Classe <span class="text-danger" data-si="moyenne_annuelle:*">*</span></label>
                  <select class="form-select @error('classe_precedente') is-invalid @enderror" id="classe_precedente" name="classe_precedente">
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::CLASSES as $niveau => $classes)
                      <optgroup label="{{ Oev::NIVEAUX_ETUDE[$niveau] }}">
                        @foreach ($classes as $cle => $label)<option value="{{ $cle }}" data-rang="{{ Oev::rangClasse($cle) }}" @selected($v('classe_precedente') === $cle)>{{ $label }}</option>@endforeach
                      </optgroup>
                    @endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('classe_precedente') ?: 'Indiquez la classe (elle fixe le barème de la moyenne).' }}</div>
                </div>
                <div class="col-md-3" data-si="etablissement_precedent:*">
                  <label class="form-label" for="moyenne_annuelle">Moyenne annuelle obtenue</label>
                  <div class="input-group">
                    <input class="form-control @error('moyenne_annuelle') is-invalid @enderror" id="moyenne_annuelle" name="moyenne_annuelle" type="number" step="0.01" min="0" max="20" value="{{ $v('moyenne_annuelle') }}">
                    <span class="input-group-text" data-bareme>/ 20</span>
                    <div class="invalid-feedback" data-bareme-erreur>{{ $errors->first('moyenne_annuelle') ?: 'Moyenne entre 0 et 20.' }}</div>
                  </div>
                </div>
                <div class="col-md-6" data-si="etablissement_precedent:*">
                  <span class="form-label d-block">Appréciation</span>
                  <div class="oev-choix" role="radiogroup" aria-label="Appréciation">
                    @foreach (Oev::APPRECIATIONS as $cle => $label)
                      <input class="btn-check" type="radio" name="appreciation" id="appreciation-{{ $cle }}" value="{{ $cle }}" @checked($v('appreciation') === $cle) data-libelle="{{ $label }}">
                      <label class="btn btn-outline-{{ $cle === 'admis' ? 'success' : ($cle === 'redouble' ? 'warning' : 'danger') }}" for="appreciation-{{ $cle }}">{{ $label }}</label>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12" data-si="situation_scolaire:scolarise">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-mortarboard" aria-hidden="true"></i> Année en cours</div>
              <div class="row g-3">
                <div class="col-md-6">@include('oevs.champs._texte', ['nom' => 'etablissement_actuel', 'label' => 'Établissement fréquenté', 'valeur' => $v('etablissement_actuel'), 'max' => 255, 'requis' => true, 'erreur' => 'L’établissement est obligatoire.'])</div>
                <div class="col-md-6">@include('oevs.champs._choix', ['nom' => 'type_etablissement', 'label' => 'Public ou privé', 'options' => Oev::TYPES_ETABLISSEMENT, 'valeur' => $v('type_etablissement'), 'requis' => true])</div>
                <div class="col-md-4">
                  <label class="form-label" for="systeme_educatif">Système éducatif <span class="text-danger">*</span></label>
                  <select class="form-select @error('systeme_educatif') is-invalid @enderror" id="systeme_educatif" name="systeme_educatif" required>
                    <option value="">Sélectionner…</option>
                    @foreach (Oev::SYSTEMES_EDUCATIFS as $cle => $label)<option value="{{ $cle }}" @selected($v('systeme_educatif') === $cle)>{{ $label }}</option>@endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('systeme_educatif') ?: 'Choisissez le système éducatif.' }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="classe">Classe <span class="text-danger">*</span></label>
                  <select class="form-select @error('classe') is-invalid @enderror" id="classe" name="classe" required>
                    <option value="" data-invite>Choisissez d’abord le niveau…</option>
                    @foreach (Oev::CLASSES as $niveau => $classes)
                      @foreach ($classes as $cle => $label)
                        <option value="{{ $cle }}" data-niveau="{{ $niveau }}" data-rang="{{ Oev::rangClasse($cle) }}" @selected($v('classe') === $cle && $v('niveau_etude') === $niveau)>{{ $label }}</option>
                      @endforeach
                    @endforeach
                  </select>
                  <div class="invalid-feedback">{{ $errors->first('classe') ?: 'Choisissez la classe correspondant au niveau (pas en dessous de celle de l’année précédente).' }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="frais_scolarite">Frais de scolarité</label>
                  <div class="input-group">
                    <input class="form-control @error('frais_scolarite') is-invalid @enderror" id="frais_scolarite" name="frais_scolarite" type="number" min="0" step="1" value="{{ $v('frais_scolarite') }}">
                    <span class="input-group-text">FCFA</span>
                    <div class="invalid-feedback">{{ $errors->first('frais_scolarite') ?: 'Montant invalide.' }}</div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="row g-3 oev-sous-bloc">
                    <div class="col-lg-7">
                      <span class="form-label d-block" id="performance_scolaire-label">Performances scolaires <span class="text-danger">*</span></span>
                      <div class="oev-choix" role="radiogroup" aria-labelledby="performance_scolaire-label">
                        @foreach (Oev::PERFORMANCES_SCOLAIRES as $cle => $label)
                          <input class="btn-check" type="radio" name="performance_scolaire" id="performance_scolaire-{{ $cle }}" value="{{ $cle }}" @checked($v('performance_scolaire') === $cle) required data-libelle="{{ $label }}">
                          <label class="btn btn-outline-{{ ['bonnes' => 'success', 'passables' => 'primary', 'faibles' => 'warning', 'irregulieres' => 'warning', 'difficultes' => 'danger'][$cle] }}" for="performance_scolaire-{{ $cle }}">{{ $label }}</label>
                        @endforeach
                      </div>
                      <div class="text-danger small mt-1" data-erreur-groupe="performance_scolaire" hidden>Ce choix est obligatoire.</div>
                      @error('performance_scolaire')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-5" data-si="performance_scolaire:difficultes">@include('oevs.champs._texte', ['nom' => 'performance_difficultes', 'label' => 'Expliquez les difficultés scolaires', 'valeur' => $v('performance_difficultes'), 'max' => 255, 'requis' => true, 'placeholder' => 'ex : difficultés en lecture, absences…', 'erreur' => 'Expliquez les difficultés.'])</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="oev-bloc">
              <div class="oev-bloc-titre"><i class="bi bi-tools" aria-hidden="true"></i> Formation professionnelle</div>
              <div class="row g-3">
                <div class="col-md-4">@include('oevs.champs._choix', ['nom' => 'formation_professionnelle', 'label' => 'L’enfant suit-il une formation professionnelle ?', 'options' => Oev::OUI_NON, 'valeur' => $vBool('formation_professionnelle')])</div>
                <div class="col-md-8" data-si="formation_professionnelle:1">
                  <div class="row g-3">
                    <div class="col-sm-4">@include('oevs.champs._choix', ['nom' => 'formation_etat', 'label' => 'État', 'options' => Oev::ETATS_FORMATION, 'valeur' => $v('formation_etat'), 'requis' => true])</div>
                    <div class="col-sm-4">@include('oevs.champs._texte', ['nom' => 'formation_filiere', 'label' => 'Filière de formation', 'valeur' => $v('formation_filiere'), 'max' => 150, 'requis' => true, 'placeholder' => 'ex : couture, mécanique…', 'erreur' => 'Indiquez la filière.'])</div>
                    <div class="col-sm-4">@include('oevs.champs._choix', ['nom' => 'formation_type_centre', 'label' => 'Type de centre', 'options' => Oev::TYPES_ETABLISSEMENT, 'valeur' => $v('formation_type_centre'), 'requis' => true])</div>
                    <div class="col-sm-4">
                      <label class="form-label" for="formation_duree_mois">Durée de la formation <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input class="form-control @error('formation_duree_mois') is-invalid @enderror" id="formation_duree_mois" name="formation_duree_mois" type="number" min="1" max="120" step="1" value="{{ $v('formation_duree_mois') }}" required>
                        <span class="input-group-text">mois</span>
                        <div class="invalid-feedback">{{ $errors->first('formation_duree_mois') ?: 'Durée entre 1 et 120 mois.' }}</div>
                      </div>
                    </div>
                    <div class="col-sm-4" data-si="formation_etat:en_cours">
                      <label class="form-label" for="formation_duree_recue_mois">Durée déjà reçue <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input class="form-control @error('formation_duree_recue_mois') is-invalid @enderror" id="formation_duree_recue_mois" name="formation_duree_recue_mois" type="number" min="0" step="1" value="{{ $v('formation_duree_recue_mois') }}" required>
                        <span class="input-group-text">mois</span>
                        <div class="invalid-feedback">{{ $errors->first('formation_duree_recue_mois') ?: 'Ne peut pas dépasser la durée de la formation.' }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ================= ÉTAPE 5 : Pièces du dossier ================= --}}
      <div data-etape="5" hidden>
        <p class="text-muted mb-3">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          Seules les pièces qui s’appliquent à l’enfant sont demandées. Cliquez sur une carte (ou glissez-y un fichier) : PDF, JPG ou PNG,
          <strong>{{ $enMo($maxFichiersKo['acte_naissance']) }} max par fichier</strong> (photo : JPG ou PNG, {{ $enMo($maxFichiersKo['photo']) }} max),
          <strong>{{ $enMo($maxEnvoiKo) }} au total</strong>. Les pièces peuvent être ajoutées plus tard, mais le dossier doit être complet pour être soumis au DR.
        </p>
        <div class="alert alert-danger py-2" data-alerte-taille hidden></div>
        <div class="row g-3">
          @foreach (Oev::DOCUMENTS as $type => $libelle)
            @php
              $existant = $documents->get($type);
            @endphp
            <div class="col-sm-6 col-xl-4" @isset($conditionsPieces[$type]) data-si="{{ $conditionsPieces[$type] }}" @endisset data-carte-piece="{{ $type }}">
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
              <input class="form-control @error('nom_structure_rib') is-invalid @enderror" id="nom_structure_rib" name="nom_structure_rib" value="{{ $v('nom_structure_rib') }}" maxlength="255" placeholder="Obligatoire si un RIB est chargé">
              <div class="invalid-feedback">Précisez le nom de la structure titulaire du RIB.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ================= ÉTAPE 6 : Récapitulatif ================= --}}
      <div data-etape="6" hidden>
        <div class="alert alert-info d-flex gap-2 align-items-center">
          <i class="bi bi-eye fs-5" aria-hidden="true"></i>
          <span>Vérifiez les informations ci-dessous. Utilisez « Modifier » pour revenir sur une étape. Les rubriques sans réponse ne sont pas affichées.</span>
        </div>
        @php
          // [titre, icône, étape, [ [libellé, champ, format?, condition?] … ]] — conditions : mêmes règles que le formulaire
          $recap = [
            ['Enfant', 'bi-person', 1, [
              ['Nom', 'nom'], ['Prénom(s)', 'prenom'], ['Sexe', 'sexe'], ['Date de naissance', 'date_naissance', 'date'], ['Date estimée', 'date_naissance_estimee'],
              ['Lieu de naissance', 'lieu_naissance'],
              ['Nationalité', 'nationalite'], ['Acte de naissance', 'a_acte_naissance'],
              ['N° de l’acte', 'numero_acte_naissance', null, 'a_acte_naissance:1'], ['Groupe de population', 'groupe_population'],
            ]],
            ['Adresse', 'bi-geo-alt', 1, [
              ['Région', 'region_id'], ['Province', 'province_id'], ['Commune', 'commune_id'], ['Village / secteur', 'village_id'], ['Quartier / précision', 'quartier'],
              ['Lieu de provenance', 'lieu_provenance_commune_id', null, 'groupe_population:' . $groupesMobiles],
              ['Précision de la provenance', 'lieu_provenance', null, 'groupe_population:' . $groupesMobiles . '&&lieu_provenance_commune_id:autre'],
            ]],
            ['Parents', 'bi-diagram-3', 2, [
              ['Mère', 'mere_nom'], ['Prénoms de la mère', 'mere_prenoms'], ['Mère vivante', 'mere_vivante'],
              ['Décès de la mère', 'mere_date_deces', 'date_simple', 'mere_vivante:non'], ['Décès confirmé', 'mere_deces_confirme', null, 'mere_vivante:non'],
              ['Père', 'pere_nom'], ['Prénoms du père', 'pere_prenoms'], ['Père vivant', 'pere_vivant'],
              ['Décès du père', 'pere_date_deces', 'date_simple', 'pere_vivant:non'], ['Décès confirmé', 'pere_deces_confirme', null, 'pere_vivant:non'],
            ]],
            ['Parent / tuteur', 'bi-person-heart', 2, [
              ['Qui s’occupe de l’enfant', 'tuteur_lien'], ['Précision', 'tuteur_lien_precision', null, 'tuteur_lien:autre_parent'],
              ['Nom', 'nom_tuteur', null, 'tuteur_lien!:parents|mere|pere'], ['Prénom(s)', 'prenom_tuteur', null, 'tuteur_lien!:parents|mere|pere'],
              ['Sexe', 'tuteur_sexe', null, 'tuteur_lien!:parents|mere|pere'], ['Téléphone', 'contact_tuteur', 'telephone'],
              ['Possède une CNIB', 'tuteur_a_cnib'], ['N° CNIB', 'tuteur_cnib', null, 'tuteur_a_cnib:1'], ['Prêt à continuer', 'tuteur_pret_continuer'],
              ['Raison', 'tuteur_raison_arret', null, 'tuteur_pret_continuer:0'],
            ]],
            ['Situation et santé', 'bi-house-heart', 3, [
              ['Lieu de vie', 'lieu_de_vie'], ['Précision', 'lieu_de_vie_precision', null, 'lieu_de_vie:autre'],
              ['Vulnérabilités', 'vulnerabilites[]'], ['Autre vulnérabilité', 'vulnerabilite_precision', null, 'vulnerabilites[]:autre'],
              ['Handicap', 'handicap'], ['Type de handicap', 'types_handicap[]', null, 'handicap:1'], ['Détails du handicap', 'nature_handicap', null, 'handicap:1'],
              ['Maladie', 'maladie_chronique'], ['Nom de la maladie', 'maladie_nom', null, 'maladie_chronique:1'], ['Suivi clinique', 'suivi_clinique', null, 'maladie_chronique:1'],
              ['Source de revenu', 'source_revenu'], ['Niveau de revenu', 'niveau_revenu'], ['Logement', 'logement'],
              ['Date d’identification', 'date_identification', 'date_simple'], ['Identifié par', 'identifie_par'], ['Priorité', 'niveau_priorite'],
              ['Gestionnaire du cas', 'gestionnaire_nom'], ['Fonction / structure', 'gestionnaire_fonction'], ['Tél. du gestionnaire', 'gestionnaire_contact', 'telephone'],
            ]],
            ['Scolarité', 'bi-mortarboard', 4, [
              ['Situation scolaire', 'situation_scolaire'], ['Niveau d’étude', 'niveau_etude', null, 'situation_scolaire:scolarise|descolarise'],
              ['Raison', 'raison_non_scolarisation', null, 'situation_scolaire:non_scolarise|descolarise'],
              ['Précision', 'raison_non_scolarisation_precision', null, 'situation_scolaire:non_scolarise|descolarise&&raison_non_scolarisation:autre'],
              ['Établ. précédent', 'etablissement_precedent', null, 'situation_scolaire!:non_scolarise'],
              ['Classe précédente', 'classe_precedente', null, 'situation_scolaire!:non_scolarise&&etablissement_precedent:*'],
              ['Moyenne annuelle', 'moyenne_annuelle', 'moyenne', 'situation_scolaire!:non_scolarise&&etablissement_precedent:*'],
              ['Appréciation', 'appreciation', null, 'situation_scolaire!:non_scolarise&&etablissement_precedent:*'],
              ['Établ. en cours', 'etablissement_actuel', null, 'situation_scolaire:scolarise'], ['Public / privé', 'type_etablissement', null, 'situation_scolaire:scolarise'],
              ['Système éducatif', 'systeme_educatif', null, 'situation_scolaire:scolarise'], ['Classe', 'classe', null, 'situation_scolaire:scolarise'],
              ['Frais de scolarité', 'frais_scolarite', 'fcfa', 'situation_scolaire:scolarise'],
              ['Performances', 'performance_scolaire', null, 'situation_scolaire:scolarise'],
              ['Difficultés', 'performance_difficultes', null, 'situation_scolaire:scolarise&&performance_scolaire:difficultes'],
              ['Formation professionnelle', 'formation_professionnelle'], ['État', 'formation_etat', null, 'formation_professionnelle:1'],
              ['Filière', 'formation_filiere', null, 'formation_professionnelle:1'], ['Type de centre', 'formation_type_centre', null, 'formation_professionnelle:1'],
              ['Durée', 'formation_duree_mois', 'mois', 'formation_professionnelle:1'], ['Durée déjà reçue', 'formation_duree_recue_mois', 'mois', 'formation_professionnelle:1&&formation_etat:en_cours'],
            ]],
          ];
        @endphp
        <div class="row g-3">
          @foreach ($recap as [$titre, $icone, $etape, $lignes])
            <div class="col-lg-6">
              <div class="oev-recap-bloc">
                <h3><span><i class="bi {{ $icone }} me-1" aria-hidden="true"></i> {{ $titre }}</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="{{ $etape }}">Modifier</button></h3>
                <dl>
                  @foreach ($lignes as $ligne)
                    <div class="d-contents" data-ligne-recap @isset($ligne[3]) data-condition="{{ $ligne[3] }}" @endisset>
                      <dt>{{ $ligne[0] }}</dt><dd data-r="{{ $ligne[1] }}" @if ($ligne[2] ?? null) data-format="{{ $ligne[2] }}" @endif></dd>
                    </div>
                  @endforeach
                </dl>
              </div>
            </div>
          @endforeach
          <div class="col-lg-6">
            <div class="oev-recap-bloc">
              <h3><span><i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Pièces du dossier</span><button type="button" class="btn btn-link btn-sm p-0" data-aller="{{ $etapeFichiers }}">Modifier</button></h3>
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
        {{-- « hidden » sur l'enveloppe : une classe d-flex sur le même élément l'emporterait sur l'attribut --}}
        <span data-enregistrer hidden>
          <span class="d-flex flex-wrap gap-2">
            <input type="hidden" name="soumettre" value="0" data-champ-soumettre>
            <button type="submit" class="btn {{ $peutSoumettre ? 'btn-outline-success' : 'btn-success' }} px-4"><i class="bi bi-save" aria-hidden="true"></i> {{ $edition ? 'Enregistrer les modifications' : 'Enregistrer le dossier' }}</button>
            @if ($peutSoumettre)
              <button type="submit" class="btn btn-success px-4" data-avec-soumission title="Le dossier doit comporter toutes les pièces demandées"><i class="bi bi-send-check" aria-hidden="true"></i> Enregistrer et soumettre au DR</button>
            @endif
          </span>
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
  var total = {{ count($etapes) }};
  var etapeFichiers = {{ $etapeFichiers }};
  var courante = 1;
  var atteinte = 1;
  var pieces = @json(Oev::DOCUMENTS);
  var statuts = @json(Oev::STATUTS);

  /* ----- Limites de taille des fichiers (en Ko, fournies par le serveur) ----- */
  var limites = { fichiers: @json($maxFichiersKo), envoi: {{ min($maxEnvoiKo, PHP_INT_MAX >> 11) }} };
  var zoneAlerteTaille = form.querySelector('[data-alerte-taille]');
  function enMo(ko) { return (ko / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' Mo'; }
  function alerteTaille(message) { zoneAlerteTaille.textContent = message; zoneAlerteTaille.hidden = !message; }

  var panneaux = form.querySelectorAll('[data-etape]');
  var puces = form.querySelectorAll('[data-stepper]');
  var btnPrec = form.querySelector('[data-precedent]');
  var btnSuiv = form.querySelector('[data-suivant]');
  var btnEnr = form.querySelector('[data-enregistrer]');
  var compteur = form.querySelector('[data-compteur]');

  /* ----- Lecture des valeurs du formulaire ----- */
  // Valeurs d'un champ (tableau) : cases cochées (y compris automatiques), bouton radio choisi, liste ou texte.
  function valeursDe(nom) {
    var champs = Array.prototype.filter.call(form.querySelectorAll('[name="' + nom + '"], [data-nom-auto="' + nom + '"]'), function (c) { return c.type !== 'hidden'; });
    if (!champs.length) { return []; }
    var c = champs[0];
    if (c.type === 'radio' || c.type === 'checkbox') {
      return champs.filter(function (x) { return x.checked; }).map(function (x) { return x.value; });
    }
    return c.value.trim() ? [c.value.trim()] : [];
  }

  /*
   * Conditions entre champs : « champ:v1|v2 » (une des valeurs), « champ!:v1|v2 » (aucune de ces valeurs),
   * « champ:* » (renseigné) ; « && » = toutes les conditions, « || » = au moins un groupe.
   */
  function conditionRemplie(expression) {
    return expression.split('||').some(function (groupe) {
      return groupe.split('&&').every(function (cond) {
        var negation = cond.indexOf('!:') !== -1;
        var morceaux = cond.split(negation ? '!:' : ':');
        var valeurs = valeursDe(morceaux[0].trim());
        var attendues = morceaux[1].split('|');
        var trouve = attendues[0] === '*' ? valeurs.length > 0 : valeurs.some(function (v) { return attendues.indexOf(v) !== -1; });
        return negation ? !trouve : trouve;
      });
    });
  }

  /* ----- Cohérence en direct : affichage conditionnel, cases automatiques, options interdites ----- */
  var champLien = document.getElementById('tuteur_lien');
  var champNiveau = document.getElementById('niveau_etude');
  var champClasse = document.getElementById('classe');
  var champMoyenne = document.getElementById('moyenne_annuelle');
  var rappelTuteur = form.querySelector('[data-rappel-tuteur]');
  var classesSur10 = @json($classesSur10);

  function majCoherence() {
    // Cases déduites des réponses (orphelin, handicap, absence d'acte)
    form.querySelectorAll('[data-auto]').forEach(function (c) { c.checked = conditionRemplie(c.dataset.auto); });
    // Blocs affichés selon les réponses (le calcul peut dépendre d'une case automatique)
    form.querySelectorAll('[data-si]').forEach(function (bloc) { bloc.hidden = !conditionRemplie(bloc.dataset.si); });
    // Une option masquée (ex. « Grossesse » pour un garçon) est décochée
    form.querySelectorAll('.oev-case[hidden] input:checked').forEach(function (c) { c.checked = false; });

    // Options retirées selon les réponses : parent non déclaré vivant comme tuteur, fonds propres sans parents…
    form.querySelectorAll('option[data-masquer-si]').forEach(function (o) {
      var masquee = conditionRemplie(o.dataset.masquerSi);
      o.hidden = masquee;
      o.disabled = masquee;
      if (masquee && o.selected) { o.parentElement.value = ''; }
    });

    // Titre du bloc tuteur selon les parents en vie
    var mereVivante = valeursDe('mere_vivante')[0] === 'oui', pereVivant = valeursDe('pere_vivant')[0] === 'oui';
    form.querySelector('[data-titre-tuteur]').textContent = (mereVivante && pereVivant ? 'Parent ou tuteur'
      : mereVivante ? 'Mère ou tuteur' : pereVivant ? 'Père ou tuteur' : 'Tuteur') + ' qui s’occupe de l’enfant';

    // Champs obligatoires selon une autre réponse (ex. nom de la mère si elle s'occupe de l'enfant)
    form.querySelectorAll('[data-requis-si]').forEach(function (c) { c.required = conditionRemplie(c.dataset.requisSi); });

    // Rappel du nom du ou des parents qui s'occupent de l'enfant
    var parentsTuteurs = { mere: ['mere'], pere: ['pere'], parents: ['pere', 'mere'] }[champLien.value] || [];
    rappelTuteur.textContent = parentsTuteurs.map(function (p) {
      var nomComplet = (document.getElementById(p + '_nom').value + ' ' + document.getElementById(p + '_prenoms').value).trim();
      return nomComplet || (p === 'mere' ? 'Nom de la mère à saisir plus haut' : 'Nom du père à saisir plus haut');
    }).join(' et ') || '—';

    // Barème de la moyenne selon la classe de l'année précédente : /10 (maternelle, primaire) ou /20
    var bareme = classesSur10.indexOf(document.getElementById('classe_precedente').value) !== -1 ? 10 : 20;
    champMoyenne.max = bareme;
    form.querySelector('[data-bareme]').textContent = '/ ' + bareme;
    form.querySelector('[data-bareme-erreur]').textContent = 'Moyenne entre 0 et ' + bareme + '.';
    // La classe est obligatoire dès qu'une moyenne est saisie (elle fixe le barème)
    document.getElementById('classe_precedente').required = champMoyenne.value.trim() !== '';

    // Formation : la durée déjà reçue ne peut pas dépasser la durée totale
    var dureeTotale = document.getElementById('formation_duree_mois').value;
    document.getElementById('formation_duree_recue_mois').max = dureeTotale || '';

    // Classes proposées selon le niveau d'étude, et jamais en dessous de la classe de l'année précédente
    // (ex. 5e l'an dernier : la 6e n'est pas proposée cette année)
    var niveau = champNiveau.value;
    var champClassePrec = document.getElementById('classe_precedente');
    var optionPrec = champClassePrec.options[champClassePrec.selectedIndex];
    var rangPrecedent = conditionRemplie('etablissement_precedent:*') && optionPrec && optionPrec.dataset.rang ? Number(optionPrec.dataset.rang) : null;
    Array.prototype.forEach.call(champClasse.options, function (o) {
      if (o.hasAttribute('data-invite')) { o.textContent = niveau ? 'Sélectionner…' : 'Choisissez d’abord le niveau…'; return; }
      var visible = o.dataset.niveau === niveau && !(rangPrecedent !== null && o.dataset.rang !== '' && Number(o.dataset.rang) < rangPrecedent);
      o.hidden = !visible;
      o.disabled = !visible;
      if (!visible && o.selected) { champClasse.value = ''; }
    });

    // Bornes des dates liées à la naissance
    var naissance = document.getElementById('date_naissance').value;
    var decesMere = document.getElementById('mere_date_deces');
    var decesPere = document.getElementById('pere_date_deces');
    var identification = document.getElementById('date_identification');
    decesMere.min = naissance || '';
    identification.min = naissance || '';
    if (naissance) {
      var d = new Date(naissance);
      d.setMonth(d.getMonth() - 9);
      decesPere.min = d.toISOString().slice(0, 10);
    } else {
      decesPere.min = '';
    }
  }
  form.addEventListener('change', majCoherence);
  // Champs dont la saisie modifie d'autres champs (affichage, barème, rappel du tuteur)
  var champsSuivis = ['etablissement_precedent', 'moyenne_annuelle', 'formation_duree_mois', 'mere_nom', 'mere_prenoms', 'pere_nom', 'pere_prenoms'];
  form.addEventListener('input', function (e) { if (champsSuivis.indexOf(e.target.id) !== -1) { majCoherence(); } });

  /* ----- Statut OEV déduit des parents (même règle que le serveur) ----- */
  var badgeStatut = form.querySelector('[data-statut-calcule]');
  function statutCalcule() {
    var mere = valeursDe('mere_vivante')[0], pere = valeursDe('pere_vivant')[0];
    if (!mere || !pere) { return null; }
    if (mere === 'non' && pere === 'non') { return 'orphelin_double'; }
    if (mere === 'non') { return 'orphelin_mere'; }
    if (pere === 'non') { return 'orphelin_pere'; }
    return 'vulnerable';
  }
  function majStatut() {
    var s = statutCalcule();
    badgeStatut.className = 'oev-statut' + (s ? ' oev-statut--' + s : '');
    badgeStatut.textContent = s ? statuts[s] : 'Répondez aux questions sur la mère et le père';
  }
  form.addEventListener('change', majStatut);

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

  /* ----- Téléphone : 8 chiffres groupés par deux (l'indicatif +226 est affiché à part) ----- */
  [document.getElementById('contact_tuteur'), document.getElementById('gestionnaire_contact')].forEach(function (champTel) {
    champTel.addEventListener('input', function () {
      var chiffres = champTel.value.replace(/\D/g, '');
      if (chiffres.length > 8 && chiffres.indexOf('226') === 0) { chiffres = chiffres.slice(3); }
      champTel.value = chiffres.slice(0, 8).replace(/(\d{2})(?=\d)/g, '$1 ');
    });
  });

  /* ----- Pièces : seules les cartes affichées (pièces demandées) comptent et sont envoyées ----- */
  function pieceDemandee(type) { return !form.querySelector('[data-carte-piece="' + type + '"]').hidden; }
  function tailleTotaleKo() {
    var octets = 0;
    Object.keys(pieces).forEach(function (type) {
      var f = document.getElementById(type).files[0];
      if (f && pieceDemandee(type)) { octets += f.size; }
    });
    return octets / 1024;
  }

  /* ----- RIB : nom de structure obligatoire si RIB fourni ----- */
  var nomStructure = document.getElementById('nom_structure_rib');
  function majRib() {
    var carteRib = form.querySelector('[data-piece="rib"]');
    var ribFourni = document.getElementById('rib').files.length > 0 || carteRib.dataset.existant;
    nomStructure.required = !!ribFourni;
    form.querySelector('[data-rib-requis]').hidden = !ribFourni;
  }

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

  /* ----- Validation d'une étape (les champs masqués sont ignorés) ----- */
  function visible(el) { return !el.closest('[hidden]'); }
  function erreurGroupe(nom, afficher) {
    var el = form.querySelector('[data-erreur-groupe="' + nom + '"]');
    if (el) { el.hidden = !afficher; }
  }
  function validerEtape(n) {
    var panneau = form.querySelector('[data-etape="' + n + '"]');
    var premier = null;
    Array.prototype.filter.call(panneau.querySelectorAll('input:not([type=file]):not([type=hidden]):not(:disabled), select, textarea'), visible).forEach(function (c) {
      var ok = c.checkValidity();
      if (c.type === 'radio') {
        form.querySelectorAll('input[name="' + c.name + '"] + label').forEach(function (l) { l.classList.toggle('border-danger', !ok); });
        erreurGroupe(c.name, !ok);
      } else if (c.type !== 'checkbox') {
        c.classList.toggle('is-invalid', !ok);
      }
      if (!ok && !premier) { premier = c; }
    });
    // Groupes de cases à cocher obligatoires (au moins une case)
    Array.prototype.filter.call(panneau.querySelectorAll('[data-groupe-requis]'), visible).forEach(function (g) {
      var ok = g.querySelectorAll('input:checked').length > 0;
      erreurGroupe(g.dataset.groupeRequis, !ok);
      if (!ok && !premier) { premier = g.querySelector('input'); }
    });
    if (premier) { premier.focus({ preventScroll: false }); }
    return !premier;
  }
  form.addEventListener('input', function (e) { if (e.target.classList.contains('is-invalid') && e.target.checkValidity()) { e.target.classList.remove('is-invalid'); } });
  form.addEventListener('change', function (e) {
    if (e.target.type === 'radio') {
      form.querySelectorAll('input[name="' + e.target.name + '"] + label').forEach(function (l) { l.classList.remove('border-danger'); });
      erreurGroupe(e.target.name, false);
    }
    if (e.target.type === 'checkbox') { erreurGroupe(e.target.name, false); }
  });

  /* ----- Récapitulatif ----- */
  function libelleRecap(nom) {
    var champs = Array.prototype.filter.call(form.querySelectorAll('[name="' + nom + '"], [data-nom-auto="' + nom + '"]'), function (c) { return c.type !== 'hidden'; });
    if (!champs.length) { return ''; }
    var c = champs[0];
    if (c.type === 'radio' || (c.type === 'checkbox' && /\[\]$/.test(nom))) {
      return champs.filter(function (x) { return x.checked; }).map(function (x) { return x.dataset.libelle || x.value; }).join(', ');
    }
    if (c.type === 'checkbox') { return c.checked ? 'Oui' : ''; }
    if (c.tagName === 'SELECT') { return c.value ? c.options[c.selectedIndex].text : ''; }
    return c.value.trim();
  }
  function construireRecap() {
    majCoherence();
    form.querySelectorAll('[data-r]').forEach(function (dd) {
      var val = libelleRecap(dd.dataset.r);
      var f = dd.dataset.format;
      if (val && f === 'fcfa') { val = Number(val).toLocaleString('fr-FR') + ' FCFA'; }
      else if (val && f === 'date') { val = val.split('-').reverse().join('/') + ' (' + ageDepuis(val) + ' ans)'; }
      else if (val && f === 'date_simple') { val = val.split('-').reverse().join('/'); }
      else if (val && f === 'moyenne') { val = Number(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + form.querySelector('[data-bareme]').textContent; }
      else if (val && f === 'mois') { val = val + ' mois'; }
      else if (val && f === 'telephone') { val = @json(Oev::INDICATIF) + ' ' + val; }
      dd.textContent = val;
      // Ligne masquée si vide ou si sa condition n'est pas remplie
      var ligne = dd.closest('[data-ligne-recap]');
      ligne.hidden = !val || (ligne.dataset.condition && !conditionRemplie(ligne.dataset.condition));
    });

    var liste = document.getElementById('recapPieces');
    liste.innerHTML = '';
    var fournies = 0, demandees = 0;
    Object.keys(pieces).forEach(function (type) {
      if (!pieceDemandee(type)) { return; }
      demandees++;
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
      fournies + '/' + demandees + ' pièce(s) demandée(s) : ' + (fournies === demandees ? 'dossier complet.' : 'dossier incomplet (complétable plus tard).');
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
      // Les étapes non affichées sont masquées : on les révèle le temps de la vérification
      var panneau = form.querySelector('[data-etape="' + i + '"]');
      var etaitMasque = panneau.hidden;
      panneau.hidden = false;
      var ok = validerEtape(i);
      panneau.hidden = etaitMasque;
      if (!ok) { e.preventDefault(); afficher(i); validerEtape(i); return; }
    }
    // Marge de 256 Ko pour les champs texte de la requête
    var totalKo = tailleTotaleKo();
    if (totalKo > limites.envoi - 256) {
      e.preventDefault();
      afficher(etapeFichiers);
      alerteTaille('Les fichiers sélectionnés totalisent ' + enMo(totalKo) + ' : le maximum autorisé en un seul envoi est de ' + enMo(limites.envoi - 256) + '. Retirez ou allégez certaines pièces (elles pourront être ajoutées plus tard via « Modifier / compléter »).');
      return;
    }
    // Les fichiers des pièces non demandées ne sont pas envoyés
    Object.keys(pieces).forEach(function (type) { if (!pieceDemandee(type)) { document.getElementById(type).value = ''; } });
    // « Enregistrer et soumettre au DR » : le serveur soumet le dossier s'il est complet
    var avecSoumission = e.submitter && e.submitter.hasAttribute('data-avec-soumission');
    form.querySelector('[data-champ-soumettre]').value = avecSoumission ? '1' : '0';
    btnEnr.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    if (e.submitter) {
      e.submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ' + (avecSoumission ? 'Soumission…' : 'Enregistrement…');
    }
  });

  // État initial
  majCoherence();
  majStatut();
  majAge();
  majRib();
  var initiale = Number(form.dataset.etapeInitiale) || 1;
  atteinte = @json($edition || $errors->any()) ? total : initiale;
  afficher(initiale, true);
});
</script>
@endpush
