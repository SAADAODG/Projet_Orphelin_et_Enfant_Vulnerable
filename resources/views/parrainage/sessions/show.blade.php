@extends('layouts.app')
@use('App\Models\SessionParrainage')
@use('App\Support\Montant')

@section('title', 'Session ' . $session->reference() . ' | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
@endpush

@php
  $utilisateur = auth()->user();
  $suivant = SessionParrainage::etatSuivant($session->etat);
  // Libellé du bouton qui fait avancer la session, et message de confirmation
  $actionsSuivant = [
      SessionParrainage::ETAT_VALIDEE => ['Valider la session', 'bi-patch-check', 'Valider la session ? L’enveloppe, le plafond, les options et les quotas seront figés. Ce passage est normalement déclenché par la validation de la liste définitive dans le module Sélection.'],
      SessionParrainage::ETAT_PAIEMENT => ['Passer en paiement', 'bi-cash-stack', 'Confirmer que l’extraction de paiement a été transmise aux services financiers ?'],
      SessionParrainage::ETAT_CLOTUREE => ['Clôturer la session', 'bi-lock', 'Clôturer la session ? Elle passera en lecture seule.'],
  ];
  $etatsAnterieurs = array_slice(SessionParrainage::ETATS, 0, array_search($session->etat, array_keys(SessionParrainage::ETATS), true), true);
  $info = fn (string $label, $valeur) => '<div class="oev-info"><span class="oev-info-label">' . e($label) . '</span><span class="oev-info-valeur">' . e($valeur) . '</span></div>';
  $ouiNon = fn (bool $valeur) => $valeur ? 'Oui' : 'Non';
  $totalQuotas = array_sum(array_map('intval', $quotas));
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Session {{ $session->reference() }}</p>
        <h1 class="h3 mb-1">{{ $session->description }}</h1>
        <p class="text-muted mb-0">
          <span class="badge rounded-pill text-bg-{{ $session->couleurEtat() }}">{{ $session->libelleEtat() }}</span>
          · {{ $session->libelleTypeAppui() }} · {{ Montant::fcfa($session->enveloppe) }}
        </p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Sessions</a>
      @if ($gere && $session->estModifiable())
        <a class="btn btn-outline-primary btn-sm" href="{{ route('parrainage.sessions.edit', $session) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</a>
      @endif
    </div>
  </div>

  @error('etat')
    <div class="alert alert-danger mt-3" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $message }}</div>
  @enderror

  {{-- Changement d'état : avancer (gestionnaires), revenir en arrière (administrateurs, motif obligatoire) --}}
  @if (($gere && $suivant) || ($utilisateur->supervise() && $etatsAnterieurs))
    <div class="panel mt-3">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted small me-auto">
          <i class="bi bi-signpost-2" aria-hidden="true"></i>
          Cycle : {{ implode(' → ', SessionParrainage::ETATS) }}
        </span>
        @if ($gere && $suivant)
          <form method="POST" action="{{ route('parrainage.sessions.etat', $session) }}" data-confirm="{{ $actionsSuivant[$suivant][2] }}">
            @csrf
            <input type="hidden" name="etat" value="{{ $suivant }}">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi {{ $actionsSuivant[$suivant][1] }}" aria-hidden="true"></i> {{ $actionsSuivant[$suivant][0] }}</button>
          </form>
        @endif
        @if ($utilisateur->supervise() && $etatsAnterieurs)
          <button class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#formRetourEtat" aria-expanded="{{ $errors->has('etat') && old('motif') !== null ? 'true' : 'false' }}" aria-controls="formRetourEtat">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Revenir à un état antérieur
          </button>
        @endif
      </div>
      @if ($utilisateur->supervise() && $etatsAnterieurs)
        <form class="collapse mt-3 {{ $errors->has('etat') && old('motif') !== null ? 'show' : '' }}" id="formRetourEtat" method="POST" action="{{ route('parrainage.sessions.etat', $session) }}"
              data-confirm="Ramener la session à un état antérieur ? Le motif sera tracé dans le journal." data-confirm-danger>
          @csrf
          <div class="row g-2">
            <div class="col-sm-4">
              <label class="form-label small fw-semibold" for="etat-anterieur">Nouvel état</label>
              <select class="form-select form-select-sm" id="etat-anterieur" name="etat" required>
                @foreach ($etatsAnterieurs as $cle => $libelle)
                  <option value="{{ $cle }}" @selected($loop->last)>{{ $libelle }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-sm-8">
              <label class="form-label small fw-semibold" for="motif">Motif <span class="text-danger">*</span></label>
              <textarea class="form-control form-control-sm" id="motif" name="motif" rows="2" maxlength="2000" required>{{ old('motif') }}</textarea>
            </div>
          </div>
          <button class="btn btn-dark btn-sm mt-2" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Confirmer le retour</button>
        </form>
      @endif
    </div>
  @endif

  <nav class="oev-onglets mt-3" aria-label="Vues de la session">
    <a class="oev-onglet {{ $vue === 'informations' ? 'is-active' : '' }}" href="{{ route('parrainage.sessions.show', $session) }}" @if ($vue === 'informations') aria-current="page" @endif>
      <i class="bi bi-info-circle" aria-hidden="true"></i> Informations
    </a>
    @if ($session->quotas_actifs)
      <a class="oev-onglet {{ $vue === 'quotas' ? 'is-active' : '' }}" href="{{ route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas']) }}" @if ($vue === 'quotas') aria-current="page" @endif>
        <i class="bi bi-pie-chart" aria-hidden="true"></i> Quotas
      </a>
    @endif
  </nav>

  @if ($vue === 'informations')
    <div class="row g-3 mt-1">
      <div class="col-12 col-xl-6">
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-calendar2-range" aria-hidden="true"></i> Session</h2>
          <div class="oev-infos">
            {!! $info('Année scolaire', $session->annee) !!}
            {!! $info('Numéro', $session->numero) !!}
            {!! $info('Type d’appui', $session->libelleTypeAppui()) !!}
            {!! $info('État', $session->libelleEtat()) !!}
            {!! $info('Date d’ouverture', $session->date_ouverture?->format('d/m/Y')) !!}
            {!! $info('Date de clôture', $session->date_cloture?->format('d/m/Y') ?? '—') !!}
            {!! $info('Créée par', ($session->createur?->name ?? '—') . ' le ' . $session->created_at?->format('d/m/Y')) !!}
            @if ($session->sessionOrigine)
              <div class="oev-info">
                <span class="oev-info-label">Session d’origine</span>
                <span class="oev-info-valeur"><a href="{{ route('parrainage.sessions.show', $session->sessionOrigine) }}">{{ $session->sessionOrigine->reference() }}</a></span>
              </div>
            @endif
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-6">
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-wallet2" aria-hidden="true"></i> Budget</h2>
          <div class="oev-infos">
            {!! $info('Enveloppe budgétaire', Montant::fcfa($session->enveloppe)) !!}
            {!! $info('Plafond par bénéficiaire', Montant::fcfa($session->plafond_beneficiaire)) !!}
            {!! $info('Source de financement', $session->libelleSource()) !!}
            @if ($session->avecParrains())
              {!! $info('Total des contributions', Montant::fcfa($session->totalContributions())) !!}
            @endif
            @if ($session->quotas_actifs)
              {!! $info('Total des quotas régionaux', Montant::fcfa($session->totalQuotas())) !!}
            @endif
            @if ($session->sessionOrigine)
              {!! $info('Reste de la session d’origine', $resteOrigine === null ? 'Données non disponibles (module Sélection)' : Montant::fcfa($resteOrigine)) !!}
            @endif
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-6">
        <section class="oev-carte">
          <h2 class="oev-carte-titre"><i class="bi bi-sliders" aria-hidden="true"></i> Options de sélection</h2>
          <div class="oev-infos">
            {!! $info('Bloquer le dépassement de l’enveloppe (RG-04)', $ouiNon($session->bloquer_depassement_enveloppe)) !!}
            {!! $info('Quotas par région (RG-04)', $ouiNon($session->quotas_actifs)) !!}
            {!! $info('Exclure les OEV déjà appuyés par un partenaire (RG-07)', $ouiNon($session->exclure_deja_appuyes)) !!}
          </div>
        </section>
      </div>

      @if ($session->avecParrains())
        <div class="col-12 col-xl-6">
          <section class="oev-carte">
            <h2 class="oev-carte-titre"><i class="bi bi-people" aria-hidden="true"></i> Parrains contributeurs</h2>
            @if ($session->parrains->isEmpty())
              <p class="text-muted mb-0">Aucun parrain contributeur.</p>
            @else
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th scope="col">Parrain</th><th scope="col">Type</th><th scope="col" class="text-end">Contribution</th></tr></thead>
                  <tbody>
                    @foreach ($session->parrains as $parrain)
                      <tr>
                        <td>{{ $parrain->nom }}</td>
                        <td>{{ $parrain->libelleType() }}</td>
                        <td class="text-end text-nowrap">{{ Montant::fcfa($parrain->pivot->montant) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </section>
        </div>
      @endif

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
                  @if ($entree->details['motif'] ?? null)
                    <span class="d-block small">Motif : {{ $entree->details['motif'] }}</span>
                  @endif
                  <span class="d-block text-muted small">{{ $entree->created_at->format('d/m/Y à H:i') }} · {{ $entree->utilisateur?->name ?? 'Système' }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </section>
      </div>
    </div>
  @else
    {{-- Vue « Quotas » : un montant par région ; total ≤ enveloppe --}}
    @php($editable = $gere && $session->parametresModifiables())
    <form class="panel mt-3" method="POST" action="{{ route('parrainage.sessions.quotas', $session) }}" data-quotas data-enveloppe="{{ $session->enveloppe }}">
      @csrf
      @method('PUT')
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Quotas par région</span></h2>
          <p class="text-muted mb-0">
            Le total des quotas ne peut pas dépasser l’enveloppe de {{ Montant::fcfa($session->enveloppe) }}.
            @unless ($editable) Les quotas sont figés depuis la validation de la session. @endunless
          </p>
        </div>
        @if ($editable)
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas', 'prorata' => 1]) }}">
            <i class="bi bi-distribute-vertical" aria-hidden="true"></i> Répartir au prorata
          </a>
        @endif
      </div>

      @if ($prorata)
        <div class="alert alert-info small">
          <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>
          Proposition calculée au prorata du nombre d’OEV intégrés par région. Corrigez les montants si besoin, puis enregistrez : rien n’est enregistré tant que vous n’avez pas validé.
        </div>
      @endif
      @error('quotas')<div class="alert alert-danger small">{{ $message }}</div>@enderror

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">Région</th>
              <th scope="col" class="text-end">OEV éligibles</th>
              <th scope="col" class="text-end" style="width: 14rem">Quota (FCFA)</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($regions as $region)
              @php($valeur = old('quotas.' . $region->id, $quotas[$region->id] ?? null))
              <tr>
                <td class="fw-semibold"><label for="quota-{{ $region->id }}" class="mb-0">{{ $region->nom }}</label></td>
                <td class="text-end">{{ Montant::nombre($eligibles[$region->id] ?? 0) }}</td>
                <td class="text-end">
                  @if ($editable)
                    <input class="form-control form-control-sm text-end @error('quotas.' . $region->id) is-invalid @enderror" id="quota-{{ $region->id }}" name="quotas[{{ $region->id }}]"
                           type="text" inputmode="numeric" value="{{ $valeur === null || $valeur === '' ? '' : (is_numeric($valeur) ? Montant::nombre($valeur) : $valeur) }}" placeholder="0" data-quota>
                    @error('quotas.' . $region->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
                  @else
                    {{ Montant::fcfa($valeur ?? 0) }}
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="fw-semibold">
              <td colspan="2" class="text-end">Total des quotas</td>
              <td class="text-end text-nowrap" data-total-quotas>{{ Montant::fcfa($totalQuotas) }}</td>
            </tr>
            <tr>
              <td colspan="2" class="text-end">Reste à répartir</td>
              <td class="text-end text-nowrap {{ $totalQuotas > $session->enveloppe ? 'text-danger' : '' }}" data-reste-quotas>{{ Montant::fcfa($session->enveloppe - $totalQuotas) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      @if ($editable)
        <div class="d-flex justify-content-end gap-2 mt-3">
          <a class="btn btn-outline-secondary" href="{{ route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas']) }}">Annuler</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer les quotas</button>
        </div>
      @endif
    </form>
  @endif
</div>
@endsection

@push('scripts')
<script>
  // Total des quotas et reste à répartir, recalculés pendant la saisie
  document.addEventListener('DOMContentLoaded', function () {
    var formulaire = document.querySelector('[data-quotas]');
    if (!formulaire) return;
    var enveloppe = Number(formulaire.dataset.enveloppe);
    var format = function (n) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n).replace(/ /g, ' ') + ' FCFA'; };
    var recalculer = function () {
      var total = 0;
      formulaire.querySelectorAll('[data-quota]').forEach(function (champ) {
        total += Number(champ.value.replace(/[^\d]/g, '')) || 0;
      });
      formulaire.querySelector('[data-total-quotas]').textContent = format(total);
      var reste = formulaire.querySelector('[data-reste-quotas]');
      reste.textContent = format(enveloppe - total);
      reste.classList.toggle('text-danger', total > enveloppe);
    };
    formulaire.addEventListener('input', recalculer);
    recalculer();
  });
</script>
@endpush
