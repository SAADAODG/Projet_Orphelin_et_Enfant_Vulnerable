<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>{{ $entete['titre'] }}</title>
  <style>
    @page { margin: 24px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e2a22; }
    h1 { font-size: 14px; color: #0d5f35; margin: 0 0 4px; }
    .entete { margin-bottom: 10px; }
    .entete p { margin: 0 0 2px; color: #5e6f65; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0f7a43; color: #ffffff; text-align: left; padding: 4px; }
    td { padding: 3px 4px; border-bottom: 1px solid #dfe9e1; }
  </style>
</head>
<body>
  <div class="entete">
    <h1>{{ $entete['titre'] }}</h1>
    @foreach ($entete['lignes'] as $ligne)<p>{{ $ligne }}</p>@endforeach
    <p>{{ $oevs->count() }} OEV, par ordre de priorité.</p>
  </div>
  <table>
    <thead><tr>@foreach (array_keys($colonnes) as $titre)<th>{{ $titre }}</th>@endforeach</tr></thead>
    <tbody>
      @forelse ($oevs as $oev)
        <tr>@foreach ($colonnes as $titre => $valeur)<td>{{ str_contains($titre, 'FCFA') ? \App\Support\Montant::nombre($valeur($oev)) : $valeur($oev) }}</td>@endforeach</tr>
      @empty
        <tr><td colspan="{{ count($colonnes) }}">Aucun OEV ne correspond aux filtres.</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
