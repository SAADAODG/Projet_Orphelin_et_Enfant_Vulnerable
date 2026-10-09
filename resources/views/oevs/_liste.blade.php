{{--
  Liste commune aux écrans du circuit des dossiers.
  Variables : $oevs, $etats, $compteurs, $filtres (fournis par le contrôleur), $lienVide (optionnel : url, label, icone)
              $route         nom de la route de la page (filtres et onglets)
              $carteTous     libellé d'une carte « tous les états », ou null si la page n'en a pas
              $messageVide   texte affiché quand l'onglet est vide
--}}
@use('App\Models\Oev')

@php
  $iconesEtats = [
    Oev::ETAT_BROUILLON => 'bi-pencil',
    Oev::ETAT_SOUMIS => 'bi-send',
    Oev::ETAT_NON_CONFORME => 'bi-x-octagon',
    Oev::ETAT_VALIDE => 'bi-patch-check',
    Oev::ETAT_COMPLEMENT => 'bi-arrow-repeat',
    Oev::ETAT_INTEGRE => 'bi-person-check',
    Oev::ETAT_REJETE => 'bi-slash-circle',
  ];
  $stylesEtats = [
    Oev::ETAT_BROUILLON => '',
    Oev::ETAT_SOUMIS => 'oev-stat--info',
    Oev::ETAT_NON_CONFORME => 'oev-stat--danger',
    Oev::ETAT_VALIDE => '',
    Oev::ETAT_COMPLEMENT => 'oev-stat--warning',
    Oev::ETAT_INTEGRE => 'oev-stat--success',
    Oev::ETAT_REJETE => 'oev-stat--rejete',
  ];
  $filtreActif = $filtres['statut'] || $filtres['recherche'] !== '';
  $cartes = collect($etats)->map(fn ($e) => ['cle' => $e, 'label' => Oev::ETATS[$e], 'valeur' => $compteurs[$e] ?? 0, 'icone' => $iconesEtats[$e], 'style' => $stylesEtats[$e]]);
  if ($carteTous) {
    $cartes->prepend(['cle' => null, 'label' => $carteTous, 'valeur' => $compteurs->sum(), 'icone' => 'bi-folder2-open', 'style' => '']);
  }
  $colonnes = $cartes->count() >= 5 ? 'col-6 col-md-4 col-xxl' : 'col-6 col-xl';
@endphp

<section class="row g-3 mt-1 {{ $cartes->count() >= 5 ? 'oev-stats--compact' : '' }}" aria-label="Dossiers par état">
  @foreach ($cartes as $carte)
    <div class="{{ $colonnes }}">
      <a class="oev-stat {{ $carte['style'] }} {{ $filtres['etat'] === $carte['cle'] ? 'is-active' : '' }}"
         href="{{ route($route, ['etat' => $carte['cle'] ?? 'tous']) }}"
         @if ($filtres['etat'] === $carte['cle']) aria-current="true" @endif>
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
        {{ $filtres['etat'] ? Oev::ETATS[$filtres['etat']] : 'Tous les dossiers' }}
        <span class="badge rounded-pill text-bg-light border ms-1">{{ $oevs->total() }}</span>
      </h2>
      <p class="text-muted mb-0">
        @if ($filtreActif)
          Résultats filtrés — <a href="{{ route($route, array_filter(['etat' => $filtres['etat']])) }}" class="text-decoration-none">retirer les filtres</a>
        @else
          Cliquez sur un dossier pour ouvrir la fiche complète de l’enfant.
        @endif
      </p>
    </div>
  </div>

  <form class="oev-toolbar" method="GET" action="{{ route($route) }}" role="search">
    @if ($filtres['etat'])<input type="hidden" name="etat" value="{{ $filtres['etat'] }}">@endif
    <div class="oev-recherche">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input class="form-control" name="q" type="search" value="{{ $filtres['recherche'] }}" placeholder="N° de dossier, code OEV, nom, prénom, tuteur, localité…" aria-label="Rechercher un dossier">
    </div>
    <select class="form-select" name="statut" aria-label="Filtrer par statut de l’enfant" onchange="this.form.submit()">
      <option value="">Tous les statuts</option>
      @foreach (Oev::STATUTS as $cle => $label)
        <option value="{{ $cle }}" @selected($filtres['statut'] === $cle)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
  </form>

  @if ($oevs->isEmpty())
    <div class="oev-vide">
      <div class="oev-vide-icone"><i class="bi {{ $filtreActif ? 'bi-search' : 'bi-inbox' }}" aria-hidden="true"></i></div>
      @if ($filtreActif)
        <h3 class="h5">Aucun dossier ne correspond à votre recherche</h3>
        <p class="text-muted">Essayez d’autres mots-clés ou retirez les filtres.</p>
      @else
        <h3 class="h5">{{ $messageVide }}</h3>
        @isset($lienVide)
          <a class="btn btn-primary mt-2" href="{{ $lienVide['url'] }}"><i class="bi {{ $lienVide['icone'] }}" aria-hidden="true"></i> {{ $lienVide['label'] }}</a>
        @endisset
      @endif
    </div>
  @else
    <div class="table-responsive">
      <table class="table align-middle mb-0 oev-liste">
        <thead>
          <tr>
            <th>Référence</th><th>Nom</th><th class="d-none d-md-table-cell">Prénom(s)</th><th>Statut</th><th>Dossier</th>
            <th class="text-end"><span class="visually-hidden">Ouvrir</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($oevs as $oev)
            <tr data-href="{{ route('oevs.show', $oev) }}">
              <td>
                <a class="oev-code text-decoration-none {{ $oev->code ? '' : 'oev-code--dossier' }}" href="{{ route('oevs.show', $oev) }}" aria-label="Ouvrir le dossier de {{ $oev->nomComplet() }}">{{ $oev->reference() }}</a>
              </td>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <span class="oev-avatar oev-avatar--{{ $oev->sexe }}" aria-hidden="true">{{ $oev->initiales() }}</span>
                  <span>
                    <span class="oev-nom d-block">{{ $oev->nom }}</span>
                    <span class="d-md-none small text-muted">{{ $oev->prenom }}</span>
                  </span>
                </div>
              </td>
              <td class="d-none d-md-table-cell">{{ $oev->prenom }}</td>
              <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', Oev::STATUTS) }}</span></td>
              <td>
                <span class="badge rounded-pill text-bg-{{ $oev->couleurEtat() }}">{{ $oev->libelleEtat() }}</span>
                @if ($oev->estDesactive())
                  <span class="badge rounded-pill text-bg-dark" title="{{ $oev->libelleMotifDesactivation() }}">Désactivé</span>
                @elseif ($oev->desactivationDemandee())
                  <span class="badge rounded-pill text-bg-warning">Désactivation demandée</span>
                @endif
                @if ($oev->estModifiable())
                  @if ($oev->estComplet())
                    <small class="d-block text-success fw-semibold mt-1"><i class="bi bi-send" aria-hidden="true"></i> Prêt à soumettre au DR</small>
                  @else
                    <small class="d-block text-muted mt-1">{{ count($oev->piecesFournies()) }}/{{ count($oev->piecesRequises()) }} pièce(s)</small>
                  @endif
                @endif
              </td>
              <td class="text-end"><span class="oev-ouvrir"><i class="bi bi-chevron-right" aria-hidden="true"></i></span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <small class="text-muted">Affichage de {{ $oevs->firstItem() }} à {{ $oevs->lastItem() }} sur {{ $oevs->total() }} dossier(s)</small>
      {{ $oevs->links() }}
    </div>
  @endif
</section>

@push('scripts')
<script>
  // Toute la ligne ouvre la fiche (la référence reste un vrai lien pour le clavier et le clic molette)
  document.querySelectorAll('.oev-liste tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (e) {
      if (e.target.closest('a')) { return; }
      if (e.ctrlKey || e.metaKey) { window.open(ligne.dataset.href, '_blank'); } else { window.location = ligne.dataset.href; }
    });
  });
</script>
@endpush
