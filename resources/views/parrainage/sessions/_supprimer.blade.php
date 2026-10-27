{{-- Bouton de suppression d'une session (confirmation). Variables : $session, $compact (bouton icône seule) --}}
@php
  $nbChoisis = $session->beneficiaires_count ?? $session->beneficiaires()->count();
@endphp
<form method="POST" action="{{ route('parrainage.sessions.destroy', $session) }}" data-confirm-danger
      data-confirm="Supprimer définitivement la session {{ $session->reference() }} ({{ $session->libelleEtat() }}) ?{{ $nbChoisis ? ' Ses ' . $nbChoisis . ' OEV sélectionné(s) seront retirés de la session.' : '' }} Ses quotas seront aussi supprimés. Cette action est irréversible.">
  @csrf
  @method('DELETE')
  @if ($compact ?? false)
    <button class="btn btn-light btn-sm text-danger" type="submit" aria-label="Supprimer la session {{ $session->reference() }}" title="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
  @else
    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Supprimer</button>
  @endif
</form>
