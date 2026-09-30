@extends('layouts.app')

@section('title', 'Plainte '.$plainte->reference.' | Espace Agent OEV')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-chat-text" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Plainte N° {{ $plainte->reference }}</p>
        <h1 class="h3 mb-1"><i class="bi {{ \App\Models\Plainte::OBJETS_ICONES[$plainte->objet] ?? 'bi-chat' }} me-1" aria-hidden="true"></i>{{ $plainte->objet_libelle }}</h1>
        <p class="text-muted mb-0">Reçue le {{ $plainte->created_at->format('d/m/Y à H:i') }}</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.plaintes.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la liste</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm" role="status">
      <i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
    </div>
  @endif

  <div class="row g-3">
    <div class="col-12 col-xl-8">
      <section class="panel mb-3">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-card-text" aria-hidden="true"></i><span>Message de l'usager</span></h2>
        </div>
        <p class="mb-0" style="white-space: pre-line;">{{ $plainte->description }}</p>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Lieu et dossier concerné</span></h2>
        </div>
        <dl class="row detail-list mb-0">
          <div class="col-sm-6"><dt>Région / Province</dt><dd>{{ $plainte->region ? $plainte->region->nom.' — '.($plainte->province?->nom ?? '—') : 'Non précisé' }}</dd></div>
          <div class="col-sm-6"><dt>Commune / quartier</dt><dd>{{ collect([$plainte->commune?->nom, $plainte->localite])->filter()->implode(' — ') ?: 'Non précisé' }}</dd></div>
          <div class="col-12">
            <dt>N° de signalement concerné</dt>
            <dd class="mb-0">
              @php $signalement = $plainte->recepisse_signalement ? \App\Models\Signalement::where('recepisse', $plainte->recepisse_signalement)->first() : null; @endphp
              @if ($signalement && auth()->user()->can('voir signalements') && $signalement->estDansLePerimetreDe(auth()->user()))
                <a href="{{ route('admin.signalements.show', $signalement) }}">{{ $plainte->recepisse_signalement }}</a>
              @else
                {{ $plainte->recepisse_signalement ?: 'Aucun' }}
              @endif
            </dd>
          </div>
        </dl>
      </section>
    </div>

    <div class="col-12 col-xl-4">
      <section class="panel mb-3">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-person" aria-hidden="true"></i><span>Plaignant</span></h2>
        </div>
        <dl class="detail-list mb-0">
          <dt>Identité</dt>
          <dd>
            @if ($plainte->anonyme || ! $plainte->nom)
              <i class="bi bi-incognito me-1" aria-hidden="true"></i>Anonyme
            @else
              {{ $plainte->nom }}
            @endif
          </dd>
          <dt>Téléphone</dt>
          <dd>@if ($plainte->telephone)<a href="tel:{{ $plainte->telephone }}">{{ $plainte->telephone }}</a>@else <span class="text-muted">Non fourni</span> @endif</dd>
          <dt>E-mail</dt>
          <dd class="mb-0">@if ($plainte->email)<a href="mailto:{{ $plainte->email }}">{{ $plainte->email }}</a>@else <span class="text-muted">Non fourni</span> @endif</dd>
        </dl>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Suivi</span></h2>
        </div>
        <p class="small text-muted">Statut actuel : <strong>{{ $plainte->statut_libelle }}</strong></p>
        @can('traiter plaintes')
        <form method="POST" action="{{ route('admin.plaintes.statut', $plainte) }}" class="d-grid gap-2">
          @csrf
          @method('PATCH')
          @if ($plainte->statut === 'nouvelle')
            <button type="submit" name="statut" value="en_cours" class="btn btn-outline-warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Marquer en cours</button>
          @endif
          @if ($plainte->statut !== 'traitee')
            <button type="submit" name="statut" value="traitee" class="btn btn-success"><i class="bi bi-check2-circle" aria-hidden="true"></i> Marquer comme traitée</button>
          @endif
          @if ($plainte->statut === 'traitee')
            <button type="submit" name="statut" value="en_cours" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Rouvrir</button>
          @endif
        </form>
        @endcan
      </section>
    </div>
  </div>
</div>
@endsection
