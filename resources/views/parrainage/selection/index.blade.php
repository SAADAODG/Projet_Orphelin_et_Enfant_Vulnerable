@extends('layouts.app')
@use('App\Models\Oev')
@use('App\Models\SessionParrainage')
@use('App\Models\BeneficiaireSession')
@use('App\Services\Parrainage\SelectionParrainage')
@use('App\Support\Montant')

@section('title', 'Sélection · Session ' . $session->reference() . ' | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .sel-filtres { border: 1px solid var(--admin-border); border-radius: 14px; padding: 1rem; background: var(--admin-surface-soft); }
  .sel-filtres .form-label { font-size: .8rem; font-weight: 600; color: var(--admin-muted); margin-bottom: .2rem; }
  .sel-filtres [data-localite] { padding: .25rem 2rem .25rem .5rem; font-size: .875rem; }
  .sel-filtres [data-localite] + .invalid-feedback { display: none; }
  .sel-frais { max-width: 9rem; }
  .sel-table td, .sel-table th { white-space: nowrap; }
  .sel-table td.sel-wrap { white-space: normal; min-width: 14rem; }
  .sel-table tr.sel-ineligible > td { background: var(--admin-surface-soft); }
  .sel-cout { border: 1px solid var(--admin-border); border-radius: 14px; padding: 1rem; background: var(--admin-surface); }
  .sel-cout table td, .sel-cout table th { white-space: nowrap; }
  .sel-coche { position: sticky; bottom: 0; z-index: 5; border: 1px solid var(--admin-border); border-radius: 12px; background: var(--admin-surface); box-shadow: 0 -4px 16px rgba(0, 0, 0, .06); padding: .6rem 1rem; }
</style>
@endpush

@php
  $f = $filtres;
  $reste = $session->enveloppe - $budget['engage'];
  $lesDeux = $session->type_appui === SessionParrainage::TYPE_LES_DEUX;
  $couvreScolaire = in_array(SessionParrainage::TYPE_SCOLAIRE, $session->naturesCouvertes(), true);
  $couvreFormation = in_array(SessionParrainage::TYPE_FORMATION, $session->naturesCouvertes(), true);
  $ouiNon = ['1' => 'Oui', '0' => 'Non'];
  $situations = array_filter(SelectionParrainage::SITUATIONS, fn ($cle) => $cle === SelectionParrainage::EN_FORMATION ? $couvreFormation : $couvreScolaire, ARRAY_FILTER_USE_KEY);
  $select = function (string $nom, string $label, array $options, $valeur, string $tous = 'Tous') {
      $html = '<label class="form-label" for="s-' . $nom . '">' . e($label) . '</label><select class="form-select form-select-sm" id="s-' . $nom . '" name="' . $nom . '"><option value="">' . e($tous) . '</option>';
      foreach ($options as $cle => $libelle) {
          $html .= '<option value="' . e($cle) . '"' . ((string) $valeur === (string) $cle ? ' selected' : '') . '>' . e($libelle) . '</option>';
      }
      return $html . '</select>';
  };
  $quotasAffiches = $session->quotas_actifs
    ? $regions->filter(fn ($r) => $session->quotas->firstWhere('region_id', $r->id) || ($budget['regions'][$r->id] ?? 0))
    : collect();
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-funnel" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Session {{ $session->reference() }}</p>
        <h1 class="h3 mb-1">{{ $session->description }}</h1>
        <p class="text-muted mb-0">
          <span class="badge rounded-pill text-bg-{{ $session->couleurEtat() }}">{{ $session->libelleEtat() }}</span>
          · {{ $session->libelleTypeAppui() }} · plafond {{ Montant::fcfa($session->plafond_beneficiaire) }} par bénéficiaire
        </p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Sessions</a>
      @if ($modifiable)
        <form method="POST" action="{{ route('parrainage.sessions.selection.valider', $session) }}"
              data-confirm="Valider la liste définitive ? {{ $retenus->count() }} OEV retenu(s) pour {{ Montant::fcfa($budget['engage']) }}. La liste, l’enveloppe et les quotas seront figés.">
          @csrf
          <button class="btn btn-success btn-sm" type="submit" @disabled($retenus->isEmpty())><i class="bi bi-patch-check" aria-hidden="true"></i> Valider la liste définitive</button>
        </form>
      @endif
    </div>
  </div>

  @include('parrainage.sessions._onglets', ['actif' => 'selection'])

  @unless ($session->parametresModifiables())
    <div class="alert alert-info small mt-3 mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>La session est « {{ $session->libelleEtat() }} » : la liste ci-dessous est la liste définitive, elle ne peut plus être modifiée.</div>
  @endunless

  {{-- Budget --}}
  {{-- Budget ; les cartes « retenus » et « liste d'attente » ouvrent la liste de la session --}}
  <section class="row g-3 mt-1 oev-stats--compact" aria-label="Budget de la session">
    @foreach ([
      ['bi-wallet2', 'Enveloppe', Montant::fcfa($session->enveloppe), '', null],
      ['bi-cash-coin', 'Engagé (retenus)', Montant::fcfa($budget['engage']), '', null],
      ['bi-piggy-bank', 'Reste disponible', Montant::fcfa($reste), $reste < 0 ? 'oev-stat--danger' : 'oev-stat--success', null],
      ['bi-person-check', 'OEV retenus', $retenus->count(), 'oev-stat--info', route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'liste'])],
      ['bi-hourglass-split', 'En liste d’attente', $attente->count(), 'oev-stat--warning', route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'liste']) . '#liste-attente'],
    ] as [$icone, $libelle, $valeur, $style, $url])
      <div class="col-6 col-md-4 col-xl">
        <{{ $url ? 'a' : 'div' }} class="oev-stat {{ $style }} {{ $url && $onglet === 'liste' ? 'is-active' : '' }}" @if ($url) href="{{ $url }}" @endif>
          <span class="oev-stat-icone"><i class="bi {{ $icone }}" aria-hidden="true"></i></span>
          <span>
            <span class="oev-stat-valeur d-block">{{ $valeur }}</span>
            <span class="oev-stat-label">{{ $libelle }}</span>
          </span>
        </{{ $url ? 'a' : 'div' }}>
      </div>
    @endforeach
  </section>

  @if ($quotasAffiches->isNotEmpty())
    <details class="panel mt-3 py-2">
      <summary class="fw-semibold small"><i class="bi bi-pie-chart me-1" aria-hidden="true"></i>Quotas par région</summary>
      <div class="table-responsive mt-2">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Région</th><th class="text-end">Quota</th><th class="text-end">Engagé</th><th class="text-end">Reste</th></tr></thead>
          <tbody>
            @foreach ($quotasAffiches as $region)
              @php
                $quota = (int) ($session->quotas->firstWhere('region_id', $region->id)?->montant ?? 0);
                $engage = $budget['regions'][$region->id] ?? 0;
              @endphp
              <tr>
                <td>{{ $region->nom }}</td>
                <td class="text-end">{{ $quota ? Montant::fcfa($quota) : 'Aucun quota' }}</td>
                <td class="text-end">{{ Montant::fcfa($engage) }}</td>
                <td class="text-end {{ $quota - $engage < 0 ? 'text-danger' : '' }}">{{ Montant::fcfa($quota - $engage) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </details>
  @endif

  {{-- Sous-onglets : liste de la session / recherche d'éligibles --}}
  <ul class="nav nav-pills mt-3 gap-2">
    <li class="nav-item">
      <a class="nav-link {{ $onglet === 'liste' ? 'active' : '' }}" href="{{ route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'liste']) }}">
        <i class="bi bi-list-check" aria-hidden="true"></i> Liste de la session <span class="badge rounded-pill text-bg-light border ms-1">{{ $retenus->count() + $attente->count() }}</span>
      </a>
    </li>
    @if ($session->parametresModifiables())
      <li class="nav-item">
        <a class="nav-link {{ $onglet === 'eligibles' ? 'active' : '' }}" href="{{ route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'eligibles']) }}">
          <i class="bi bi-person-plus" aria-hidden="true"></i> Ajouter des OEV
        </a>
      </li>
    @endif
  </ul>

  @if ($onglet === 'liste')
    {{-- ================= Liste de la session ================= --}}
    <section class="panel mt-3">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1">OEV retenus <span class="badge rounded-pill text-bg-light border ms-1">{{ $retenus->count() }}</span></h2>
          <p class="text-muted mb-0 small">Montant retenu : le plus petit des frais réels et du plafond ({{ Montant::fcfa($session->plafond_beneficiaire) }}) ; le plafond quand les frais ne sont pas renseignés.</p>
        </div>
      </div>
      @if ($retenus->isEmpty())
        <div class="oev-vide">
          <div class="oev-vide-icone"><i class="bi bi-person-plus" aria-hidden="true"></i></div>
          <h3 class="h5">Aucun OEV retenu pour le moment</h3>
          @if ($modifiable)
            <p class="text-muted">Peuplez la session : affichez les OEV, filtrez-les (scolarité, vulnérabilité, âge, localité…) puis ajoutez-les.</p>
            <a class="btn btn-primary" href="{{ route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'eligibles']) }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Ajouter des OEV</a>
          @endif
        </div>
      @else
        @include('parrainage.selection._lignes', ['lignes' => $retenus, 'statut' => BeneficiaireSession::RETENU])
      @endif
    </section>

    @if ($attente->isNotEmpty())
      <section class="panel mt-3" id="liste-attente">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1">Liste d’attente <span class="badge rounded-pill text-bg-light border ms-1">{{ $attente->count() }}</span></h2>
            <p class="text-muted mb-0 small">OEV hors enveloppe ou hors quota, par rang. Ils pourront être retenus si des fonds se libèrent (retrait d’un OEV, session de rattrapage, parrain).</p>
          </div>
        </div>
        @include('parrainage.selection._lignes', ['lignes' => $attente, 'statut' => BeneficiaireSession::ATTENTE])
      </section>
    @endif
  @else
    {{-- ================= Recherche des éligibles ================= --}}
    <section class="panel mt-3">
      <form class="sel-filtres" method="GET" action="{{ route('parrainage.sessions.selection', $session) }}" id="formFiltresSelection">
        <input type="hidden" name="onglet" value="eligibles">
        <div class="row g-2">
          <div class="col-sm-6 col-lg-2">{!! $select('eligibilite', 'Éligibilité', ['oui' => 'Éligibles uniquement', 'non' => 'Non éligibles uniquement'], $f['eligibilite']) !!}</div>
          <div class="col-lg-4">
            <label class="form-label" for="s-q">Recherche</label>
            <input class="form-control form-control-sm" id="s-q" name="q" type="search" value="{{ $f['q'] }}" placeholder="Code OEV, nom, prénom, établissement…">
          </div>
          @if ($lesDeux)
            <div class="col-sm-6 col-lg-2">{!! $select('type', 'Type d’appui', [SessionParrainage::TYPE_SCOLAIRE => 'Scolaire', SessionParrainage::TYPE_FORMATION => 'Formation'], $f['type']) !!}</div>
          @endif
          <div class="col-sm-6 col-lg-2">{!! $select('niveau_priorite', 'Priorité du cas', Oev::NIVEAUX, $f['niveau_priorite'], 'Toutes') !!}</div>
          <div class="col-sm-6 col-lg-2">{!! $select('scolarite', 'Scolarité', $situations, $f['scolarite'], 'Toutes') !!}</div>
          <div class="col-6 col-lg-2">{!! $select('sexe', 'Sexe', Oev::SEXES, $f['sexe']) !!}</div>

          <div class="col-6 col-lg-1">
            <label class="form-label" for="s-age_min">Âge min.</label>
            <input class="form-control form-control-sm" id="s-age_min" name="age_min" type="number" min="0" max="25" value="{{ $f['age_min'] }}">
          </div>
          <div class="col-6 col-lg-1">
            <label class="form-label" for="s-age_max">Âge max.</label>
            <input class="form-control form-control-sm" id="s-age_max" name="age_max" type="number" min="0" max="25" value="{{ $f['age_max'] }}">
          </div>
          <div class="col-sm-6 col-lg-2">{!! $select('statut', 'Statut OEV', Oev::STATUTS, $f['statut']) !!}</div>
          <div class="col-sm-6 col-lg-3">{!! $select('vulnerabilite', 'Vulnérabilité', Oev::VULNERABILITES, $f['vulnerabilite'], 'Toutes') !!}</div>
          <div class="col-6 col-lg-1">{!! $select('handicap', 'Handicap', $ouiNon, $f['handicap']) !!}</div>
          <div class="col-6 col-lg-2">{!! $select('niveau_etude', 'Niveau d’étude', Oev::NIVEAUX_ETUDE, $f['niveau_etude']) !!}</div>
          <div class="col-sm-6 col-lg-2">{!! $select('groupe_population', 'Groupe de population', Oev::GROUPES_POPULATION, $f['groupe_population']) !!}</div>

          <div class="col-12">
            @include('partials.localite-selects', [
              'localites' => $localites,
              'requis' => false,
              'valeurs' => ['region_id' => $f['region_id'], 'province_id' => $f['province_id'], 'commune_id' => $f['commune_id']],
              'colonnes' => ['region_id' => 'col-sm-4', 'province_id' => 'col-sm-4', 'commune_id' => 'col-sm-4'],
            ])
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-2">
          <span class="small text-muted me-auto"><i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>Les filtres s’appliquent automatiquement.</span>
          @if ($nbFiltres)<a class="btn btn-link btn-sm" href="{{ route('parrainage.sessions.selection', ['session' => $session, 'onglet' => 'eligibles']) }}">Réinitialiser</a>@endif
          <noscript><button class="btn btn-primary btn-sm" type="submit">Filtrer</button></noscript>
        </div>
      </form>

      {{-- Estimation du coût : frais de scolarité cumulés + montants estimatifs pour les OEV sans frais connus --}}
      @if ($estimation)
        <div class="sel-cout mt-3">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <h2 class="h6 mb-1"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Estimation du coût des {{ Montant::nombre($estimation['nombre']) }} OEV éligibles {{ $nbFiltres ? 'filtrés' : '' }}</h2>
              <p class="text-muted small mb-0">
                Scolarisés : frais de scolarité de la fiche. Non scolarisés d’âge scolaire ({{ SelectionParrainage::AGE_SCOLAIRE_MIN }} à {{ SelectionParrainage::AGE_SCOLAIRE_MAX }} ans) et OEV en formation : montant estimatif saisi ci-dessous.
                Montant pris en charge : frais plafonnés à {{ Montant::fcfa($session->plafond_beneficiaire) }}, le plafond quand les frais manquent.
              </p>
            </div>
          </div>

          @if ($modifiable)
            <form class="row g-2 align-items-end mt-1" method="POST" action="{{ route('parrainage.sessions.selection.estimations', $session) }}" id="formEstimations">
              @csrf
              @method('PUT')
              @if ($couvreScolaire)
                <div class="col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold mb-1" for="estimation_scolarisation">Montant estimatif · enfant à inscrire à l’école (FCFA)</label>
                  <input class="form-control form-control-sm text-end @error('estimation_scolarisation') is-invalid @enderror" id="estimation_scolarisation" name="estimation_scolarisation"
                         type="text" inputmode="numeric" value="{{ old('estimation_scolarisation', $session->estimation_scolarisation === null ? '' : Montant::nombre($session->estimation_scolarisation)) }}" placeholder="Ex : 25 000">
                  @error('estimation_scolarisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              @endif
              @if ($couvreFormation)
                <div class="col-sm-6 col-lg-4">
                  <label class="form-label small fw-semibold mb-1" for="estimation_formation">Montant estimatif · OEV en formation professionnelle (FCFA)</label>
                  <input class="form-control form-control-sm text-end @error('estimation_formation') is-invalid @enderror" id="estimation_formation" name="estimation_formation"
                         type="text" inputmode="numeric" value="{{ old('estimation_formation', $session->estimation_formation === null ? '' : Montant::nombre($session->estimation_formation)) }}" placeholder="Ex : 75 000">
                  @error('estimation_formation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              @endif
              <div class="col-auto">
                <span class="small text-muted" data-etat-enregistrement><i class="bi bi-cloud-check me-1" aria-hidden="true"></i>Enregistrement automatique</span>
                <noscript><button class="btn btn-outline-primary btn-sm" type="submit">Enregistrer les montants</button></noscript>
              </div>
            </form>
          @endif

          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr><th scope="col">Situation</th><th scope="col" class="text-end">OEV</th><th scope="col" class="text-end">Cumul des frais</th><th scope="col" class="text-end">Pris en charge</th></tr>
              </thead>
              <tbody>
                @foreach ($situations as $cle => $libelle)
                  @php
                    $g = $estimation['groupes'][$cle];
                  @endphp
                  <tr>
                    <td>
                      {{ $libelle }}
                      @if ($cle === SelectionParrainage::A_SCOLARISER)
                        <span class="d-block small text-muted">{{ $session->estimation_scolarisation === null ? 'Montant estimatif non saisi : plafond retenu' : Montant::fcfa($session->estimation_scolarisation) . ' par enfant' }}</span>
                      @elseif ($cle === SelectionParrainage::EN_FORMATION)
                        <span class="d-block small text-muted">{{ $session->estimation_formation === null ? 'Montant estimatif non saisi : plafond retenu' : Montant::fcfa($session->estimation_formation) . ' par OEV' }}</span>
                      @elseif ($g['sans_frais'])
                        <span class="d-block small text-muted">dont {{ $g['sans_frais'] }} sans frais renseignés (plafond retenu)</span>
                      @endif
                    </td>
                    <td class="text-end">{{ Montant::nombre($g['nombre']) }}</td>
                    <td class="text-end">{{ Montant::fcfa($g['frais']) }}</td>
                    <td class="text-end">{{ Montant::fcfa($g['pris_en_charge']) }}</td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="fw-semibold">
                  <td>Total estimé</td>
                  <td class="text-end">{{ Montant::nombre($estimation['nombre']) }}</td>
                  <td class="text-end">{{ Montant::fcfa($estimation['frais']) }}</td>
                  <td class="text-end">{{ Montant::fcfa($estimation['pris_en_charge']) }}</td>
                </tr>
                <tr>
                  <td colspan="3" class="text-end small">Reste disponible de l’enveloppe ({{ Montant::fcfa($reste) }}) après ajout de tous ces OEV</td>
                  <td class="text-end fw-semibold {{ $reste - $estimation['pris_en_charge'] < 0 ? 'text-danger' : 'text-success' }}">{{ Montant::fcfa($reste - $estimation['pris_en_charge']) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      @endif

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <p class="mb-0">
          <strong>{{ $eligibles->total() }}</strong> OEV{{ ($f['eligibilite'] ?? null) === 'non' ? ' non éligible(s)' : ', dont ' . $nbAjoutables . ' éligible(s)' }}, du besoin le plus urgent au moins urgent
        </p>
        @if ($modifiable && $nbAjoutables)
          <form method="POST" action="{{ route('parrainage.sessions.selection.ajouter', $session) }}"
                data-confirm="Ajouter les {{ $nbAjoutables }} OEV éligibles correspondant aux filtres (coût estimé : {{ Montant::fcfa($estimation['pris_en_charge']) }}) ? Ils seront retenus du besoin le plus urgent au moins urgent tant que l’enveloppe et les quotas le permettent, les autres iront en liste d’attente.">
            @csrf
            <input type="hidden" name="tous" value="1">
            @foreach (array_filter($f, fn ($v) => $v !== null && $v !== '') as $cle => $valeur)
              <input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">
            @endforeach
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-plus-square-dotted" aria-hidden="true"></i> Tout ajouter ({{ $nbAjoutables }})</button>
          </form>
        @endif
      </div>

      @if ($eligibles->isEmpty())
        <div class="oev-vide">
          <div class="oev-vide-icone"><i class="bi bi-search" aria-hidden="true"></i></div>
          <h3 class="h5">Aucun OEV ne correspond</h3>
          <p class="text-muted mb-0">Seuls les OEV intégrés et non désactivés sont proposés ; ceux déjà choisis pour cette session figurent dans « Liste de la session ».</p>
        </div>
      @else
        <form method="POST" action="{{ route('parrainage.sessions.selection.ajouter', $session) }}" id="formAjout">
          @csrf
          <div class="table-responsive mt-2">
            <table class="table align-middle mb-0 sel-table oev-liste">
              <thead>
                <tr>
                  @if ($modifiable)<th><input class="form-check-input" type="checkbox" data-tout-cocher aria-label="Tout cocher sur cette page" @checked(count($motifs) < count($eligibles->items()))></th>@endif
                  <th>OEV</th><th>Statut</th><th class="sel-wrap">Vulnérabilités</th><th>Priorité</th><th>Localité</th><th>Scolarité</th><th class="text-end">Montant prévu</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($eligibles as $oev)
                  @php
                    $type = SelectionParrainage::typePour($oev, $session);
                    ['frais' => $frais, 'estime' => $estime] = SelectionParrainage::fraisPour($oev, $session, $type);
                    $montantPrevu = BeneficiaireSession::montantPour($frais, $session->plafond_beneficiaire);
                    $motif = $motifs[$oev->id] ?? null;
                  @endphp
                  <tr class="{{ $motif ? 'sel-ineligible' : '' }}">
                    @if ($modifiable)
                      <td>
                        @if ($motif)
                          <input class="form-check-input" type="checkbox" disabled aria-label="{{ $oev->nomComplet() }} : non éligible ({{ $motif }})" title="Non éligible : {{ $motif }}">
                        @else
                          <input class="form-check-input" type="checkbox" name="oev_ids[]" value="{{ $oev->id }}" aria-label="Ajouter {{ $oev->nomComplet() }}" data-case-oev data-montant="{{ $montantPrevu }}" checked>
                        @endif
                      </td>
                    @endif
                    <td class="sel-wrap">
                      @include('parrainage._oev', ['oev' => $oev, 'detail' => '· ' . $oev->age() . ' ans', 'nouvelOnglet' => true])
                      @if ($motif)<span class="badge rounded-pill text-bg-secondary mt-1"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Non éligible : {{ $motif }}</span>@endif
                      @if ($dernierSuivi = $suivis[$oev->id] ?? null)
                        <span class="d-block small mt-1 {{ $dernierSuivi->a_reconduire ? 'text-success' : 'text-danger' }}" title="{{ $dernierSuivi->observation }}">
                          <i class="bi bi-journal-check" aria-hidden="true"></i> Déjà parrainé : {{ $dernierSuivi->resume() }}
                        </span>
                      @endif
                    </td>
                    <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span></td>
                    <td class="sel-wrap small">{{ implode(', ', $oev->libelles('vulnerabilites', Oev::VULNERABILITES)) ?: '—' }}</td>
                    <td>{{ $oev->niveau_priorite ? Oev::NIVEAUX[$oev->niveau_priorite] : '—' }}</td>
                    <td class="small">{{ $oev->commune?->nom }} <span class="text-muted">· {{ $oev->province?->nom }}</span></td>
                    <td class="small">
                      @if ($oev->situation_scolaire === 'scolarise')
                        {{ Oev::toutesLesClasses()[$oev->classe] ?? 'Scolarisé' }}
                        @if ($oev->type_etablissement)<span class="d-block text-muted">{{ Oev::TYPES_ETABLISSEMENT[$oev->type_etablissement] ?? '' }}</span>@endif
                      @elseif ($oev->formation_professionnelle && $oev->formation_etat === 'en_cours')
                        Formation{{ $oev->formation_filiere ? ' · ' . $oev->formation_filiere : '' }}
                      @else
                        <span class="text-muted">{{ Oev::SITUATIONS_SCOLAIRES[$oev->situation_scolaire] ?? 'Non scolarisé' }}</span>
                        @if (SelectionParrainage::situationDe($oev) === SelectionParrainage::A_SCOLARISER)<span class="d-block text-muted">à inscrire</span>@endif
                      @endif
                    </td>
                    <td class="text-end small">
                      @if ($motif)
                        <span class="text-muted">—</span>
                      @else
                        {{ Montant::fcfa($montantPrevu) }}
                        @if ($frais === null)
                          <span class="d-block text-muted">{{ $estime ? 'montant estimatif non saisi' : 'frais non renseignés' }} : plafond</span>
                        @elseif ($estime)
                          <span class="d-block text-muted">montant estimatif</span>
                        @elseif ($frais > $session->plafond_beneficiaire)
                          <span class="d-block text-muted">frais {{ Montant::fcfa($frais) }}, plafonnés</span>
                        @else
                          <span class="d-block text-muted">{{ $type === SessionParrainage::TYPE_FORMATION ? 'frais de formation' : 'frais de scolarité' }}</span>
                        @endif
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="mt-3">{{ $eligibles->links() }}</div>
          @if ($modifiable)
            {{-- Coût des OEV cochés, recalculé à chaque case --}}
            <div class="sel-coche d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2" data-reste="{{ $reste }}">
              <span class="small">
                <strong data-nb-coches-texte>0</strong> OEV coché(s) · coût <strong data-cout-coches>0 FCFA</strong>
                · reste après ajout <strong data-reste-coches>{{ Montant::fcfa($reste) }}</strong>
              </span>
              <button class="btn btn-primary" type="submit" data-bouton-ajout disabled><i class="bi bi-person-plus" aria-hidden="true"></i> Ajouter à la session <span data-nb-coches>(0)</span></button>
            </div>
          @endif
        </form>
      @endif
    </section>
  @endif
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Cases à cocher des éligibles : tout cocher, compteur du bouton d'ajout
    var formulaire = document.getElementById('formAjout');
    if (formulaire) {
      var cases = formulaire.querySelectorAll('[data-case-oev]');
      var bouton = formulaire.querySelector('[data-bouton-ajout]');
      var barre = formulaire.querySelector('[data-reste]');
      var format = function (n) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n).replace(/ | /g, ' ') + ' FCFA'; };
      var maj = function () {
        var cochees = formulaire.querySelectorAll('[data-case-oev]:checked');
        var cout = 0;
        cochees.forEach(function (c) { cout += Number(c.dataset.montant) || 0; });
        if (bouton) { bouton.disabled = cochees.length === 0; bouton.querySelector('[data-nb-coches]').textContent = '(' + cochees.length + ')'; }
        if (barre) {
          var reste = Number(barre.dataset.reste) - cout;
          barre.querySelector('[data-nb-coches-texte]').textContent = cochees.length;
          barre.querySelector('[data-cout-coches]').textContent = format(cout);
          var champReste = barre.querySelector('[data-reste-coches]');
          champReste.textContent = format(reste);
          champReste.classList.toggle('text-danger', reste < 0);
        }
      };
      var tout = formulaire.querySelector('[data-tout-cocher]');
      if (tout) { tout.addEventListener('change', function () { cases.forEach(function (c) { c.checked = tout.checked; }); maj(); }); }
      cases.forEach(function (c) {
        c.addEventListener('change', function () {
          if (tout) { tout.checked = formulaire.querySelectorAll('[data-case-oev]:not(:checked)').length === 0; }
          maj();
        });
      });
      // Les éligibles de la page arrivent cochés : coût et bouton d'ajout à jour dès l'affichage
      maj();
    }

    // Rechargement automatique (filtres, montants estimatifs) : on retrouve la position et le champ en cours
    var CLE = 'selection-parrainage-retour';
    try {
      var retour = JSON.parse(sessionStorage.getItem(CLE) || 'null');
      sessionStorage.removeItem(CLE);
      if (retour) {
        window.scrollTo(0, retour.y || 0);
        var champ = retour.champ && document.getElementById(retour.champ);
        if (champ) {
          champ.focus({ preventScroll: true });
          if (champ.setSelectionRange && champ.type !== 'number') { champ.setSelectionRange(champ.value.length, champ.value.length); }
        }
      }
    } catch (e) {}
    var envoyerAuto = function (formulaire, declencheur) {
      try { sessionStorage.setItem(CLE, JSON.stringify({ y: window.scrollY, champ: declencheur && declencheur.id })); } catch (e) {}
      formulaire.requestSubmit ? formulaire.requestSubmit() : formulaire.submit();
    };
    // Listes : envoi immédiat ; saisie libre : envoi après une courte pause de frappe
    var brancherAuto = function (formulaire, delai) {
      var minuterie;
      formulaire.addEventListener('change', function (e) {
        if (!e.target.matches('input, select')) { return; }
        clearTimeout(minuterie);
        envoyerAuto(formulaire, e.target);
      });
      formulaire.addEventListener('input', function (e) {
        if (!e.target.matches('input[type="search"], input[type="text"], input[type="number"]')) { return; }
        clearTimeout(minuterie);
        minuterie = setTimeout(function () { envoyerAuto(formulaire, e.target); }, delai);
      });
    };

    // Filtres : appliqués automatiquement ; filtres vides non envoyés (URL plus lisible)
    var filtres = document.getElementById('formFiltresSelection');
    if (filtres) {
      filtres.addEventListener('submit', function () {
        filtres.querySelectorAll('input, select').forEach(function (c) { if (!c.value) { c.disabled = true; } });
      });
      brancherAuto(filtres, 700);
    }

    // Montants estimatifs : enregistrés automatiquement
    var estimations = document.getElementById('formEstimations');
    if (estimations) {
      estimations.addEventListener('submit', function () {
        var etat = estimations.querySelector('[data-etat-enregistrement]');
        if (etat) { etat.textContent = 'Enregistrement…'; }
      });
      brancherAuto(estimations, 1200);
    }
  });
</script>
@endpush
