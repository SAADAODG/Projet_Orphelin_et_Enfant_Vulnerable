{{--
  Rejet définitif d'un dossier (DR ou niveau central). À inclure dans une barre d'action .oev-action :
  le bouton ouvre le formulaire de motif (id « formRejet »).
  Variables : $oev, $cible ('bouton' ou 'formulaire')
--}}
@if ($cible === 'bouton')
  <button class="btn btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#formRejet" aria-expanded="{{ $errors->has('motif_rejet') ? 'true' : 'false' }}" aria-controls="formRejet">
    <i class="bi bi-slash-circle" aria-hidden="true"></i> Rejeter
  </button>
@else
  <form class="collapse w-100 {{ $errors->has('motif_rejet') ? 'show' : '' }}" id="formRejet" method="POST" action="{{ route('oevs.rejeter', $oev) }}"
        onsubmit="return confirm('Rejeter définitivement ce dossier ? L’enfant ne sera pas intégré comme OEV.');">
    @csrf
    <div class="alert alert-dark py-2 small mb-2">
      <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
      Le rejet est <strong>définitif</strong> : le dossier est clos et ne pourra plus être modifié ni intégré.
      Pour une simple correction, utilisez plutôt {{ $oev->statut_dossier === \App\Models\Oev::ETAT_VALIDE ? '« Demander un complément »' : '« Non conforme / Renvoyer au DP »' }}.
    </div>
    <label class="form-label fw-semibold" for="motif_rejet">Motif du rejet <span class="text-danger">*</span></label>
    <textarea class="form-control @error('motif_rejet') is-invalid @enderror" id="motif_rejet" name="motif_rejet" rows="3" maxlength="2000" required placeholder="ex : l’enfant ne remplit pas les critères d’éligibilité, doublon d’un dossier existant…">{{ old('motif_rejet') }}</textarea>
    <button class="btn btn-dark mt-2" type="submit"><i class="bi bi-slash-circle" aria-hidden="true"></i> Confirmer le rejet</button>
  </form>
@endif
