@extends('layouts.app')

@use('App\Models\Oev')
@use('App\Http\Controllers\ExtractionController')

@section('title', 'Filtrage et extraction | OEV')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .ext-groupe { border: 1px solid var(--admin-border); border-radius: 14px; padding: 1rem; background: var(--admin-surface-soft); height: 100%; }
  .ext-groupe-titre { display: flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .9rem; margin-bottom: .75rem; }
  .ext-groupe-titre i { color: var(--admin-primary); }
  .ext-groupe .form-label { color: var(--admin-muted); font-size: .875rem; font-weight: 600; margin-bottom: .25rem; }
  /* Listes de localités (composant partagé) à la même taille que les autres filtres */
  .ext-groupe [data-localite] { padding: .25rem 2rem .25rem .5rem; font-size: .875rem; border-radius: var(--bs-border-radius-sm); }
  .ext-groupe [data-localite] + .invalid-feedback { display: none; }
  .form-select.is-filtre, .form-control.is-filtre { border-color: var(--admin-primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--admin-primary) 15%, transparent); }
  .ext-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
  .ext-stat { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 14px; border: 1px solid var(--admin-border); background: var(--admin-surface); height: 100%; }
  .ext-stat i { font-size: 1.3rem; color: var(--admin-primary); }
  .ext-stat strong { font-size: 1.35rem; line-height: 1; display: block; }
  .ext-stat span { font-size: .78rem; color: var(--admin-muted); font-weight: 600; }
</style>
@endpush

@php
  $f = $filtres;
  $ouiNon = ['1' => 'Oui', '0' => 'Non'];
  $requeteActuelle = request()->query();
  $stats = [
    ['bi-people', 'Dossiers trouvés', $synthese->total ?? 0],
    ['bi-gender-female', 'Filles', $synthese->filles ?? 0],
    ['bi-gender-male', 'Garçons', $synthese->garcons ?? 0],
    ['bi-person-dash', 'Orphelins', $synthese->orphelins ?? 0],
    ['bi-backpack', 'Scolarisés', $synthese->scolarises ?? 0],
    ['bi-universal-access', 'En situation de handicap', $synthese->handicap ?? 0],
  ];
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-funnel" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Contrôle</p>
        <h1 class="h3 mb-1">Filtrage et extraction</h1>
        <p class="text-muted mb-0">Combinez les critères pour retrouver des dossiers d’enfants, puis exportez le résultat (fichier CSV lisible dans Excel).</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-success {{ ($synthese->total ?? 0) ? '' : 'disabled' }}" href="{{ route('extraction.export', $requeteActuelle) }}" @if (! ($synthese->total ?? 0)) aria-disabled="true" @endif>
        <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Exporter ({{ $synthese->total ?? 0 }})
      </a>
    </div>
  </div>

  <form class="panel mt-3" method="GET" action="{{ route('extraction.index') }}" id="formFiltres">
    {{-- Recherche, tri et actions --}}
    <div class="row g-2 align-items-end">
      <div class="col-lg-5">
        <label class="form-label small fw-semibold mb-1" for="f-q">Recherche libre</label>
        <div class="oev-recherche">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input class="form-control {{ $f['q'] ? 'is-filtre' : '' }}" id="f-q" name="q" type="search" value="{{ $f['q'] }}" placeholder="Code OEV, n° de dossier, nom, parent, tuteur, établissement, localité…">
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">@include('extraction._filtre', ['nom' => 'tri', 'label' => 'Trier par', 'options' => ExtractionController::TRIS, 'valeur' => $f['tri'] === 'recent' ? null : $f['tri'], 'tous' => ExtractionController::TRIS['recent']])</div>
      <div class="col-sm-6 col-lg-4">
        <div class="ext-actions justify-content-lg-end">
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#criteres" aria-expanded="{{ $nbFiltres ? 'true' : 'false' }}" aria-controls="criteres">
            <i class="bi bi-sliders" aria-hidden="true"></i> Critères @if ($nbFiltres)<span class="badge rounded-pill text-bg-primary ms-1">{{ $nbFiltres }}</span>@endif
          </button>
          <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
          @if ($nbFiltres)
            <a class="btn btn-link px-1" href="{{ route('extraction.index') }}">Réinitialiser</a>
          @endif
        </div>
      </div>
    </div>

    {{-- Critères détaillés --}}
    <div class="collapse {{ $nbFiltres ? 'show' : '' }}" id="criteres">
      <div class="row g-3 mt-1">
        <div class="col-xl-6">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-geo-alt" aria-hidden="true"></i> Localité</div>
            @include('partials.localite-selects', [
              'localites' => $localites,
              'requis' => false,
              'valeurs' => ['region_id' => $f['region_id'], 'province_id' => $f['province_id'], 'commune_id' => $f['commune_id']],
              'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
            ])
          </div>
        </div>

        <div class="col-xl-6">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-folder2-open" aria-hidden="true"></i> Dossier</div>
            <div class="row g-2">
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'statut_dossier', 'label' => 'État du dossier', 'options' => Oev::ETATS, 'valeur' => $f['statut_dossier']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'statut', 'label' => 'Statut OEV', 'options' => Oev::STATUTS, 'valeur' => $f['statut']])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'date_champ', 'label' => 'Période sur', 'options' => ExtractionController::DATES, 'valeur' => $f['date_champ'] === 'integre_at' ? null : $f['date_champ'], 'tous' => ExtractionController::DATES['integre_at']])</div>
              <div class="col-6 col-sm-4">
                <label class="form-label small fw-semibold mb-1" for="f-date_du">Du</label>
                <input class="form-control form-control-sm {{ $f['date_du'] ? 'is-filtre' : '' }}" id="f-date_du" name="date_du" type="date" value="{{ $f['date_du'] }}">
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label small fw-semibold mb-1" for="f-date_au">Au</label>
                <input class="form-control form-control-sm {{ $f['date_au'] ? 'is-filtre' : '' }}" id="f-date_au" name="date_au" type="date" value="{{ $f['date_au'] }}">
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-6">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-person" aria-hidden="true"></i> Enfant</div>
            <div class="row g-2">
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'sexe', 'label' => 'Sexe', 'options' => Oev::SEXES, 'valeur' => $f['sexe']])</div>
              <div class="col-6 col-sm-4">
                <label class="form-label small fw-semibold mb-1" for="f-age_min">Âge minimum</label>
                <input class="form-control form-control-sm {{ $f['age_min'] !== null ? 'is-filtre' : '' }}" id="f-age_min" name="age_min" type="number" min="0" max="25" value="{{ $f['age_min'] }}" placeholder="ans">
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label small fw-semibold mb-1" for="f-age_max">Âge maximum</label>
                <input class="form-control form-control-sm {{ $f['age_max'] !== null ? 'is-filtre' : '' }}" id="f-age_max" name="age_max" type="number" min="0" max="25" value="{{ $f['age_max'] }}" placeholder="ans">
              </div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'groupe_population', 'label' => 'Groupe de population', 'options' => Oev::GROUPES_POPULATION, 'valeur' => $f['groupe_population']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'a_acte_naissance', 'label' => 'Acte de naissance', 'options' => $ouiNon, 'valeur' => $f['a_acte_naissance']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'lieu_de_vie', 'label' => 'Lieu de vie', 'options' => Oev::LIEUX_DE_VIE, 'valeur' => $f['lieu_de_vie']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'vulnerabilite', 'label' => 'Situation de vulnérabilité', 'options' => Oev::VULNERABILITES, 'valeur' => $f['vulnerabilite'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'handicap', 'label' => 'Handicap', 'options' => $ouiNon, 'valeur' => $f['handicap']])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'type_handicap', 'label' => 'Type de handicap', 'options' => Oev::TYPES_HANDICAP, 'valeur' => $f['type_handicap']])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'maladie_chronique', 'label' => 'Maladie', 'options' => $ouiNon, 'valeur' => $f['maladie_chronique']])</div>
            </div>
          </div>
        </div>

        <div class="col-xl-6">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-people" aria-hidden="true"></i> Parents, tuteur et ménage</div>
            <div class="row g-2">
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'mere_vivante', 'label' => 'Mère vivante', 'options' => Oev::PARENT_VIVANT, 'valeur' => $f['mere_vivante']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'pere_vivant', 'label' => 'Père vivant', 'options' => Oev::PARENT_VIVANT, 'valeur' => $f['pere_vivant']])</div>
              <div class="col-sm-6">@include('extraction._filtre', ['nom' => 'tuteur_lien', 'label' => 'Qui s’occupe de l’enfant', 'options' => Oev::LIENS_TUTEUR, 'valeur' => $f['tuteur_lien']])</div>
              <div class="col-sm-3">@include('extraction._filtre', ['nom' => 'tuteur_a_cnib', 'label' => 'Tuteur avec CNIB', 'options' => $ouiNon, 'valeur' => $f['tuteur_a_cnib']])</div>
              <div class="col-sm-3">@include('extraction._filtre', ['nom' => 'tuteur_pret_continuer', 'label' => 'Prêt à continuer', 'options' => $ouiNon, 'valeur' => $f['tuteur_pret_continuer']])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'source_revenu', 'label' => 'Source de revenu', 'options' => Oev::SOURCES_REVENU, 'valeur' => $f['source_revenu'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'niveau_revenu', 'label' => 'Niveau de revenu', 'options' => Oev::NIVEAUX, 'valeur' => $f['niveau_revenu']])</div>
              <div class="col-sm-4">@include('extraction._filtre', ['nom' => 'logement', 'label' => 'Logement', 'options' => Oev::LOGEMENTS, 'valeur' => $f['logement']])</div>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-mortarboard" aria-hidden="true"></i> Scolarité et formation</div>
            <div class="row g-2">
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'situation_scolaire', 'label' => 'Situation scolaire', 'options' => Oev::SITUATIONS_SCOLAIRES, 'valeur' => $f['situation_scolaire'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'niveau_etude', 'label' => 'Niveau d’étude', 'options' => Oev::NIVEAUX_ETUDE, 'valeur' => $f['niveau_etude']])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'classe', 'label' => 'Classe actuelle', 'options' => Oev::toutesLesClasses(), 'valeur' => $f['classe'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'systeme_educatif', 'label' => 'Système éducatif', 'options' => Oev::SYSTEMES_EDUCATIFS, 'valeur' => $f['systeme_educatif']])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'type_etablissement', 'label' => 'Établissement', 'options' => Oev::TYPES_ETABLISSEMENT, 'valeur' => $f['type_etablissement']])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'appreciation', 'label' => 'Appréciation (année précédente)', 'options' => Oev::APPRECIATIONS, 'valeur' => $f['appreciation'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'performance_scolaire', 'label' => 'Performances', 'options' => Oev::PERFORMANCES_SCOLAIRES, 'valeur' => $f['performance_scolaire'], 'tous' => 'Toutes'])</div>
              <div class="col-sm-6 col-lg-4">@include('extraction._filtre', ['nom' => 'formation_professionnelle', 'label' => 'Formation professionnelle', 'options' => $ouiNon, 'valeur' => $f['formation_professionnelle']])</div>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="ext-groupe">
            <div class="ext-groupe-titre"><i class="bi bi-clipboard-data" aria-hidden="true"></i> Identification du cas</div>
            <div class="row g-2">
              <div class="col-12">@include('extraction._filtre', ['nom' => 'identifie_par', 'label' => 'Identifié par', 'options' => Oev::IDENTIFIE_PAR, 'valeur' => $f['identifie_par']])</div>
              <div class="col-12">@include('extraction._filtre', ['nom' => 'niveau_priorite', 'label' => 'Niveau de priorité', 'options' => Oev::NIVEAUX, 'valeur' => $f['niveau_priorite']])</div>
            </div>
          </div>
        </div>
      </div>

      <div class="ext-actions justify-content-end mt-3">
        @if ($nbFiltres)<a class="btn btn-link" href="{{ route('extraction.index') }}">Réinitialiser les filtres</a>@endif
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Appliquer les filtres</button>
      </div>
    </div>
  </form>

  {{-- Synthèse du résultat --}}
  <section class="row g-3 mt-1" aria-label="Synthèse du résultat">
    @foreach ($stats as [$icone, $libelle, $valeur])
      <div class="col-6 col-md-4 col-xxl-2">
        <div class="ext-stat"><i class="bi {{ $icone }}" aria-hidden="true"></i><div><strong>{{ $valeur }}</strong><span>{{ $libelle }}</span></div></div>
      </div>
    @endforeach
  </section>

  {{-- Résultats --}}
  <section class="panel mt-3">
    @if ($oevs->isEmpty())
      <div class="oev-vide">
        <div class="oev-vide-icone"><i class="bi bi-search" aria-hidden="true"></i></div>
        <h3 class="h5">Aucun dossier ne correspond à ces critères</h3>
        <p class="text-muted mb-0">Retirez ou assouplissez certains filtres.</p>
      </div>
    @else
      <div class="table-responsive">
        <table class="table align-middle mb-0 oev-liste">
          <thead>
            <tr>
              <th>Référence</th><th>Enfant</th><th>Âge</th><th>Statut OEV</th><th>État du dossier</th>
              <th class="d-none d-lg-table-cell">Localité</th><th class="d-none d-xl-table-cell">Scolarité</th>
              <th class="text-end"><span class="visually-hidden">Ouvrir</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($oevs as $oev)
              <tr data-href="{{ route('oevs.show', $oev) }}">
                <td><a class="oev-code {{ $oev->code ? '' : 'oev-code--dossier' }} text-decoration-none" href="{{ route('oevs.show', $oev) }}">{{ $oev->reference() }}</a></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="oev-avatar oev-avatar--{{ $oev->sexe }}" aria-hidden="true">{{ $oev->initiales() }}</span>
                    <span><span class="oev-nom d-block">{{ $oev->nom }}</span><span class="small text-muted">{{ $oev->prenom }}</span></span>
                  </div>
                </td>
                <td class="text-nowrap">{{ $oev->age() }} ans</td>
                <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span></td>
                <td><span class="badge rounded-pill text-bg-{{ $oev->couleurEtat() }}">{{ $oev->libelleEtat() }}</span></td>
                <td class="d-none d-lg-table-cell small text-nowrap" title="{{ $oev->localiteComplete() }}">{{ $oev->commune?->nom }} <span class="text-muted">· {{ $oev->province?->nom }}</span></td>
                <td class="d-none d-xl-table-cell small">
                  {{ $oev->situation_scolaire ? $oev->libelle('situation_scolaire', Oev::SITUATIONS_SCOLAIRES) : '—' }}
                  @if ($oev->classe)<span class="text-muted">· {{ Oev::toutesLesClasses()[$oev->classe] ?? $oev->classe }}</span>@endif
                </td>
                <td class="text-end"><span class="oev-ouvrir"><i class="bi bi-chevron-right" aria-hidden="true"></i></span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-muted">Dossiers {{ $oevs->firstItem() }} à {{ $oevs->lastItem() }} sur {{ $oevs->total() }}</small>
        {{ $oevs->links() }}
      </div>
    @endif
  </section>
</div>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
  // URL plus lisible : les filtres laissés vides ne sont pas envoyés
  document.getElementById('formFiltres').addEventListener('submit', function () {
    this.querySelectorAll('input, select').forEach(function (champ) { if (!champ.value) { champ.disabled = true; } });
  });
</script>
@endpush
