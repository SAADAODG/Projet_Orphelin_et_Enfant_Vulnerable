@extends('layouts.app')
@use('App\Models\Oev')

@section('title', 'OEV sortants · Liste des OEV | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">OEV</p>
        <h1 class="h3 mb-1">Liste des OEV</h1>
      </div>
    </div>
  </div>

  <div class="mt-3">@include('oevs._onglets_liste')</div>

  {{-- OEV sortants : désactivés / demandes de désactivation en attente --}}
  <div class="btn-group mt-3" role="group" aria-label="OEV sortants">
    <a class="btn btn-sm {{ $vue === 'desactives' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('oevs.sortants') }}" @if ($vue === 'desactives') aria-current="page" @endif>
      <i class="bi bi-person-slash" aria-hidden="true"></i> Désactivés <span class="badge rounded-pill text-bg-light ms-1">{{ $nbDesactives }}</span>
    </a>
    <a class="btn btn-sm {{ $vue === 'demandes' ? 'btn-warning' : 'btn-outline-warning' }}" href="{{ route('oevs.sortants', ['vue' => 'demandes']) }}" @if ($vue === 'demandes') aria-current="page" @endif>
      <i class="bi bi-hourglass-split" aria-hidden="true"></i> Demandes en attente <span class="badge rounded-pill {{ $nbDemandes ? 'text-bg-danger' : 'text-bg-light' }} ms-1">{{ $nbDemandes }}</span>
    </a>
  </div>

  <section class="panel mt-3">
    <form class="d-flex flex-wrap gap-2 mb-3" method="GET" action="{{ route('oevs.sortants') }}" role="search">
      @if ($vue === 'demandes')<input type="hidden" name="vue" value="demandes">@endif
      <input class="form-control form-control-sm w-auto" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Code OEV, nom ou prénom" aria-label="Rechercher un OEV">
      <select class="form-select form-select-sm w-auto" name="motif" aria-label="Filtrer par motif" onchange="this.form.submit()">
        <option value="">Tous les motifs</option>
        @foreach (Oev::MOTIFS_DESACTIVATION as $cle => $libelle)
          <option value="{{ $cle }}" @selected($filtres['motif'] === $cle)>{{ $libelle }}</option>
        @endforeach
      </select>
      <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
      @if (array_filter($filtres))
        <a class="btn btn-link btn-sm" href="{{ route('oevs.sortants', $vue === 'demandes' ? ['vue' => 'demandes'] : []) }}">Effacer</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">OEV</th>
            <th scope="col">Localité</th>
            <th scope="col">Motif</th>
            <th scope="col">{{ $vue === 'demandes' ? 'Demandée' : 'Désactivé' }}</th>
            <th scope="col" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($oevs as $oev)
            <tr>
              <td>
                <a class="fw-semibold" href="{{ route('oevs.show', $oev) }}">{{ $oev->nomComplet() }}</a>
                <span class="d-block small text-muted">{{ $oev->reference() }} · {{ $oev->age() }} ans</span>
              </td>
              <td class="small">{{ $oev->commune?->nom }} <span class="text-muted">· {{ $oev->province?->nom }}</span></td>
              <td>
                {{ $oev->libelleMotifDesactivation() }}
                @if ($oev->desactivation_commentaire)<span class="d-block small text-muted">{{ $oev->desactivation_commentaire }}</span>@endif
              </td>
              <td class="small text-nowrap">
                @if ($vue === 'demandes')
                  {{ $oev->desactivation_demandee_at?->format('d/m/Y') }}
                  <span class="d-block text-muted">{{ $oev->demandeurDesactivation?->name }}</span>
                @else
                  {{ $oev->desactive_at?->format('d/m/Y') }}
                  <span class="d-block text-muted">{{ $oev->auteurDesactivation?->name }}</span>
                @endif
              </td>
              <td class="text-end">
                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                  @if ($central && $vue === 'demandes')
                    <form method="POST" action="{{ route('oevs.desactivation.decider', $oev) }}" data-confirm="Valider la désactivation de {{ $oev->nomComplet() }} ? L’OEV ne bénéficiera plus d’aucune aide." data-confirm-danger>
                      @csrf
                      <input type="hidden" name="decision" value="valider">
                      <button class="btn btn-danger btn-sm" type="submit"><i class="bi bi-person-slash" aria-hidden="true"></i> Valider</button>
                    </form>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#motifModal"
                            data-action="{{ route('oevs.desactivation.decider', $oev) }}" data-mode="refuser" data-oev="{{ $oev->nomComplet() }}">
                      <i class="bi bi-x-lg" aria-hidden="true"></i> Refuser
                    </button>
                  @elseif ($central)
                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#motifModal"
                            data-action="{{ route('oevs.reactiver', $oev) }}" data-mode="reactiver" data-oev="{{ $oev->nomComplet() }}">
                      <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réactiver
                    </button>
                  @endif
                  <a class="btn btn-light btn-sm" href="{{ route('oevs.show', $oev) }}" aria-label="Fiche de {{ $oev->nomComplet() }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                {{ $vue === 'demandes' ? 'Aucune demande de désactivation en attente.' : 'Aucun OEV désactivé.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($oevs->hasPages())
      <div class="d-flex justify-content-center mt-3">{{ $oevs->links('pagination::bootstrap-5') }}</div>
    @endif
  </section>
</div>

{{-- Motif de la réactivation ou du refus (formulaire commun, adressé à l'OEV choisi) --}}
@if ($central)
  <div class="modal fade" id="motifModal" tabindex="-1" aria-labelledby="motifModalTitre" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="" data-formulaire-motif>
          @csrf
          <input type="hidden" name="decision" value="refuser" disabled data-decision>
          <div class="modal-header">
            <h2 class="modal-title h5" id="motifModalTitre" data-titre>Motif</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            <label class="form-label fw-semibold" for="motif-saisie" data-libelle>Motif <span class="text-danger">*</span></label>
            <textarea class="form-control" id="motif-saisie" rows="3" maxlength="2000" required data-motif></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
            <button class="btn btn-primary" type="submit" data-valider>Confirmer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
@endsection

@if ($central)
@push('scripts')
<script>
  // Fenêtre de motif : réactivation (motif_reactivation) ou refus d'une demande (motif_refus)
  document.addEventListener('DOMContentLoaded', function () {
    var modale = document.getElementById('motifModal');
    if (!modale) return;
    var formulaire = modale.querySelector('[data-formulaire-motif]');
    modale.addEventListener('show.bs.modal', function (e) {
      var bouton = e.relatedTarget;
      var reactiver = bouton.dataset.mode === 'reactiver';
      formulaire.action = bouton.dataset.action;
      formulaire.querySelector('[data-decision]').disabled = reactiver;
      formulaire.querySelector('[data-motif]').name = reactiver ? 'motif_reactivation' : 'motif_refus';
      formulaire.querySelector('[data-motif]').value = '';
      formulaire.querySelector('[data-titre]').textContent = (reactiver ? 'Réactiver ' : 'Refuser la désactivation de ') + bouton.dataset.oev;
      formulaire.querySelector('[data-valider]').textContent = reactiver ? 'Réactiver' : 'Refuser la demande';
    });
    modale.addEventListener('shown.bs.modal', function () { formulaire.querySelector('[data-motif]').focus(); });
  });
</script>
@endpush
@endif
