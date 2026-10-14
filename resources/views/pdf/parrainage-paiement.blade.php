@use('App\Support\Montant')
{{-- Liste pour paiement (PDF) : colonnes choisies ($colonnesEtablissement, $colonnesNominatif : clé => libellé) --}}
@php
  $montants = ['montant', 'frais_reels', 'montant_retenu'];
  $valeur = fn ($cle, $v) => in_array($cle, $montants, true) ? Montant::nombre($v) : $v;
  $avantTotaux = array_search('nombre_oev', array_keys($colonnesEtablissement), true);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>{{ $entete['titre'] }}</title>
  <style>
    @page { margin: 24px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e2a22; }
    h1 { font-size: 14px; color: #0d5f35; margin: 0 0 4px; }
    h2 { font-size: 11px; background: #0f7a43; color: #ffffff; padding: 4px 6px; margin: 14px 0 0; }
    .entete p { margin: 0 0 2px; color: #5e6f65; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #edf8f1; text-align: left; padding: 4px; border-bottom: 1px solid #0f7a43; }
    td { padding: 3px 4px; border-bottom: 1px solid #dfe9e1; }
    .nombre { text-align: right; white-space: nowrap; }
    .total td { font-weight: bold; background: #f2f4f3; }
    .general td { font-weight: bold; background: #d9ead3; }
    .rib-manquant { color: #b42318; font-weight: bold; }
    .saut { page-break-before: always; }
  </style>
</head>
<body>
  <div class="entete">
    <h1>{{ $entete['titre'] }}</h1>
    @foreach ($entete['lignes'] as $ligne)<p>{{ $ligne }}</p>@endforeach
  </div>

  <h2>Par établissement</h2>
  <table>
    <thead>
      <tr>@foreach ($colonnesEtablissement as $cle => $libelle)<th @class(['nombre' => in_array($cle, ['nombre_oev', 'montant'], true)])>{{ $libelle }}</th>@endforeach</tr>
    </thead>
    <tbody>
      @forelse ($lignes as $l)
        @if ($l['niveau'] === 'etablissement')
          <tr>
            @foreach ($colonnesEtablissement as $cle => $libelle)
              @if ($cle === 'rib')
                <td>@if ($l['rib_manquant'])<span class="rib-manquant">RIB manquant</span>@else Complet @endif</td>
              @else
                <td @class(['nombre' => in_array($cle, ['nombre_oev', 'montant'], true), 'rib-manquant' => $l['rib_manquant'] && $loop->first && ! isset($colonnesEtablissement['rib'])])>{{ $valeur($cle, $l[$cle]) }}</td>
              @endif
            @endforeach
          </tr>
        @else
          {{-- Sous-total : libellé sur les colonnes qui précèdent le nombre d'OEV --}}
          <tr class="{{ $l['niveau'] === 'general' ? 'general' : 'total' }}">
            @foreach ($colonnesEtablissement as $cle => $libelle)
              @if ($cle === 'nombre_oev')
                <td class="nombre">{{ $l['nombre_oev'] }}</td>
              @elseif ($cle === 'montant')
                <td class="nombre">{{ Montant::nombre($l['montant']) }}</td>
              @elseif ($loop->first)
                <td colspan="{{ max(1, (int) $avantTotaux) }}">{{ $l['libelle'] }}</td>
              @elseif ($loop->index >= $avantTotaux)
                <td></td>
              @endif
            @endforeach
          </tr>
        @endif
      @empty
        <tr><td colspan="{{ count($colonnesEtablissement) }}">Aucune ligne.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2 class="saut">Nominatif</h2>
  <table>
    <thead>
      <tr>@foreach ($colonnesNominatif as $cle => $libelle)<th @class(['nombre' => in_array($cle, $montants, true)])>{{ $libelle }}</th>@endforeach</tr>
    </thead>
    <tbody>
      @forelse ($donnees['nominatif'] as $l)
        <tr>@foreach ($colonnesNominatif as $cle => $libelle)<td @class(['nombre' => in_array($cle, $montants, true)])>{{ $valeur($cle, $l[$cle]) }}</td>@endforeach</tr>
      @empty
        <tr><td colspan="{{ count($colonnesNominatif) }}">Aucune ligne.</td></tr>
      @endforelse
      <tr class="general">
        @foreach ($colonnesNominatif as $cle => $libelle)
          @if ($cle === 'montant_retenu')
            <td class="nombre">{{ Montant::nombre($donnees['total_montant']) }}</td>
          @elseif ($cle === 'frais_reels')
            <td class="nombre">{{ Montant::nombre($donnees['nominatif']->sum('frais_reels')) }}</td>
          @elseif ($loop->first)
            <td>Total général ({{ $donnees['total_oev'] }} OEV)</td>
          @else
            <td></td>
          @endif
        @endforeach
      </tr>
    </tbody>
  </table>
</body>
</html>
