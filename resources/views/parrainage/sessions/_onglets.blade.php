{{-- Onglets d'une session : Informations, Quotas (si actifs), Sélection. Variables : $session, $actif --}}
@php
  $onglets = array_filter([
    'informations' => [route('parrainage.sessions.show', $session), 'bi-info-circle', 'Informations'],
    'quotas' => $session->quotas_actifs ? [route('parrainage.sessions.show', ['session' => $session, 'vue' => 'quotas']), 'bi-pie-chart', 'Quotas'] : null,
    'selection' => [route('parrainage.sessions.selection', $session), 'bi-funnel', 'Sélection des OEV'],
  ]);
@endphp
<nav class="oev-onglets mt-3" aria-label="Vues de la session">
  @foreach ($onglets as $cle => [$url, $icone, $libelle])
    <a class="oev-onglet {{ $actif === $cle ? 'is-active' : '' }}" href="{{ $url }}" @if ($actif === $cle) aria-current="page" @endif>
      <i class="bi {{ $icone }}" aria-hidden="true"></i> {{ $libelle }}
    </a>
  @endforeach
</nav>
