@extends('layouts.app')
@use('App\Models\Oev')
@use('App\Models\SessionParrainage')
@use('App\Services\Parrainage\ExtractionsParrainage')
@use('App\Services\Parrainage\FichiersExtraction')
@use('App\Support\Montant')

@section('title', 'Extractions du parrainage | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .ext-donnees { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .75rem; }
  .ext-donnees fieldset { border: 1px solid var(--admin-border); border-radius: 12px; padding: .75rem .9rem; background: var(--admin-surface-soft); }
  .ext-donnees legend { float: none; width: auto; font-size: .85rem; font-weight: 700; margin: 0 0 .35rem; padding: 0; }
  .sel-multi-menu { min-width: 100%; max-height: 18rem; overflow-y: auto; }
  .sel-multi-menu--large { min-width: 22rem; max-height: 24rem; }
  .sel-provinces { margin: .1rem 0 .35rem 1.5rem; }
</style>
@endpush

@php
  // Données de la liste pour un parrain, regroupées ; les données d'identité sont réservées au niveau central
  $groupesParrain = [
      'Identité (niveau central)' => ['code', 'nom', 'prenom', 'date_naissance'],
      'Profil' => ['initiales', 'age', 'sexe', 'statut', 'vulnerabilites', 'handicap'],
      'Localité' => ['region', 'province', 'commune'],
      'Scolarité et besoin' => ['cycle', 'classe', 'besoin', 'priorite'],
      'Tuteur (niveau central)' => ['tuteur', 'telephone_tuteur'],
  ];
  $choisies = array_map('strval', (array) old('donnees', $donneesParrainChoisies ?? []));
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Pilotage</h1>
      </div>
    </div>
  </div>

  <div class="mt-3">@include('parrainage.pilotage._onglets')</div>

  {{-- Bascule entre les deux extractions --}}
  <div class="btn-group mt-3" role="group" aria-label="Type d’extraction">
    <a class="btn btn-sm {{ $vue === 'parrain' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('parrainage.pilotage.extractions') }}" @if ($vue === 'parrain') aria-current="page" @endif>
      <i class="bi bi-person-heart" aria-hidden="true"></i> Liste pour un parrain
    </a>
    @if ($peutPaiement)
      <a class="btn btn-sm {{ $vue === 'paiement' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('parrainage.pilotage.extractions', ['vue' => 'paiement']) }}" @if ($vue === 'paiement') aria-current="page" @endif>
        <i class="bi bi-bank" aria-hidden="true"></i> Liste pour paiement
      </a>
    @endif
  </div>

  @if ($vue === 'paiement')
    {{-- ================= Liste pour paiement ================= --}}
    <form class="panel mt-3" method="GET" action="{{ route('parrainage.pilotage.paiement') }}">
      <div class="panel-header">
        <div><h2 class="h5 mb-1 section-title"><i class="bi bi-bank" aria-hidden="true"></i><span>Liste pour paiement</span></h2></div>
      </div>
      @unless ($disponible)
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> Données non disponibles : la liste définitive et les établissements viendront du module Sélection. L’extraction produit pour l’instant un fichier sans ligne.</div>
      @endunless
      @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
      @endif
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="session_id">Session <span class="text-danger">*</span></label>
          <select class="form-select" id="session_id" name="session_id" required>
            <option value="">Choisir une session validée…</option>
            @foreach ($sessionsPaiement as $session)
              <option value="{{ $session->id }}" @selected((string) old('session_id') === (string) $session->id)>{{ $session->reference() }} — {{ $session->description }} ({{ $session->libelleEtat() }})</option>
            @endforeach
          </select>
          @if ($sessionsPaiement->isEmpty())<div class="form-text">Aucune session validée pour l’instant.</div>@endif
        </div>
        <div class="col-md-3">
          <label class="form-label" for="type_appui_paiement">Type d’appui</label>
          <select class="form-select" id="type_appui_paiement" name="type_appui">
            <option value="">Tous</option>
            @foreach (SessionParrainage::TYPES_APPUI as $cle => $libelle)
              <option value="{{ $cle }}">{{ $libelle }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="etablissement_id">Établissement</label>
          <select class="form-select" id="etablissement_id" name="etablissement_id" @disabled($etablissements->isEmpty())>
            <option value="">{{ $etablissements->isEmpty() ? 'Non disponible' : 'Tous' }}</option>
            @foreach ($etablissements as $etablissement)
              <option value="{{ $etablissement['id'] }}">{{ $etablissement['nom'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          @include('partials.localite-selects', [
            'localites' => $localites,
            'requis' => false,
            'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
          ])
        </div>

        {{-- Données à afficher dans chaque feuille --}}
        <div class="col-12">
          <h3 class="h6 mb-2">Données à afficher</h3>
          <div class="ext-donnees">
            <fieldset>
              <legend>Feuille « Par établissement »</legend>
              @foreach ($colonnesEtablissement as $cle => $libelle)
                @php($obligatoire = in_array($cle, ExtractionsParrainage::COLONNES_ETABLISSEMENT_OBLIGATOIRES, true))
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="ce-{{ $cle }}" name="colonnes_etablissement[]" value="{{ $cle }}" checked @disabled($obligatoire)>
                  <label class="form-check-label" for="ce-{{ $cle }}">{{ $libelle }}@if ($obligatoire) <span class="text-muted small">(toujours)</span>@endif</label>
                </div>
              @endforeach
            </fieldset>
            <fieldset>
              <legend>Feuille « Nominatif »</legend>
              @foreach ($colonnesNominatif as $cle => $libelle)
                @php($obligatoire = in_array($cle, ExtractionsParrainage::COLONNES_NOMINATIF_OBLIGATOIRES, true))
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="cn-{{ $cle }}" name="colonnes_nominatif[]" value="{{ $cle }}"
                         @checked(in_array($cle, ExtractionsParrainage::NOMINATIF_PAR_DEFAUT, true)) @disabled($obligatoire)>
                  <label class="form-check-label" for="cn-{{ $cle }}">{{ $libelle }}@if ($obligatoire) <span class="text-muted small">(toujours)</span>@endif</label>
                </div>
              @endforeach
            </fieldset>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-primary" type="submit" name="format" value="pdf"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</button>
        <button class="btn btn-primary" type="submit" name="format" value="xlsx"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</button>
      </div>
    </form>
  @else
    {{-- ================= Liste pour un parrain ================= --}}
    <form class="panel mt-3" method="GET" action="{{ route('parrainage.pilotage.extractions') }}" id="formParrain">
      <input type="hidden" name="filtre" value="1">
      <div class="panel-header">
        <div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-heart" aria-hidden="true"></i><span>Liste pour un parrain</span></h2></div>
      </div>
      @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
      @endif

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="parrain_id">Parrain <span class="text-danger">*</span></label>
          <select class="form-select" id="parrain_id" name="parrain_id" required onchange="this.form.filtre.disabled = true; this.form.submit()">
            <option value="">Choisir un parrain…</option>
            @foreach ($parrains as $p)
              <option value="{{ $p->id }}" @selected($parrain?->id === $p->id)>{{ $p->nom }}</option>
            @endforeach
          </select>
        </div>

        @if ($parrain)
          <div class="col-md-6">
            <span class="form-label d-block">Source</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="source" id="source-eligibles" value="eligibles" @checked($f['source'] === 'eligibles')>
              <label class="form-check-label" for="source-eligibles">OEV éligibles, sans appui de la nature choisie pour l’année</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="source" id="source-attente" value="attente" @checked($f['source'] === 'attente')>
              <label class="form-check-label" for="source-attente">Liste d’attente d’une session</label>
            </div>
          </div>
          <div class="col-sm-4 col-lg-2" data-source-eligibles>
            <label class="form-label" for="annee">Année scolaire</label>
            <input class="form-control" id="annee" name="annee" value="{{ $f['annee'] }}" pattern="\d{4}-\d{4}">
          </div>
          <div class="col-sm-8 col-lg-4" data-source-eligibles>
            <label class="form-label" for="nature_id">Nature d’appui</label>
            <select class="form-select" id="nature_id" name="nature_id">
              <option value="">Toutes (aucun OEV exclu)</option>
              @foreach ($natures->sortBy(fn ($n) => in_array($n->id, $f['natures_parrain'], true) ? 0 : 1) as $nature)
                <option value="{{ $nature->id }}" @selected($f['nature']?->id === $nature->id)>{{ $nature->libelle }}@if (in_array($nature->id, $f['natures_parrain'], true)) (proposée par le parrain)@endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-sm-6 col-lg-3" data-source-attente>
            <label class="form-label" for="session_liste">Session <span class="text-danger">*</span></label>
            <select class="form-select" id="session_liste" name="session_id">
              <option value="">Choisir une session…</option>
              @foreach ($sessions as $session)
                <option value="{{ $session->id }}" @selected($f['session']?->id === $session->id)>{{ $session->reference() }} — {{ $session->description }}</option>
              @endforeach
            </select>
          </div>

          {{-- Zone d'intervention : choisie à l'extraction (toutes les régions par défaut) --}}
          <div class="col-md-6 col-lg-4">
            @include('parrainage.selection._filtre-localites', ['localites' => $localites, 'regions' => $f['regions'], 'provinces' => $f['provinces'], 'label' => 'Zone d’intervention'])
          </div>

          {{-- Autres critères à choix multiple --}}
          <div class="col-6 col-md-3 col-lg-2">@include('parrainage.selection._filtre-multiple', ['nom' => 'sexe', 'label' => 'Sexe', 'options' => Oev::SEXES, 'valeurs' => $f['sexe']])</div>
          <div class="col-6 col-md-3 col-lg-3">@include('parrainage.selection._filtre-multiple', ['nom' => 'statut', 'label' => 'Statut OEV', 'options' => Oev::STATUTS, 'valeurs' => $f['statut']])</div>
          <div class="col-md-6 col-lg-3">@include('parrainage.selection._filtre-multiple', ['nom' => 'vulnerabilite', 'label' => 'Vulnérabilité', 'options' => Oev::vulnerabilites(), 'valeurs' => $f['vulnerabilite'], 'tous' => 'Toutes'])</div>
          <div class="col-6 col-md-3 col-lg-2">@include('parrainage.selection._filtre-multiple', ['nom' => 'niveau_priorite', 'label' => 'Priorité', 'options' => Oev::NIVEAUX, 'valeurs' => $f['niveau_priorite'], 'tous' => 'Toutes'])</div>
          <div class="col-6 col-md-3 col-lg-2">@include('parrainage.selection._filtre-multiple', ['nom' => 'niveau_etude', 'label' => 'Niveau d’étude', 'options' => Oev::NIVEAUX_ETUDE, 'valeurs' => $f['niveau_etude']])</div>
          <div class="col-6 col-md-3 col-lg-2">@include('parrainage.selection._filtre-multiple', ['nom' => 'handicap', 'label' => 'Handicap', 'options' => ['1' => 'Oui', '0' => 'Non'], 'valeurs' => $f['handicap']])</div>
          <div class="col-3 col-lg-1">
            <label class="form-label" for="age_min">Âge min.</label>
            <input class="form-control form-control-sm" id="age_min" name="age_min" type="number" min="0" max="25" value="{{ $f['age_min'] }}">
          </div>
          <div class="col-3 col-lg-1">
            <label class="form-label" for="age_max">Âge max.</label>
            <input class="form-control form-control-sm" id="age_max" name="age_max" type="number" min="0" max="25" value="{{ $f['age_max'] }}">
          </div>

          {{-- Données à extraire --}}
          <div class="col-12">
            <h3 class="h6 mb-1">Données à extraire</h3>
            <p class="small text-muted mb-2">Les initiales sont la première lettre du nom et du prénom (ex : « OA » pour OUEDRAOGO Awa).
              @unless ($central) Les données d’identité et du tuteur sont réservées au niveau central. @endunless</p>
            <div class="ext-donnees">
              @foreach ($groupesParrain as $groupe => $cles)
                <fieldset>
                  <legend>{{ $groupe }}</legend>
                  @foreach ($cles as $cle)
                    @php($identifiante = $donneesParrain[$cle][2])
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="d-{{ $cle }}" name="donnees[]" value="{{ $cle }}"
                             @checked(in_array($cle, $choisies, true) && ($central || ! $identifiante)) @disabled($identifiante && ! $central) @if ($identifiante) data-identite @endif>
                      <label class="form-check-label" for="d-{{ $cle }}">{{ $donneesParrain[$cle][0] }}</label>
                    </div>
                  @endforeach
                </fieldset>
              @endforeach
            </div>
          </div>

          @if ($central)
            <div class="col-12" data-confirmation-identite hidden>
              <div class="alert alert-warning small mb-0">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="confirmation_identite" name="confirmation_identite" value="1">
                  <label class="form-check-label" for="confirmation_identite">
                    Des données d’identité des enfants sont cochées. Je confirme qu’elles sont transmises dans le cadre d’une convention avec ce parrain. L’extraction sera tracée.
                  </label>
                </div>
              </div>
            </div>
          @endif
        @endif
      </div>

      @if ($parrain)
        {{-- Exports limités (mémoire du serveur) : le bouton est désactivé, avec l'explication, au-delà --}}
        @php($pdfTropGrand = $nombreListe > FichiersExtraction::PDF_LIGNES_MAX)
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4"
             data-limite-export data-lignes="{{ $nombreListe }}" data-cellules-max="{{ FichiersExtraction::CELLULES_MAX }}" data-lignes-max="{{ FichiersExtraction::LIGNES_MAX }}">
          <p class="small text-muted mb-0 me-auto" data-limite-message @unless ($pdfTropGrand) hidden @endunless>
            @if ($pdfTropGrand) PDF limité à {{ Montant::nombre(FichiersExtraction::PDF_LIGNES_MAX) }} lignes : exportez en Excel ou affinez les filtres. @endif
          </p>
          <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Actualiser l’aperçu</button>
          <button class="btn btn-outline-primary" type="submit" formaction="{{ route('parrainage.pilotage.liste-parrain') }}" name="format" value="pdf" @disabled($pdfTropGrand)><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</button>
          <button class="btn btn-primary" type="submit" formaction="{{ route('parrainage.pilotage.liste-parrain') }}" name="format" value="xlsx" data-export-excel><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</button>
        </div>
      @endif
    </form>

    @if ($parrain)
      <section class="panel mt-3">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-eye" aria-hidden="true"></i><span>Aperçu</span></h2>
            <p class="text-muted mb-0">
              {{ Montant::nombre($nombreListe) }} OEV correspondent aux filtres{{ $nombreListe > 10 ? ' (10 premiers affichés)' : '' }}, par ordre de priorité.
              L’aperçu, réservé aux agents, nomme les enfants ; le fichier ne contient que les données cochées.
              @if ($f['source'] === 'attente' && ! $disponible) Liste d’attente : données non disponibles (module Sélection). @endif
            </p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr>@foreach (array_keys($colonnesApercu) as $titre)<th scope="col">{{ $titre }}</th>@endforeach</tr></thead>
            <tbody>
              @forelse ($apercu as $oev)
                <tr>@foreach ($colonnesApercu as $titre => $valeur)<td>{{ str_contains($titre, 'FCFA') ? \App\Support\Montant::nombre($valeur($oev)) : $valeur($oev) }}</td>@endforeach</tr>
              @empty
                <tr><td colspan="{{ count($colonnesApercu) }}" class="text-center text-muted py-3">Aucun OEV ne correspond à ces filtres.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>
    @endif
  @endif
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var formulaire = document.getElementById('formParrain');
    if (!formulaire) return;

    // Localité : les provinces d'une région ne se choisissent qu'une fois la région cochée
    formulaire.querySelectorAll('[data-region]').forEach(function (bloc) {
      var region = bloc.querySelector('[data-region-case]');
      var provinces = bloc.querySelector('[data-provinces]');
      region.addEventListener('change', function () {
        provinces.hidden = !region.checked;
        provinces.querySelectorAll('input').forEach(function (c) { c.disabled = !region.checked; if (!region.checked) { c.checked = false; } });
      });
    });

    // Source : année et nature pour les éligibles, session pour la liste d'attente
    var majSource = function () {
      var attente = formulaire.querySelector('#source-attente');
      if (!attente) return;
      formulaire.querySelectorAll('[data-source-eligibles]').forEach(function (b) { b.hidden = attente.checked; });
      formulaire.querySelectorAll('[data-source-attente]').forEach(function (b) { b.hidden = !attente.checked; });
    };
    formulaire.querySelectorAll('input[name="source"]').forEach(function (r) { r.addEventListener('change', majSource); });
    majSource();

    // Confirmation demandée dès qu'une donnée d'identité est cochée
    var confirmation = formulaire.querySelector('[data-confirmation-identite]');
    var maj = function () {
      if (!confirmation) return;
      confirmation.hidden = !formulaire.querySelector('input[data-identite]:checked');
    };
    formulaire.addEventListener('change', maj);
    maj();

    // Export Excel limité en cellules (lignes × données cochées) : bouton désactivé au-delà, avec l'explication
    var limite = formulaire.querySelector('[data-limite-export]');
    if (limite) {
      var excel = limite.querySelector('[data-export-excel]');
      var message = limite.querySelector('[data-limite-message]');
      var messagePdf = message.textContent.trim();
      var lignes = +limite.dataset.lignes;
      var nombre = function (n) { return n.toLocaleString('fr-FR'); };
      var verifier = function () {
        var colonnes = Math.max(1, formulaire.querySelectorAll('input[name="donnees[]"]:checked').length);
        var max = Math.min(+limite.dataset.lignesMax, Math.floor(+limite.dataset.cellulesMax / colonnes));
        excel.disabled = lignes > max;
        var texte = excel.disabled
          ? 'Excel limité à ' + nombre(max) + ' lignes avec ' + colonnes + ' donnée(s) cochée(s) (' + nombre(lignes) + ' OEV) : affinez les filtres ou cochez moins de données.'
          : '';
        message.textContent = [messagePdf, texte].filter(Boolean).join(' ');
        message.hidden = !message.textContent;
      };
      formulaire.addEventListener('change', verifier);
      verifier();
    }
  });
</script>
@endpush
