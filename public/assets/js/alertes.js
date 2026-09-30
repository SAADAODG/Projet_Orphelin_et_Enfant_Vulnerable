/*
 * Notifications et confirmations de l'application, avec SweetAlert2 (aucune boîte native alert/confirm).
 *
 *  - Messages flash (succès, erreur, avertissement, info) : lus dans <script id="alertes-flash">,
 *    produit par resources/views/partials/alertes.blade.php.
 *  - Confirmation avant envoi d'un formulaire : <form data-confirm="Supprimer ce dossier ?">
 *    Options : data-confirm-titre, data-confirm-bouton (libellé), data-confirm-danger (bouton rouge).
 *  - Depuis un script : Alertes.succes('…'), Alertes.erreur('…'), Alertes.attention('…'), Alertes.info('…').
 */
(function () {
  'use strict';

  if (typeof Swal === 'undefined') return;

  function theme() {
    var racine = document.documentElement;
    if (racine.dataset.theme) return racine.dataset.theme === 'dark' ? 'dark' : 'light';
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function couleur(variable, defaut) {
    var valeur = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
    return valeur || defaut;
  }

  var base = function () {
    return {
      theme: theme(),
      confirmButtonColor: couleur('--admin-primary', '#2563eb'),
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'OK',
      cancelButtonText: 'Annuler',
      reverseButtons: true,
      focusCancel: false,
    };
  };

  /** Notification discrète (succès, info) : disparaît seule. */
  function toast(icone, message) {
    return Swal.fire(Object.assign(base(), {
      toast: true,
      position: 'top-end',
      icon: icone,
      title: message,
      showConfirmButton: false,
      showCloseButton: true,
      timer: 5000,
      timerProgressBar: true,
    }));
  }

  /** Fenêtre à valider (erreur, avertissement) : reste affichée jusqu'au clic. */
  function fenetre(icone, titre, message, html) {
    var options = Object.assign(base(), { icon: icone, title: titre });
    if (html) options.html = html; else options.text = message;
    return Swal.fire(options);
  }

  function echapper(texte) {
    var div = document.createElement('div');
    div.textContent = texte;
    return div.innerHTML;
  }

  var Alertes = {
    succes: function (message) { return toast('success', message); },
    info: function (message) { return toast('info', message); },
    attention: function (message, titre) { return fenetre('warning', titre || 'Attention', message); },
    erreur: function (message, titre) { return fenetre('error', titre || 'Action impossible', message); },

    /** Erreurs de formulaire : liste des messages, les champs concernés restent signalés en rouge. */
    erreursFormulaire: function (messages, note) {
      var liste = '<ul class="text-start mb-0 ps-3">' + messages.map(function (m) { return '<li>' + echapper(m) + '</li>'; }).join('') + '</ul>';
      var complement = note ? '<p class="small text-muted mt-3 mb-0">' + echapper(note) + '</p>' : '';
      return fenetre('error', 'Veuillez corriger le formulaire', null, liste + complement);
    },

    /** Demande de confirmation ; renvoie une promesse résolue à true si l'utilisateur confirme. */
    confirmer: function (message, options) {
      options = options || {};
      return Swal.fire(Object.assign(base(), {
        icon: options.danger ? 'warning' : 'question',
        title: options.titre || 'Confirmation',
        text: message,
        showCancelButton: true,
        confirmButtonText: options.bouton || 'Confirmer',
        confirmButtonColor: options.danger ? couleur('--admin-danger', '#dc2626') : couleur('--admin-primary', '#2563eb'),
        focusCancel: !!options.danger,
      })).then(function (resultat) { return resultat.isConfirmed; });
    },
  };

  window.Alertes = Alertes;

  // Confirmation avant envoi des formulaires marqués data-confirm (remplace confirm() du navigateur)
  document.addEventListener('submit', function (evenement) {
    var formulaire = evenement.target;
    if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirm) return;
    if (formulaire.dataset.confirme === '1') { delete formulaire.dataset.confirme; return; }

    evenement.preventDefault();
    var bouton = evenement.submitter || null;
    var danger = formulaire.hasAttribute('data-confirm-danger');

    Alertes.confirmer(formulaire.dataset.confirm, {
      titre: formulaire.dataset.confirmTitre,
      bouton: formulaire.dataset.confirmBouton,
      danger: danger,
    }).then(function (ok) {
      if (!ok) return;
      formulaire.dataset.confirme = '1';
      // requestSubmit conserve le bouton cliqué (name/value) et la validation HTML du formulaire
      if (formulaire.requestSubmit) formulaire.requestSubmit(bouton && bouton.form === formulaire ? bouton : undefined);
      else formulaire.submit();
    });
  }, true);

  // Messages flash transmis par le serveur
  document.addEventListener('DOMContentLoaded', function () {
    var source = document.getElementById('alertes-flash');
    if (!source) return;
    var messages;
    try { messages = JSON.parse(source.textContent || '[]'); } catch (e) { return; }

    // Les fenêtres s'enchaînent : une erreur n'est pas masquée par un succès affiché en même temps
    messages.reduce(function (precedente, alerte) {
      return precedente.then(function () {
        switch (alerte.type) {
          case 'success': return Alertes.succes(alerte.message);
          case 'info': return Alertes.info(alerte.message);
          case 'warning': return Alertes.attention(alerte.message);
          case 'validation': return Alertes.erreursFormulaire(alerte.messages, alerte.note);
          default: return Alertes.erreur(alerte.message);
        }
      });
    }, Promise.resolve());
  });
})();
