@extends('layouts.app')

@section('title', 'Importer des appuis | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Parrainage · Appuis</p>
        <h1 class="h3 mb-1">Importer des appuis</h1>
        <p class="text-muted mb-0">Enregistrez plusieurs appuis à partir du modèle Excel. Chaque ligne est contrôlée comme une saisie unitaire.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('parrainage.appuis.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour aux appuis</a>
    </div>
  </div>

  <section class="row g-3 mt-1">
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <h2 class="h5 section-title"><i class="bi bi-1-circle" aria-hidden="true"></i><span>Télécharger le modèle</span></h2>
        <p class="text-muted">Le modèle contient la feuille « Appuis » à remplir (à partir de la ligne 3) et les listes des parrains actifs et des natures d’appui avec leurs codes.</p>
        <ul class="small text-muted">
          <li>L’OEV est désigné par son code (ex : OEV-2026-0001) ; il doit être intégré et dans votre zone.</li>
          <li>Si l’OEV a déjà un appui de même nature la même année, la ligne est rejetée sauf si la colonne « motif_doublon » est renseignée (RG-01).</li>
        </ul>
        <a class="btn btn-outline-primary" href="{{ route('parrainage.appuis.modele') }}"><i class="bi bi-download" aria-hidden="true"></i> Modèle Excel</a>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <form class="panel h-100" method="POST" action="{{ route('parrainage.appuis.import.store') }}" enctype="multipart/form-data">
        @csrf
        <h2 class="h5 section-title"><i class="bi bi-2-circle" aria-hidden="true"></i><span>Importer le fichier rempli</span></h2>
        <label class="form-label" for="fichier">Fichier Excel (.xlsx) <span class="text-danger">*</span></label>
        <input class="form-control @error('fichier') is-invalid @enderror" id="fichier" name="fichier" type="file" accept=".xlsx,.xls" required>
        @error('fichier')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Importer</button>
      </form>
    </div>
  </section>

  @if ($rapport)
    <section class="panel mt-3">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Rapport d’import</span></h2>
          <p class="text-muted mb-0">{{ $rapport['importes'] }} ligne(s) importée(s) · {{ count($rapport['rejets']) }} ligne(s) rejetée(s).</p>
        </div>
      </div>
      @if ($rapport['rejets'])
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Ligne</th><th scope="col">Code OEV</th><th scope="col">Motif du rejet</th></tr></thead>
            <tbody>
              @foreach ($rapport['rejets'] as $rejet)
                <tr>
                  <td>{{ $rejet['ligne'] }}</td>
                  <td>{{ $rejet['code_oev'] ?: '—' }}</td>
                  <td>
                    <ul class="mb-0 ps-3 small">
                      @foreach ($rejet['motifs'] as $motif)
                        <li>{{ $motif }}</li>
                      @endforeach
                    </ul>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-success mb-0"><i class="bi bi-check-circle me-1" aria-hidden="true"></i> Toutes les lignes ont été importées.</p>
      @endif
    </section>
  @endif
</div>
@endsection
