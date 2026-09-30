{{--
  Notifications SweetAlert de toutes les pages (inclus par chaque layout, avant @stack('scripts')).

  Messages affichés :
    session('error' | 'warning' | 'success' | 'info' | 'status')
    erreurs de validation du sac par défaut ($errors), avec la note facultative $noteErreursFormulaire
    définie par la page (ex. rappel de sélectionner à nouveau les fichiers).
  Les erreurs passent avant les succès, pour ne jamais être masquées.
--}}
@php
  $alertesFlash = [];
  $erreursValidation = isset($errors) ? $errors->getBag('default') : null;

  if ($erreursValidation && $erreursValidation->any()) {
    $alertesFlash[] = ['type' => 'validation', 'messages' => $erreursValidation->all(), 'note' => $noteErreursFormulaire ?? null];
  }
  foreach (['error' => 'error', 'warning' => 'warning', 'success' => 'success', 'info' => 'info', 'status' => 'info'] as $cle => $type) {
    if (is_string($message = session($cle)) && $message !== '') {
      $alertesFlash[] = ['type' => $type, 'message' => $message];
    }
  }
@endphp
<script src="{{ asset('assets/vendors/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('assets/js/alertes.js') }}"></script>
<script type="application/json" id="alertes-flash">@json($alertesFlash)</script>
