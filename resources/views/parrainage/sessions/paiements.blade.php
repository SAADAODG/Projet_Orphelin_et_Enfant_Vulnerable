@extends('layouts.app')
@use('App\Http\Controllers\PaiementSessionController')
@use('App\Models\Ecole')
@use('App\Services\Parrainage\ExportPaiementSession')
@use('App\Support\Montant')

@section('title', 'Paiement · session ' . $session->reference() . ' | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .pai-rib { font-family: var(--bs-font-monospace); font-size: .8rem; }
  .pai-reception { position: sticky; bottom: 0; z-index: 5; border: 1px solid var(--admin-border); border-radius: 12px; background: var(--admin-surface); box-shadow: 0 -4px 16px rgba(0, 0, 0, .06); padding: .6rem 1rem; }
</style>
@endpush

@php
  $reste = (int) $totaux->montant - (int) $totaux->montant_paye;
  $nomRegion = fn ($id) => $regions[$id] ?? 'Région non renseignée';
  $aujourdhui = now()->toDateString();
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-bank" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Session {{ $session->reference() }}</p>
        <h1 class="h3 mb-1">{{ $session->description }}</h1>
        <p class="text-muted mb-0">
          <span class="badge rounded-pill text-bg-{{ $session->couleurEtat() }}">{{ $session->libelleEtat() }}</span>
          · {{ $session->libelleTypeAppui() }} · {{ $session->libelleEnveloppe() }}
          @unless ($central) · {{ $zone->libelle() }} @endunless
        </p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Sessions</a>
      {{-- Fichier pour les services financiers (facultatif : tout se consulte ici) : feuilles et colonnes au choix --}}
      <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#exportPaiementModal"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exporter (Excel)</button>
    </div>
  </div>

  @include('parrainage.sessions._onglets', ['actif' => 'paiements'])

  @if ($errors->any())
    <div class="alert alert-danger mt-3 mb-0" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $errors->first() }}</div>
  @endif

  {{-- Totaux de la zone --}}
  <section class="row g-3 mt-1 oev-stats--compact" aria-label="Paiement de la session">
    @foreach ([
      ['bi-cash-stack', 'À verser', Montant::fcfa($totaux->montant), Montant::nombre($totaux->nombre_oev) . ' OEV', ''],
      ['bi-check2-circle', 'Fonds reçus', Montant::fcfa($totaux->montant_paye), $totaux->etablissements_payes . ' / ' . $totaux->nombre_etablissements . ' établissement(s)', 'oev-stat--success'],
      ['bi-hourglass-split', 'Reste à verser', Montant::fcfa($reste), '', $reste > 0 ? 'oev-stat--warning' : 'oev-stat--success'],
      ['bi-exclamation-triangle', 'RIB manquants', $totaux->rib_manquants, 'établissement(s)', $totaux->rib_manquants ? 'oev-stat--danger' : ''],
    ] as [$icone, $libelle, $valeur, $aide, $style])
      <div class="col-6 col-xl-3">
        <div class="oev-stat {{ $style }}">
          <span class="oev-stat-icone"><i class="bi {{ $icone }}" aria-hidden="true"></i></span>
          <span>
            <span class="oev-stat-valeur d-block">{{ $valeur }}</span>
            <span class="oev-stat-label">{{ $libelle }}@if ($aide) <span class="d-block small text-muted">{{ $aide }}</span>@endif</span>
          </span>
        </div>
      </div>
    @endforeach
  </section>

  @if ($totaux->sans_etablissement)
    <div class="alert alert-warning small mt-3 mb-0">
      <i class="bi bi-building-exclamation me-1" aria-hidden="true"></i>
      {{ Montant::nombre($totaux->sans_etablissement) }} OEV n’ont pas d’établissement (école ou centre de formation) sur leur fiche : ils ne peuvent pas être payés tant qu’il n’est pas renseigné.
    </div>
  @endif

  {{-- Affichage et filtres --}}
  <form class="panel mt-3" method="GET" action="{{ route('parrainage.sessions.paiements', $session) }}">
    <div class="row g-2 align-items-end">
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="par">Afficher par</label>
        <select class="form-select form-select-sm" id="par" name="par" onchange="this.form.submit()">
          @foreach (PaiementSessionController::AFFICHAGES as $cle => $libelle)
            <option value="{{ $cle }}" @selected($affichage === $cle)>{{ $libelle }}</option>
          @endforeach
        </select>
      </div>
      @if ($central)
        <div class="col-sm-6 col-lg-3">
          <label class="form-label small fw-semibold" for="region_id">Région</label>
          <select class="form-select form-select-sm" id="region_id" name="region_id">
            <option value="">Toutes les régions</option>
            @foreach ($localites as $region)
              <option value="{{ $region['id'] }}" @selected($filtres['region_id'] === $region['id'])>{{ $region['nom'] }}</option>
            @endforeach
          </select>
        </div>
      @endif
      @if ($affichage === 'etablissement')
        <div class="col-sm-6 col-lg-2">
          <label class="form-label small fw-semibold" for="paiement">Paiement</label>
          <select class="form-select form-select-sm" id="paiement" name="paiement">
            <option value="">Tous</option>
            @foreach (PaiementSessionController::FILTRES_PAIEMENT as $cle => $libelle)
              <option value="{{ $cle }}" @selected($filtres['paiement'] === $cle)>{{ $libelle }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label small fw-semibold" for="q">Établissement ou RIB</label>
          <input class="form-control form-control-sm" id="q" name="q" type="search" value="{{ $filtres['q'] }}" placeholder="Nom ou RIB">
        </div>
      @endif
      <div class="col-auto">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
      </div>
    </div>
  </form>

  @if ($affichage === 'etablissement')
    {{-- ================= Par établissement ================= --}}
    <form class="panel mt-3" method="POST" action="{{ route('parrainage.sessions.paiements.store', $session) }}" id="formReception">
      @csrf
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-building" aria-hidden="true"></i><span>Établissements</span></h2>
          <p class="text-muted mb-0 small">Écoles et centres de formation fréquentés par les OEV retenus, avec leur banque et leur RIB. Le montant est la somme des montants retenus de leurs OEV.</p>
        </div>
      </div>
      @if ($etablissements->isEmpty())
        <p class="text-muted mb-0">Aucun établissement ne correspond à ces filtres.</p>
      @else
        <div class="table-responsive">
          <table class="table align-middle mb-0 oev-liste">
            <thead>
              <tr>
                @if ($peutEnregistrer)<th scope="col"><input class="form-check-input" type="checkbox" data-tout-cocher aria-label="Cocher tous les établissements à payer de la page"></th>@endif
                <th scope="col">Région / province</th>
                <th scope="col">Établissement</th>
                <th scope="col">Banque et RIB</th>
                <th scope="col" class="text-end">OEV</th>
                <th scope="col" class="text-end">Montant</th>
                <th scope="col">Paiement</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($etablissements as $e)
                @php($ribManquant = $e->etablissement_id && ($e->rib === null || $e->rib === ''))
                <tr>
                  @if ($peutEnregistrer)
                    <td>
                      @if ($e->etablissement_id && ! $e->paiement_id)
                        <input class="form-check-input" type="checkbox" name="etablissements[]" value="{{ $e->etablissement_id }}" data-case-etab data-montant="{{ (int) $e->montant }}" aria-label="{{ $e->nom }}">
                      @endif
                    </td>
                  @endif
                  <td class="small">{{ $nomRegion($e->region_id) }}@if ($e->province_id)<span class="d-block text-muted">{{ $provinces[$e->province_id] ?? '' }}</span>@endif</td>
                  <td>
                    @if ($e->etablissement_id)
                      <span class="fw-semibold">{{ $e->nom }}</span>
                      <span class="d-block small text-muted">{{ Ecole::NATURES[$e->nature] ?? '' }}@if ($e->commune) · {{ $e->commune }}@endif</span>
                    @else
                      <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Établissement non renseigné</span>
                    @endif
                  </td>
                  <td class="small">
                    @if (! $e->etablissement_id)
                      —
                    @elseif ($ribManquant)
                      <span class="badge text-bg-danger">RIB manquant</span>
                      @can('gérer localités')
                        <a class="d-block" href="{{ route('parametres.ecoles.edit', $e->etablissement_id) }}">Renseigner</a>
                      @endcan
                    @else
                      {{ $e->banque ?: 'Banque non précisée' }}<span class="d-block pai-rib">{{ $e->rib }}</span>
                    @endif
                  </td>
                  <td class="text-end">{{ Montant::nombre($e->nombre_oev) }}</td>
                  <td class="text-end fw-semibold text-nowrap">{{ Montant::fcfa($e->montant) }}</td>
                  <td class="small">
                    @if ($e->paiement_id)
                      <span class="badge text-bg-success"><i class="bi bi-check2" aria-hidden="true"></i> Reçu le {{ \Illuminate\Support\Carbon::parse($e->recu_le)->format('d/m/Y') }}</span>
                      @if ($e->reference)<span class="d-block text-muted">Réf. {{ $e->reference }}</span>@endif
                      @if ((int) $e->montant_paye !== (int) $e->montant)
                        <span class="d-block text-warning">Versé : {{ Montant::fcfa($e->montant_paye) }} ({{ $e->nombre_oev_paye }} OEV)</span>
                      @endif
                      @if ($peutEnregistrer)
                        <button class="btn btn-link btn-sm p-0 text-danger" type="submit" form="annuler-{{ $e->paiement_id }}">Annuler</button>
                      @endif
                    @elseif ($e->etablissement_id)
                      <span class="badge text-bg-warning">À payer</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if ($etablissements->hasPages())
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <small class="text-muted">Établissements {{ $etablissements->firstItem() }} à {{ $etablissements->lastItem() }} sur {{ $etablissements->total() }}</small>
            {{ $etablissements->links('pagination::bootstrap-5') }}
          </div>
        @endif

        @if ($peutEnregistrer)
          {{-- Réception des fonds par les établissements cochés --}}
          <div class="pai-reception d-flex flex-wrap align-items-end gap-2 mt-3">
            <span class="small me-auto"><strong data-nb-coches>0</strong> établissement(s) coché(s) · <strong data-montant-coches>0 FCFA</strong></span>
            <div>
              <label class="form-label small mb-0" for="recu_le">Fonds reçus le</label>
              <input class="form-control form-control-sm" id="recu_le" name="recu_le" type="date" value="{{ old('recu_le', $aujourdhui) }}" max="{{ $aujourdhui }}" required>
            </div>
            <div>
              <label class="form-label small mb-0" for="reference">Référence du virement</label>
              <input class="form-control form-control-sm" id="reference" name="reference" type="text" maxlength="100" value="{{ old('reference') }}" placeholder="Facultative">
            </div>
            <button class="btn btn-success btn-sm" type="submit" data-bouton-reception disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Marquer comme reçus</button>
          </div>
        @endif
      @endif
    </form>

    {{-- Annulations (formulaires séparés, hors du formulaire de réception) --}}
    @if ($peutEnregistrer)
      @foreach ($etablissements->filter(fn ($e) => $e->paiement_id) as $e)
        <form id="annuler-{{ $e->paiement_id }}" method="POST" action="{{ route('parrainage.sessions.paiements.destroy', [$session, $e->paiement_id]) }}"
              data-confirm="Annuler la réception des fonds par « {{ $e->nom }} » ?" data-confirm-danger hidden>
          @csrf
          @method('DELETE')
        </form>
      @endforeach
    @endif
  @else
    {{-- ================= Par région ================= --}}
    <section class="panel mt-3">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-map" aria-hidden="true"></i><span>Régions</span></h2>
          <p class="text-muted mb-0 small">Nombre d’établissements et d’OEV de chaque région, montant à verser et fonds déjà reçus. « Toute la région a reçu » enregistre la réception pour tous ses établissements pas encore payés.</p>
        </div>
      </div>
      @if ($parRegion->isEmpty())
        <p class="text-muted mb-0">Aucun OEV retenu dans cette zone.</p>
      @else
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Région</th>
                <th scope="col" class="text-end">Établissements</th>
                <th scope="col" class="text-end">OEV</th>
                <th scope="col" class="text-end">À verser</th>
                <th scope="col" class="text-end">Reçu</th>
                <th scope="col" class="text-end">Reste</th>
                @if ($peutEnregistrer)<th scope="col"><span class="visually-hidden">Réception</span></th>@endif
              </tr>
            </thead>
            <tbody>
              @foreach ($parRegion as $r)
                @php($resteRegion = (int) $r->montant - (int) $r->montant_paye)
                <tr>
                  <td class="fw-semibold">
                    <a href="{{ route('parrainage.sessions.paiements', ['session' => $session, 'par' => 'etablissement', 'region_id' => $r->region_id]) }}">{{ $nomRegion($r->region_id) }}</a>
                    @if ($r->sans_etablissement)<span class="d-block small text-warning">{{ $r->sans_etablissement }} OEV sans établissement</span>@endif
                  </td>
                  <td class="text-end">{{ $r->etablissements_payes }} / {{ $r->nombre_etablissements }} payé(s)</td>
                  <td class="text-end">{{ Montant::nombre($r->nombre_oev) }}</td>
                  <td class="text-end text-nowrap">{{ Montant::fcfa($r->montant) }}</td>
                  <td class="text-end text-nowrap text-success">{{ Montant::fcfa($r->montant_paye) }}</td>
                  <td class="text-end text-nowrap fw-semibold {{ $resteRegion > 0 ? 'text-warning' : '' }}">{{ Montant::fcfa($resteRegion) }}</td>
                  @if ($peutEnregistrer)
                    <td>
                      @if ($r->etablissements_payes < $r->nombre_etablissements && $r->region_id)
                        <form class="d-flex flex-wrap gap-1 justify-content-end" method="POST" action="{{ route('parrainage.sessions.paiements.store', $session) }}"
                              data-confirm="Enregistrer la réception des fonds pour les {{ $r->nombre_etablissements - $r->etablissements_payes }} établissement(s) non payés de la région {{ $nomRegion($r->region_id) }} ?">
                          @csrf
                          <input type="hidden" name="region_id" value="{{ $r->region_id }}">
                          <input class="form-control form-control-sm w-auto" type="date" name="recu_le" value="{{ $aujourdhui }}" max="{{ $aujourdhui }}" required aria-label="Date de réception">
                          <input class="form-control form-control-sm w-auto" type="text" name="reference" maxlength="100" placeholder="Référence" aria-label="Référence du virement">
                          <button class="btn btn-outline-success btn-sm" type="submit"><i class="bi bi-check2-all" aria-hidden="true"></i> Toute la région a reçu</button>
                        </form>
                      @else
                        <span class="badge text-bg-success">Fonds reçus</span>
                      @endif
                    </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="fw-semibold">
                <td>Total</td>
                <td class="text-end">{{ $totaux->etablissements_payes }} / {{ $totaux->nombre_etablissements }}</td>
                <td class="text-end">{{ Montant::nombre($totaux->nombre_oev) }}</td>
                <td class="text-end text-nowrap">{{ Montant::fcfa($totaux->montant) }}</td>
                <td class="text-end text-nowrap text-success">{{ Montant::fcfa($totaux->montant_paye) }}</td>
                <td class="text-end text-nowrap">{{ Montant::fcfa($reste) }}</td>
                @if ($peutEnregistrer)<td></td>@endif
              </tr>
            </tfoot>
          </table>
        </div>
      @endif
    </section>
  @endif
</div>

{{-- Export Excel : feuilles, colonnes de chaque feuille, filtres --}}
<div class="modal fade" id="exportPaiementModal" tabindex="-1" aria-labelledby="exportPaiementTitre" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form class="modal-formulaire" method="GET" action="{{ route('parrainage.sessions.paiements.export', $session) }}" id="formExportPaiement">
        <div class="modal-header">
          <h2 class="modal-title h5" id="exportPaiementTitre"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Fichier de paiement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            @if ($central)
              <div class="col-sm-6 col-lg-4">
                <label class="form-label small fw-semibold" for="export_region_id">Région</label>
                <select class="form-select form-select-sm" id="export_region_id" name="region_id">
                  <option value="">Toutes les régions</option>
                  @foreach ($localites as $region)
                    <option value="{{ $region['id'] }}" @selected($filtres['region_id'] === $region['id'])>{{ $region['nom'] }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="col-sm-6 col-lg-4">
              <label class="form-label small fw-semibold" for="export_paiement">Établissements</label>
              <select class="form-select form-select-sm" id="export_paiement" name="paiement">
                <option value="">Tous</option>
                @foreach (PaiementSessionController::FILTRES_PAIEMENT as $cle => $libelle)
                  <option value="{{ $cle }}" @selected($filtres['paiement'] === $cle)>{{ $libelle }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <p class="small text-muted mb-2">Cochez les feuilles du fichier, puis les colonnes de chacune. Les colonnes marquées « toujours » portent les totaux.</p>
          <div class="row g-3">
            @foreach (ExportPaiementSession::FEUILLES as $feuille => $titre)
              <div class="col-lg-4">
                <fieldset class="border rounded p-3 h-100" data-feuille-export>
                  <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">
                    <span class="form-check d-inline-block mb-0">
                      <input class="form-check-input" type="checkbox" name="feuilles[]" value="{{ $feuille }}" id="feuille-{{ $feuille }}" checked data-case-feuille>
                      <label class="form-check-label" for="feuille-{{ $feuille }}">{{ $titre }}</label>
                    </span>
                  </legend>
                  @foreach (ExportPaiementSession::COLONNES[$feuille] as $cle => $libelle)
                    @php($toujours = in_array($cle, ExportPaiementSession::OBLIGATOIRES[$feuille], true))
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="colonnes[{{ $feuille }}][]" value="{{ $cle }}" id="col-{{ $feuille }}-{{ $cle }}"
                             @checked($toujours || in_array($cle, ExportPaiementSession::PAR_DEFAUT[$feuille], true)) @disabled($toujours) data-case-colonne>
                      <label class="form-check-label small" for="col-{{ $feuille }}-{{ $cle }}">{{ $libelle }}@if ($toujours) <span class="text-muted">(toujours)</span>@endif</label>
                    </div>
                  @endforeach
                </fieldset>
              </div>
            @endforeach
          </div>
        </div>
        <div class="modal-footer">
          <span class="small text-muted me-auto">Liste nominative : une feuille par région, rangée par province, établissement puis nom, avec le sous-total de chaque établissement.</span>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-success" type="submit" data-bouton-export><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Télécharger</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Fenêtre d'export : colonnes d'une feuille décochée inactives ; au moins une feuille
    var exportForm = document.getElementById('formExportPaiement');
    if (exportForm) {
      var majExport = function () {
        var nb = 0;
        exportForm.querySelectorAll('[data-feuille-export]').forEach(function (bloc) {
          var active = bloc.querySelector('[data-case-feuille]').checked;
          if (active) { nb++; }
          bloc.querySelectorAll('[data-case-colonne]').forEach(function (c) { if (!c.hasAttribute('data-toujours')) { c.closest('.form-check').classList.toggle('opacity-50', !active); } });
        });
        exportForm.querySelector('[data-bouton-export]').disabled = nb === 0;
      };
      exportForm.addEventListener('change', majExport);
      majExport();
    }

    var formulaire = document.getElementById('formReception');
    if (!formulaire) return;
    var cases = formulaire.querySelectorAll('[data-case-etab]');
    var bouton = formulaire.querySelector('[data-bouton-reception]');
    if (!bouton) return;
    var format = function (n) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n).replace(/ | /g, ' ') + ' FCFA'; };
    var maj = function () {
      var cochees = formulaire.querySelectorAll('[data-case-etab]:checked');
      var total = 0;
      cochees.forEach(function (c) { total += Number(c.dataset.montant) || 0; });
      formulaire.querySelector('[data-nb-coches]').textContent = cochees.length;
      formulaire.querySelector('[data-montant-coches]').textContent = format(total);
      bouton.disabled = cochees.length === 0;
    };
    var tout = formulaire.querySelector('[data-tout-cocher]');
    if (tout) { tout.addEventListener('change', function () { cases.forEach(function (c) { c.checked = tout.checked; }); maj(); }); }
    cases.forEach(function (c) { c.addEventListener('change', maj); });
    maj();
  });
</script>
@endpush
