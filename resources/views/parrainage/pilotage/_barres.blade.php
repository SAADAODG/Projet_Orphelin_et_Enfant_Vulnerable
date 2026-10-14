{{--
  Barres horizontales (styles : assets/css/barres.css).
  Variables : $lignes (collection de ['libelle' => …, 'total' => …]), $couleur (primary, success, info…),
              $vide (message si tout vaut 0), $base (facultatif : total de référence → pourcentage affiché)
--}}
@php
  $lignes = collect($lignes);
  $max = max(1, $lignes->max('total'));
  $base ??= null;
@endphp
@if ($lignes->sum('total') === 0)
  <p class="text-muted small mb-0">{{ $vide ?? 'Aucune donnée.' }}</p>
@else
  @foreach ($lignes as $ligne)
    <div @class(['db-barre', 'db-' . $couleur, 'db-barre--pourcent' => $base])>
      <span class="db-barre-libelle" title="{{ $ligne['libelle'] }}">{{ $ligne['libelle'] }}</span>
      <span class="db-piste" role="img" aria-label="{{ $ligne['libelle'] }} : {{ $ligne['total'] }}"><span style="--v: {{ round($ligne['total'] / $max * 100, 1) }}%" @if (! $ligne['total']) data-zero @endif></span></span>
      <span class="db-barre-valeur">
        {{ $ligne['total'] }}
        @if ($base)<small class="db-barre-pourcent">{{ round($ligne['total'] / max(1, $base) * 100) }} %</small>@endif
      </span>
    </div>
  @endforeach
@endif
