@extends('layouts.app')
@use('App\Models\Oev')
@use('App\Models\SessionParrainage')
@use('App\Models\SuiviParrainage')
@use('App\Http\Controllers\SuiviParrainageController')
@use('App\Services\Parrainage\FicheApresSuivi')
@use('App\Support\Montant')

@section('title', 'Suivi des parrainés | ' . $siteSetting->structure_nom)

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/oev.css') }}">
<style>
  .suivi-filtres { display: flex; flex-wrap: wrap; gap: .5rem; }
  .suivi-filtres .form-select { width: auto; min-width: 9rem; }
  .suivi-form { background: var(--admin-surface-soft); border: 1px solid var(--admin-border); border-radius: 14px; padding: 1rem 1.1rem; }
  .suivi-form .form-label { font-size: .8rem; font-weight: 600; color: var(--admin-muted); margin-bottom: .3rem; }
  .suivi-form .btn-group .btn { font-size: .8rem; }
  .oev-liste tr.suivi-detail > td { background: transparent; border-top: 0; padding-top: 0; }
</style>
@endpush

@php
  $f = $filtres;
  $ligneEnErreur = (int) old('ligne_id');
  $filtreActif = $f['session'] || $f['type'] || $f['q'];
  // Cartes de compteurs : chacune affiche la liste correspondante
  $cartes = [
    ['cle' => null, 'label' => 'OEV parrainés', 'valeur' => $compteurs['total'], 'icone' => 'bi-people', 'style' => ''],
    ['cle' => 'renseigne', 'label' => 'Suivis renseignés', 'valeur' => $compteurs['renseignes'], 'icone' => 'bi-check2-circle', 'style' => 'oev-stat--success'],
    ['cle' => 'a_renseigner', 'label' => 'Suivis à renseigner', 'valeur' => $compteurs['total'] - $compteurs['renseignes'], 'icone' => 'bi-hourglass-split', 'style' => 'oev-stat--warning'],
    ['cle' => 'reconduire', 'label' => 'Recommandés pour un nouveau parrainage', 'valeur' => $compteurs['reconduire'], 'icone' => 'bi-arrow-repeat', 'style' => 'oev-stat--info'],
  ];
  $lien = fn (array $changement) => route('parrainage.suivis.index', array_filter(array_merge($f, $changement), fn ($v) => $v !== null && $v !== ''));
  $optionsClasses = collect(Oev::CLASSES)->mapWithKeys(fn ($classes, $niveau) => [Oev::NIVEAUX_ETUDE[$niveau] => $classes]);
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage</p>
        <h1 class="h3 mb-1">Suivi des parrainés</h1>
        <p class="text-muted mb-0">Résultat de fin d’année et scolarité prévue de chaque OEV parrainé : de quoi préparer les prochains parrainages.</p>
      </div>
    </div>
  </div>

  @if ($annees->isEmpty())
    <section class="panel mt-3">
      <div class="oev-vide">
        <div class="oev-vide-icone"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
        <h2 class="h5">Aucun OEV parrainé pour le moment</h2>
        <p class="text-muted mb-0">Le suivi concerne les OEV retenus sur la liste définitive d’une session validée.</p>
      </div>
    </section>
  @else
    <section class="row g-3 mt-1" aria-label="Avancement du suivi">
      @foreach ($cartes as $carte)
        <div class="col-6 col-xl-3">
          <a class="oev-stat {{ $carte['style'] }} {{ $f['etat'] === $carte['cle'] ? 'is-active' : '' }}" href="{{ $lien(['etat' => $carte['cle']]) }}"
             @if ($f['etat'] === $carte['cle']) aria-current="true" @endif>
            <span class="oev-stat-icone"><i class="bi {{ $carte['icone'] }}" aria-hidden="true"></i></span>
            <span>
              <span class="oev-stat-valeur d-block">{{ $carte['valeur'] }}</span>
              <span class="oev-stat-label">{{ $carte['label'] }}</span>
            </span>
          </a>
        </div>
      @endforeach
    </section>

    <section class="panel mt-3">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1">
            {{ $f['etat'] ? SuiviParrainageController::ETATS[$f['etat']] : 'Tous les OEV parrainés' }} · {{ $f['annee'] }}
            <span class="badge rounded-pill text-bg-light border ms-1">{{ $lignes->total() }}</span>
          </h2>
          <p class="text-muted mb-0">
            @if ($filtreActif)
              Résultats filtrés — <a href="{{ route('parrainage.suivis.index', array_filter(['annee' => $f['annee'], 'etat' => $f['etat']])) }}" class="text-decoration-none">retirer les filtres</a>
            @else
              Les suivis à renseigner apparaissent en premier.{{ $peutRenseigner ? ' Le bouton de chaque ligne ouvre son formulaire.' : '' }}
            @endif
          </p>
        </div>
      </div>

      <form class="oev-toolbar" method="GET" action="{{ route('parrainage.suivis.index') }}" role="search" id="formFiltresSuivi">
        @if ($f['etat'])<input type="hidden" name="etat" value="{{ $f['etat'] }}">@endif
        <div class="oev-recherche">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input class="form-control" name="q" type="search" value="{{ $f['q'] }}" placeholder="Code OEV, nom, prénom…" aria-label="Rechercher un OEV parrainé">
        </div>
        <div class="suivi-filtres">
          <select class="form-select" name="annee" aria-label="Année scolaire">
            @foreach ($annees as $annee)<option value="{{ $annee }}" @selected($f['annee'] === $annee)>{{ $annee }}</option>@endforeach
          </select>
          <select class="form-select" name="session" aria-label="Session">
            <option value="">Toutes les sessions</option>
            @foreach ($sessions as $s)<option value="{{ $s->id }}" @selected($f['session'] === $s->id)>Session n°{{ $s->numero }}</option>@endforeach
          </select>
          <select class="form-select" name="type" aria-label="Type d’appui">
            <option value="">Tous les appuis</option>
            <option value="{{ SessionParrainage::TYPE_SCOLAIRE }}" @selected($f['type'] === SessionParrainage::TYPE_SCOLAIRE)>Scolaire</option>
            <option value="{{ SessionParrainage::TYPE_FORMATION }}" @selected($f['type'] === SessionParrainage::TYPE_FORMATION)>Formation professionnelle</option>
          </select>
        </div>
        <noscript><button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button></noscript>
      </form>

      @if ($lignes->isEmpty())
        <div class="oev-vide">
          <div class="oev-vide-icone"><i class="bi {{ $filtreActif ? 'bi-search' : 'bi-check2-all' }}" aria-hidden="true"></i></div>
          <h3 class="h5">{{ $filtreActif ? 'Aucun OEV parrainé ne correspond à votre recherche' : ($f['etat'] === 'a_renseigner' ? 'Tous les suivis sont renseignés' : 'Aucun OEV dans cette liste') }}</h3>
        </div>
      @else
        <div class="table-responsive">
          <table class="table align-middle mb-0 oev-liste">
            <thead>
              <tr>
                <th>OEV</th><th>Statut</th><th class="d-none d-lg-table-cell">Appui</th><th class="d-none d-md-table-cell">Scolarité / formation</th>
                <th>Suivi {{ $f['annee'] }}</th><th class="text-end"><span class="visually-hidden">Action</span></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($lignes as $ligne)
                @php
                  $oev = $ligne->oev;
                  $suivi = $ligne->suivi;
                  $formation = $ligne->type_appui === SessionParrainage::TYPE_FORMATION;
                  $ouvert = $ligneEnErreur === $ligne->id;
                  $valeur = fn (string $champ, $defaut = null) => $ouvert ? old($champ, $defaut) : $defaut;
                  // Année suivie : la scolarité de la fiche avant le suivi (la fiche, elle, est passée à l'année suivante)
                  $avant = array_merge(FicheApresSuivi::photographier($oev), $suivi?->fiche_avant ?? []);
                  $classeSuivie = $avant['classe'];
                  $bareme = Oev::baremeMoyenne($classeSuivie);
                @endphp
                <tr>
                  <td>@include('parrainage._oev', ['oev' => $oev, 'detail' => '· ' . $oev->age() . ' ans · ' . ($oev->commune?->nom ?? $oev->province?->nom)])</td>
                  <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span></td>
                  <td class="d-none d-lg-table-cell small">
                    {{ $formation ? 'Formation' : 'Scolaire' }}
                    <a class="d-block text-muted text-decoration-none" href="{{ route('parrainage.sessions.show', $ligne->session) }}">Session n°{{ $ligne->session->numero }}</a>
                  </td>
                  <td class="d-none d-md-table-cell small">
                    @if ($formation)
                      {{ $avant['formation_filiere'] ?: 'Formation professionnelle' }}
                    @else
                      {{ $classeSuivie ? (Oev::toutesLesClasses()[$classeSuivie] ?? $classeSuivie) : (Oev::SITUATIONS_SCOLAIRES[$avant['situation_scolaire']] ?? '—') }}
                      @if ($avant['etablissement_actuel'])<span class="d-block text-muted">{{ $avant['etablissement_actuel'] }}</span>@endif
                    @endif
                  </td>
                  <td class="small">
                    @if ($suivi)
                      <span class="badge rounded-pill text-bg-{{ $suivi->couleur() }}">{{ $suivi->libelleResultat() }}</span>
                      @if ($suivi->moyenne !== null)<span class="ms-1">{{ str_replace('.', ',', (string) (float) $suivi->moyenne) }}/{{ $bareme }}</span>@endif
                      @if ($suivi->insertion)<span class="ms-1">· {{ SuiviParrainage::INSERTIONS[$suivi->insertion] }}</span>@endif
                      @if ($suivi->libelleProchaineAnnee())<span class="d-block"><i class="bi bi-arrow-right-short" aria-hidden="true"></i>L’an prochain : <strong>{{ $suivi->libelleProchaineAnnee() }}</strong></span>@endif
                      <span class="d-block {{ $suivi->a_reconduire ? 'text-success' : 'text-muted' }}">
                        <i class="bi {{ $suivi->a_reconduire ? 'bi-arrow-repeat' : 'bi-dash-circle' }}" aria-hidden="true"></i>
                        {{ $suivi->a_reconduire ? 'Recommandé pour un nouveau parrainage' : 'Non recommandé' }}
                      </span>
                    @else
                      <span class="badge rounded-pill text-bg-light border text-muted"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>À renseigner</span>
                    @endif
                  </td>
                  <td class="text-end">
                    @if ($peutRenseigner)
                      <button class="btn btn-sm {{ $suivi ? 'btn-outline-secondary' : 'btn-primary' }} text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#suivi-{{ $ligne->id }}"
                              aria-expanded="{{ $ouvert ? 'true' : 'false' }}" aria-controls="suivi-{{ $ligne->id }}">
                        <i class="bi {{ $suivi ? 'bi-pencil' : 'bi-journal-plus' }}" aria-hidden="true"></i> {{ $suivi ? 'Modifier' : 'Renseigner' }}
                      </button>
                    @else
                      <a class="oev-ouvrir" href="{{ route('oevs.show', $oev) }}" aria-label="Ouvrir la fiche"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    @endif
                  </td>
                </tr>
                @if ($peutRenseigner)
                  <tr class="collapse suivi-detail {{ $ouvert ? 'show' : '' }}" id="suivi-{{ $ligne->id }}">
                    <td colspan="6">
                      @php
                        $classes = Oev::toutesLesClasses();
                        $classeSuperieure = SuiviParrainage::classeSuivante($classeSuivie, 'admis');
                        $classeConnue = $classeSuperieure !== null;
                        $orientation = $valeur('orientation', $suivi?->orientation);
                        // Détails de l'année prochaine : ceux déjà saisis, sinon ceux de l'année suivie
                        $details = $suivi?->details_prochaine_annee ?? [];
                        $montant = fn ($n) => $n === null || $n === '' ? '' : Montant::nombre((int) $n);
                        $ecole = $suivi?->orientation === SuiviParrainage::ECOLE
                            ? ['etab' => $details['etablissement'] ?? null, 'type' => $details['type_etablissement'] ?? null, 'frais' => $details['frais'] ?? null]
                            : ['etab' => $avant['etablissement_actuel'], 'type' => $avant['type_etablissement'], 'frais' => $avant['frais_scolarite']];
                        $versFormation = $suivi?->orientation === SuiviParrainage::FORMATION ? $details : [];
                        // Passage dans un autre cycle (CM2 → 6e…) : nouvel établissement, nouveaux frais
                        $changeCycle = $classeConnue && Oev::niveauDeLaClasse($classeSuperieure) !== Oev::niveauDeLaClasse($classeSuivie);
                      @endphp
                      <form class="suivi-form" method="POST" action="{{ route('parrainage.suivis.enregistrer', $ligne) }}" data-suivi
                            data-classe-superieure="{{ $classes[$classeSuperieure] ?? '' }}" data-classe-actuelle="{{ $classes[$classeSuivie] ?? '' }}"
                            data-change-cycle="{{ $changeCycle ? '1' : '0' }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="ligne_id" value="{{ $ligne->id }}">
                        <div class="row g-3 align-items-end">
                          <div class="col-12 col-xl-auto">
                            <span class="form-label d-block">Résultat de l’année <span class="text-danger">*</span></span>
                            <div class="btn-group flex-wrap" role="group" aria-label="Résultat">
                              @foreach (SuiviParrainage::resultatsPour($ligne->type_appui) as $code => $libelle)
                                <input type="radio" class="btn-check" name="resultat" id="r-{{ $ligne->id }}-{{ $code }}" value="{{ $code }}" autocomplete="off"
                                       @checked($valeur('resultat', $suivi?->resultat) === $code) required>
                                <label class="btn btn-outline-{{ SuiviParrainage::COULEURS[$code] ?? 'secondary' }}" for="r-{{ $ligne->id }}-{{ $code }}">{{ $libelle }}</label>
                              @endforeach
                            </div>
                          </div>
                          @if ($formation)
                            <div class="col-sm-6 col-xl-3" data-insertion @if ($valeur('resultat', $suivi?->resultat) !== 'achevee') hidden @endif>
                              <label class="form-label" for="i-{{ $ligne->id }}">Après la formation <span class="text-danger">*</span></label>
                              <select class="form-select form-select-sm" id="i-{{ $ligne->id }}" name="insertion">
                                <option value="">Choisir</option>
                                @foreach (SuiviParrainage::INSERTIONS as $code => $libelle)
                                  <option value="{{ $code }}" @selected($valeur('insertion', $suivi?->insertion) === $code)>{{ $libelle }}</option>
                                @endforeach
                              </select>
                            </div>
                            <div class="col-sm-6 col-xl-3" data-champ-poursuit @if ($valeur('resultat', $suivi?->resultat) !== 'poursuit') hidden @endif>
                              <label class="form-label" for="ff-{{ $ligne->id }}">Frais de formation l’année prochaine (FCFA) <span class="text-danger">*</span></label>
                              <input class="form-control form-control-sm text-end" id="ff-{{ $ligne->id }}" name="prochains_frais" type="text" inputmode="numeric" data-requis
                                     value="{{ $valeur('prochains_frais', $montant($details['frais'] ?? $avant['frais_formation'])) }}" placeholder="0 si gratuite">
                            </div>
                            <div class="col-12 col-xl small text-muted">
                              <i class="bi bi-info-circle" aria-hidden="true"></i>
                              L’année prochaine est déduite du résultat : il poursuit {{ $avant['formation_filiere'] ? '« ' . $avant['formation_filiere'] . ' »' : 'sa formation' }}, l’a achevée, ou arrête. La fiche de l’OEV est mise à jour ; l’année suivie reste dans son parcours.
                            </div>
                          @else
                            <div class="col-6 col-sm-4 col-xl-2">
                              <label class="form-label" for="m-{{ $ligne->id }}">Moyenne <span class="fw-normal">(facultatif)</span></label>
                              <div class="input-group input-group-sm">
                                <input class="form-control" id="m-{{ $ligne->id }}" name="moyenne" type="text" inputmode="decimal"
                                       value="{{ $valeur('moyenne', $suivi?->moyenne === null ? null : str_replace('.', ',', (string) (float) $suivi->moyenne)) }}" placeholder="Ex : 12,5">
                                <span class="input-group-text">/{{ $bareme }}</span>
                              </div>
                            </div>
                            {{-- L'année prochaine : indépendante du résultat (un admis peut partir en formation professionnelle) --}}
                            <div class="col-12 col-xl-auto">
                              <span class="form-label d-block">L’année prochaine <span class="text-danger">*</span></span>
                              <div class="btn-group flex-wrap" role="group" aria-label="L’année prochaine">
                                @foreach (SuiviParrainage::ORIENTATIONS as $code => $libelle)
                                  <input type="radio" class="btn-check" name="orientation" id="or-{{ $ligne->id }}-{{ $code }}" value="{{ $code }}" autocomplete="off"
                                         @checked($orientation === $code) required data-orientation>
                                  <label class="btn btn-outline-secondary" for="or-{{ $ligne->id }}-{{ $code }}">{{ $libelle }}</label>
                                @endforeach
                              </div>
                              {{-- Classe calculée : supérieure si admis, la même sinon --}}
                              <span class="d-block small text-primary fw-semibold mt-1" data-classe-auto @if ($orientation !== SuiviParrainage::ECOLE || ! $classeConnue) hidden @endif></span>
                            </div>
                            {{-- Continue l'école : établissement et frais de l'année prochaine --}}
                            <div class="w-100 d-none d-xl-block" aria-hidden="true"></div>
                            <div class="col-sm-6 col-xl-4" data-champ-ecole @if ($orientation !== SuiviParrainage::ECOLE) hidden @endif>
                              <label class="form-label" for="pe-{{ $ligne->id }}">Établissement l’année prochaine</label>
                              <input class="form-control form-control-sm" id="pe-{{ $ligne->id }}" name="prochain_etablissement" type="text" maxlength="255" data-defaut="{{ $ecole['etab'] }}"
                                     value="{{ $valeur('prochain_etablissement', $ecole['etab']) }}" placeholder="Nom de l’établissement">
                            </div>
                            <div class="col-6 col-sm-3 col-xl-2" data-champ-ecole @if ($orientation !== SuiviParrainage::ECOLE) hidden @endif>
                              <label class="form-label" for="pt-{{ $ligne->id }}">Public / privé</label>
                              <select class="form-select form-select-sm" id="pt-{{ $ligne->id }}" name="prochain_type_etablissement" data-defaut="{{ $ecole['type'] }}">
                                <option value="">—</option>
                                @foreach (Oev::TYPES_ETABLISSEMENT as $code => $libelle)
                                  <option value="{{ $code }}" @selected($valeur('prochain_type_etablissement', $ecole['type']) === $code)>{{ $libelle }}</option>
                                @endforeach
                              </select>
                            </div>
                            <div class="col-6 col-sm-3 col-xl-3" data-champ-ecole @if ($orientation !== SuiviParrainage::ECOLE) hidden @endif>
                              <label class="form-label" for="ps-{{ $ligne->id }}">Frais de scolarité (FCFA) <span class="text-danger">*</span></label>
                              <input class="form-control form-control-sm text-end" id="ps-{{ $ligne->id }}" name="prochains_frais" type="text" inputmode="numeric" data-requis data-defaut="{{ $montant($ecole['frais']) }}"
                                     value="{{ $valeur('prochains_frais', $montant($ecole['frais'])) }}" placeholder="0 si gratuite">
                            </div>

                            {{-- Part en formation professionnelle : filière, centre, durée, frais --}}
                            <div class="col-sm-6 col-xl-3" data-champ-filiere @if ($orientation !== SuiviParrainage::FORMATION) hidden @endif>
                              <label class="form-label" for="pf-{{ $ligne->id }}">Formation choisie <span class="text-danger">*</span></label>
                              <input class="form-control form-control-sm" id="pf-{{ $ligne->id }}" name="prochaine_filiere" type="text" maxlength="150" data-requis
                                     value="{{ $valeur('prochaine_filiere', $suivi?->orientation === SuiviParrainage::FORMATION ? $suivi->prochaine_annee : null) }}" placeholder="Ex : Couture, mécanique…">
                            </div>
                            <div class="col-6 col-sm-3 col-xl-2" data-champ-filiere @if ($orientation !== SuiviParrainage::FORMATION) hidden @endif>
                              <label class="form-label" for="pcc-{{ $ligne->id }}">Centre <span class="text-danger">*</span></label>
                              <select class="form-select form-select-sm" id="pcc-{{ $ligne->id }}" name="prochain_type_centre" data-requis>
                                <option value="">Choisir</option>
                                @foreach (Oev::TYPES_ETABLISSEMENT as $code => $libelle)
                                  <option value="{{ $code }}" @selected($valeur('prochain_type_centre', $versFormation['type_centre'] ?? null) === $code)>{{ $libelle }}</option>
                                @endforeach
                              </select>
                            </div>
                            <div class="col-6 col-sm-3 col-xl-2" data-champ-filiere @if ($orientation !== SuiviParrainage::FORMATION) hidden @endif>
                              <label class="form-label" for="pd-{{ $ligne->id }}">Durée (mois) <span class="text-danger">*</span></label>
                              <input class="form-control form-control-sm" id="pd-{{ $ligne->id }}" name="prochaine_duree" type="number" min="1" max="120" data-requis
                                     value="{{ $valeur('prochaine_duree', $versFormation['duree_mois'] ?? null) }}" placeholder="Ex : 24">
                            </div>
                            <div class="col-6 col-sm-3 col-xl-3" data-champ-filiere @if ($orientation !== SuiviParrainage::FORMATION) hidden @endif>
                              <label class="form-label" for="pff-{{ $ligne->id }}">Frais de formation (FCFA) <span class="text-danger">*</span></label>
                              <input class="form-control form-control-sm text-end" id="pff-{{ $ligne->id }}" name="prochains_frais_formation" type="text" inputmode="numeric" data-requis
                                     value="{{ $valeur('prochains_frais_formation', $montant($versFormation['frais'] ?? null)) }}" placeholder="0 si gratuite">
                            </div>
                            @unless ($classeConnue)
                              {{-- Classe actuelle inconnue (ex. enfant inscrit grâce au parrainage) : la classe est saisie --}}
                              <div class="col-sm-6 col-xl-2" data-champ-classe @if ($orientation !== SuiviParrainage::ECOLE) hidden @endif>
                                <label class="form-label" for="pc-{{ $ligne->id }}">Classe l’année prochaine <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="pc-{{ $ligne->id }}" name="prochaine_classe">
                                  <option value="">Choisir</option>
                                  @foreach ($optionsClasses as $niveau => $classesNiveau)
                                    <optgroup label="{{ $niveau }}">
                                      @foreach ($classesNiveau as $code => $libelle)
                                        <option value="{{ $code }}" @selected($valeur('prochaine_classe', $suivi?->orientation === SuiviParrainage::ECOLE ? $suivi->prochaine_annee : null) === $code)>{{ $libelle }}</option>
                                      @endforeach
                                    </optgroup>
                                  @endforeach
                                </select>
                              </div>
                            @endunless
                          @endif
                          <div class="col-12 col-xl-auto">
                            <span class="form-label d-block">Nouveau parrainage ? <span class="text-danger">*</span></span>
                            <div class="btn-group" role="group" aria-label="Recommandé pour un nouveau parrainage">
                              @php
                                $reconduire = $valeur('a_reconduire', $suivi === null ? null : ($suivi->a_reconduire ? '1' : '0'));
                              @endphp
                              <input type="radio" class="btn-check" name="a_reconduire" id="a-{{ $ligne->id }}-1" value="1" autocomplete="off" @checked((string) $reconduire === '1') required data-reconduire>
                              <label class="btn btn-outline-success" for="a-{{ $ligne->id }}-1">Recommandé</label>
                              <input type="radio" class="btn-check" name="a_reconduire" id="a-{{ $ligne->id }}-0" value="0" autocomplete="off" @checked((string) $reconduire === '0') data-reconduire>
                              <label class="btn btn-outline-secondary" for="a-{{ $ligne->id }}-0">Non</label>
                            </div>
                          </div>
                          <div class="col-12 col-md">
                            <label class="form-label" for="o-{{ $ligne->id }}">Observation <span class="fw-normal">(facultatif)</span></label>
                            <input class="form-control form-control-sm" id="o-{{ $ligne->id }}" name="observation" type="text" maxlength="1000"
                                   value="{{ $valeur('observation', $suivi?->observation) }}" placeholder="Ex : très assidu, besoin de fournitures…">
                          </div>
                          <div class="col-auto">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer</button>
                          </div>
                        </div>
                        @if ($ouvert && $errors->any())
                          <div class="text-danger small mt-2">{{ implode(' ', $errors->all()) }}</div>
                        @endif
                        @if ($suivi)
                          <p class="small text-muted mt-2 mb-0">Renseigné par {{ $suivi->auteur?->name ?? '—' }} le {{ $suivi->updated_at->format('d/m/Y') }}.</p>
                        @endif
                      </form>
                    </td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <small class="text-muted">Affichage de {{ $lignes->firstItem() }} à {{ $lignes->lastItem() }} sur {{ $lignes->total() }} OEV parrainé(s)</small>
          {{ $lignes->links() }}
        </div>
      @endif
    </section>
  @endif
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Filtres appliqués automatiquement (listes : tout de suite ; recherche : après une pause de frappe)
    var filtres = document.getElementById('formFiltresSuivi');
    if (filtres) {
      var minuterie;
      var envoyer = function () { filtres.requestSubmit ? filtres.requestSubmit() : filtres.submit(); };
      filtres.addEventListener('submit', function () {
        filtres.querySelectorAll('input, select').forEach(function (c) { if (!c.value) { c.disabled = true; } });
      });
      filtres.addEventListener('change', function (e) {
        if (e.target.name === 'annee') { var s = filtres.querySelector('[name="session"]'); if (s) { s.value = ''; } }
        envoyer();
      });
      filtres.addEventListener('input', function (e) {
        if (e.target.type !== 'search') { return; }
        clearTimeout(minuterie);
        minuterie = setTimeout(envoyer, 700);
      });
    }

    // Formulaire de suivi : propositions d'après le résultat et l'orientation, tant que l'agent n'a pas choisi lui-même
    document.querySelectorAll('[data-suivi]').forEach(function (form) {
      var choix = function (nom) { var r = form.querySelector('[name="' + nom + '"]:checked'); return r ? r.value : null; };
      var reconduireChoisi = !!choix('a_reconduire');
      var orientationChoisie = !!choix('orientation');
      var classeAuto = form.querySelector('[data-classe-auto]');
      var champFiliere = form.querySelector('[data-champ-filiere]');
      var champClasse = form.querySelector('[data-champ-classe]');

      var proposerReconduction = function () {
        if (reconduireChoisi) { return; }
        var resultat = choix('resultat');
        var orientation = choix('orientation');
        // Oui si l'OEV continue (école ou formation) ; non s'il arrête ou a achevé sa formation
        var oui = orientation ? orientation !== 'arret' : ['admis', 'redouble', 'poursuit'].indexOf(resultat) !== -1;
        if (resultat || orientation) { form.querySelector('[data-reconduire][value="' + (oui ? '1' : '0') + '"]').checked = true; }
      };

      // Champs de l'année prochaine : classe calculée, formation choisie, ou classe à saisir si elle est inconnue
      var majAnneeProchaine = function (initial) {
        var resultat = choix('resultat');
        var orientation = choix('orientation');
        if (classeAuto) {
          var superieure = form.dataset.classeSuperieure;
          var actuelle = form.dataset.classeActuelle;
          var texte = resultat === 'admis' ? 'Passe en ' + superieure : (resultat === 'redouble' ? 'Redouble la ' + actuelle : 'Reprend l’école en ' + actuelle);
          classeAuto.innerHTML = '<i class="bi bi-arrow-right-short" aria-hidden="true"></i>' + texte;
          classeAuto.hidden = orientation !== 'ecole' || !resultat || !superieure;
        }
        // Groupes de champs : visibles (et obligatoires) seulement pour le choix fait ; masqués, ils ne sont pas envoyés
        var basculer = function (selecteur, visible) {
          form.querySelectorAll(selecteur).forEach(function (groupe) {
            groupe.hidden = !visible;
            groupe.querySelectorAll('input, select').forEach(function (c) {
              c.disabled = !visible;
              c.required = visible && c.hasAttribute('data-requis');
            });
          });
        };
        basculer('[data-champ-ecole]', orientation === 'ecole');
        basculer('[data-champ-filiere]', orientation === 'formation');
        basculer('[data-champ-classe]', orientation === 'ecole');
        basculer('[data-champ-poursuit]', resultat === 'poursuit');
        if (champClasse) { champClasse.querySelector('select').required = orientation === 'ecole'; }

        // Changement de cycle (CM2 → 6e…) : l'établissement et les frais sont à ressaisir, sauf s'ils ont été modifiés
        form.querySelectorAll('[data-champ-ecole] [data-defaut]').forEach(function (c) {
          if (initial || c.dataset.modifie) { return; }
          c.value = resultat === 'admis' && form.dataset.changeCycle === '1' ? '' : c.dataset.defaut;
        });
      };
      form.querySelectorAll('[data-champ-ecole] [data-defaut]').forEach(function (c) {
        c.addEventListener('input', function () { c.dataset.modifie = '1'; });
        c.addEventListener('change', function () { c.dataset.modifie = '1'; });
      });

      form.querySelectorAll('[data-reconduire]').forEach(function (r) { r.addEventListener('change', function () { reconduireChoisi = true; }); });
      form.querySelectorAll('[data-orientation]').forEach(function (r) {
        r.addEventListener('change', function () { orientationChoisie = true; majAnneeProchaine(); proposerReconduction(); });
      });
      form.querySelectorAll('[name="resultat"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          var resultat = radio.value;
          // « Après la formation » : seulement si elle est achevée
          var insertion = form.querySelector('[data-insertion]');
          if (insertion) {
            insertion.hidden = resultat !== 'achevee';
            insertion.querySelector('select').required = resultat === 'achevee';
          }
          // Orientation proposée : l'école s'il est admis ou redouble, l'arrêt s'il a abandonné (modifiable)
          if (!orientationChoisie && form.querySelector('[data-orientation]')) {
            form.querySelector('[data-orientation][value="' + (resultat === 'abandon' ? 'arret' : 'ecole') + '"]').checked = true;
          }
          majAnneeProchaine();
          proposerReconduction();
        });
      });
      majAnneeProchaine(true);
    });
  });
</script>
@endpush
