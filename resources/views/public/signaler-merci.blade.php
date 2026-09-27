@extends('layouts.public')

@section('title', 'Signalement envoyé | OEV')

@section('content')
<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-7">
        <div class="suivi-card text-center">
          <div class="merci-icon"><i class="bi bi-check-lg"></i></div>
          <h1 class="h3 fw-bold text-dark mb-2">Merci, votre signalement a bien été envoyé</h1>
          <p class="text-muted mb-4">Votre récépissé se télécharge automatiquement (PDF). Conservez-le : son numéro vous permettra de suivre la réponse de nos agents.</p>

          <div class="recepisse-box">
            <span class="recepisse-label">N° de récépissé</span>
            <span class="recepisse-value" id="recepisseValue">{{ $recepisse }}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="copierRecepisse">
              <i class="bi bi-clipboard me-1"></i> Copier
            </button>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="{{ route('public.signaler.recepisse', $recepisse) }}" class="btn btn-primary px-4 fw-bold" id="telechargerRecepisse" download>
              <i class="bi bi-file-earmark-arrow-down me-1"></i> Télécharger le récépissé (PDF)
            </a>
            <a href="{{ route('public.suivi', ['recepisse' => $recepisse]) }}" class="btn btn-outline-secondary px-4">
              <i class="bi bi-search me-1"></i> Suivre mon signalement
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  // Téléchargement automatique du récépissé PDF à l'arrivée sur la page
  window.addEventListener('load', function () {
    setTimeout(function () {
      document.getElementById('telechargerRecepisse').click();
    }, 600);
  });

  document.getElementById('copierRecepisse').addEventListener('click', function () {
    const bouton = this;
    navigator.clipboard.writeText(document.getElementById('recepisseValue').textContent.trim()).then(function () {
      bouton.innerHTML = '<i class="bi bi-check2 me-1"></i> Copié';
    });
  });
</script>
@endpush
