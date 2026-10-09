@use('App\Support\Montant')
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
      <tr>
        <th>Région</th><th>Province</th><th>Établissement</th><th>Type</th><th>Banque</th><th>Code banque</th><th>Code guichet</th>
        <th>N° de compte</th><th>Clé</th><th>Titulaire</th><th class="nombre">OEV</th><th class="nombre">Montant (FCFA)</th><th>RIB</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($lignes as $l)
        @if ($l['niveau'] === 'etablissement')
          <tr>
            <td>{{ $l['region'] }}</td><td>{{ $l['province'] }}</td><td>{{ $l['etablissement'] }}</td><td>{{ $l['type'] }}</td><td>{{ $l['banque'] }}</td>
            <td>{{ $l['code_banque'] }}</td><td>{{ $l['code_guichet'] }}</td><td>{{ $l['numero_compte'] }}</td><td>{{ $l['cle_rib'] }}</td><td>{{ $l['titulaire'] }}</td>
            <td class="nombre">{{ $l['nombre_oev'] }}</td><td class="nombre">{{ Montant::nombre($l['montant']) }}</td>
            <td>@if ($l['rib_manquant'])<span class="rib-manquant">RIB manquant</span>@else Complet @endif</td>
          </tr>
        @else
          <tr class="{{ $l['niveau'] === 'general' ? 'general' : 'total' }}">
            <td colspan="10">{{ $l['libelle'] }}</td>
            <td class="nombre">{{ $l['nombre_oev'] }}</td><td class="nombre">{{ Montant::nombre($l['montant']) }}</td><td></td>
          </tr>
        @endif
      @empty
        <tr><td colspan="13">Aucune ligne.</td></tr>
      @endforelse
    </tbody>
  </table>

  <h2 class="saut">Nominatif</h2>
  <table>
    <thead>
      <tr><th>Établissement</th><th>Nom</th><th>Prénom</th><th>Sexe</th><th>Date de naissance</th><th>Classe ou filière</th><th class="nombre">Frais réels</th><th class="nombre">Montant retenu</th></tr>
    </thead>
    <tbody>
      @forelse ($donnees['nominatif'] as $l)
        <tr>
          <td>{{ $l['etablissement'] }}</td><td>{{ $l['nom'] }}</td><td>{{ $l['prenom'] }}</td><td>{{ $l['sexe'] }}</td><td>{{ $l['date_naissance'] }}</td>
          <td>{{ $l['classe'] }}</td><td class="nombre">{{ Montant::nombre($l['frais_reels']) }}</td><td class="nombre">{{ Montant::nombre($l['montant_retenu']) }}</td>
        </tr>
      @empty
        <tr><td colspan="8">Aucune ligne.</td></tr>
      @endforelse
      <tr class="general">
        <td colspan="6">Total général ({{ $donnees['total_oev'] }} OEV)</td>
        <td class="nombre">{{ Montant::nombre($donnees['nominatif']->sum('frais_reels')) }}</td><td class="nombre">{{ Montant::nombre($donnees['total_montant']) }}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>
