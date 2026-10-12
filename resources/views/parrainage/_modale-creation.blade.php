{{--
  Formulaire de création dans une fenêtre modale (nouvelle session, nouveau parrain, nouvel appui).
  Variables :
    $id       identifiant de la modale (ex : nouvelleSessionModal)
    $titre    titre de la fenêtre ; $icone : icône Bootstrap Icons
    $action   URL du formulaire (POST)
    $vue      champs à inclure (ex : parrainage.sessions._form) ; $donnees : variables de ces champs
    $bouton   libellé du bouton d'enregistrement
    $ouvrir   true pour ouvrir la fenêtre au chargement (erreur de saisie, lien « nouveau »)
    $taille   modal-lg (défaut) ou modal-xl
--}}
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Titre" aria-hidden="true">
  <div class="modal-dialog {{ $taille ?? 'modal-lg' }} modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="{{ $id }}Titre"><i class="bi {{ $icone }} me-1" aria-hidden="true"></i> {{ $titre }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form class="modal-formulaire" method="POST" action="{{ $action }}">
        @csrf
        {{-- Rouvre cette fenêtre si le serveur renvoie le formulaire (erreur de saisie) --}}
        <input type="hidden" name="_modale" value="{{ $id }}">
        <div class="modal-body">
          @include($vue, $donnees)
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $bouton }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@if ($ouvrir)
  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      bootstrap.Modal.getOrCreateInstance(document.getElementById(@json($id))).show();
    });
  </script>
  @endpush
@endif
