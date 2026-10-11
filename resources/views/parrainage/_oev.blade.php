{{--
  Identité d'un OEV dans les listes du parrainage, au style de la « Liste des OEV » : avatar, nom, prénom, code.
  Variables : $oev, $detail (optionnel : texte sous le nom), $nouvelOnglet (optionnel : fiche dans un nouvel onglet)
--}}
<div class="d-flex align-items-center gap-3">
  <span class="oev-avatar oev-avatar--{{ $oev->sexe }}" aria-hidden="true">{{ $oev->initiales() }}</span>
  <span class="text-wrap">
    <a class="oev-nom d-block text-decoration-none text-reset" href="{{ route('oevs.show', $oev) }}" @if ($nouvelOnglet ?? false) target="_blank" rel="noopener" @endif
       aria-label="Ouvrir la fiche de {{ $oev->nomComplet() }}">{{ $oev->nom }} <span class="fw-normal">{{ $oev->prenom }}</span></a>
    <span class="small text-muted">
      <span class="oev-code me-1">{{ $oev->reference() }}</span>{{ $detail ?? '' }}
    </span>
  </span>
</div>
