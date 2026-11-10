"use strict";

(function () {
  var sidebarStorageKey = "adminHMD.sidebarMini";
  var themeStorageKey = "adminHMD.colorTheme";
  var desktopMedia = "(min-width: 992px)";

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
      return;
    }

    callback();
  }

  function isDesktop() {
    return window.matchMedia(desktopMedia).matches;
  }

  function canUseStorage() {
    try {
      var testKey = sidebarStorageKey + ".test";
      window.localStorage.setItem(testKey, "1");
      window.localStorage.removeItem(testKey);
      return true;
    } catch {
      // Stockage refusé par le navigateur (navigation privée, cookies bloqués) : préférences non mémorisées
      return false;
    }
  }

  function getSavedMiniState(storageAvailable) {
    if (!storageAvailable) {
      return false;
    }

    return window.localStorage.getItem(sidebarStorageKey) === "true";
  }

  function saveMiniState(storageAvailable, isMini) {
    if (storageAvailable) {
      window.localStorage.setItem(sidebarStorageKey, String(isMini));
    }
  }

  function getPreferredTheme(storageAvailable) {
    var savedTheme = storageAvailable ? window.localStorage.getItem(themeStorageKey) : "";

    if (savedTheme === "dark" || savedTheme === "light") {
      return savedTheme;
    }

    if (window.matchMedia?.("(prefers-color-scheme: dark)").matches) {
      return "dark";
    }

    return "light";
  }

  function setClass(element, className, enabled) {
    if (enabled) {
      element.classList.add(className);
    } else {
      element.classList.remove(className);
    }
  }

  // Lignes du tableau masquées quand elles ne contiennent pas le texte recherché
  function filterRows(table, query) {
    var rows = table.querySelectorAll("tbody tr");

    Array.prototype.forEach.call(rows, function (row) {
      row.hidden = query !== "" && !row.textContent.toLowerCase().includes(query);
    });
  }

  onReady(function () {
    var body = document.body;
    var sidebarToggle = document.querySelector("[data-sidebar-toggle]");
    var themeToggles = document.querySelectorAll("[data-theme-toggle]");
    var themeIcons = document.querySelectorAll("[data-theme-icon]");
    var closeButtons = document.querySelectorAll("[data-sidebar-close]");
    var sidebarLinks = document.querySelectorAll(".sidebar-nav .nav-link");
    var mediaQuery = window.matchMedia(desktopMedia);
    var storageAvailable = canUseStorage();

    function initValidation() {
      var forms = document.querySelectorAll(".needs-validation");

      Array.prototype.forEach.call(forms, function (form) {
        form.addEventListener("submit", function (event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }

          form.classList.add("was-validated");
        });
      });
    }

    function initTableSearch() {
      var searchInputs = document.querySelectorAll("[data-table-search]");

      Array.prototype.forEach.call(searchInputs, function (input) {
        var tableId = input.dataset.tableSearch;
        var table = document.getElementById(tableId);

        if (!table) {
          return;
        }

        input.addEventListener("input", function () {
          filterRows(table, input.value.trim().toLowerCase());
        });
      });
    }

    function updateThemeControls(theme) {
      var nextTheme = theme === "dark" ? "light" : "dark";
      var label = "Switch to " + nextTheme + " mode";
      var iconClass = theme === "dark" ? "bi bi-sun" : "bi bi-moon-stars";

      Array.prototype.forEach.call(themeToggles, function (button) {
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
      });

      Array.prototype.forEach.call(themeIcons, function (icon) {
        icon.className = iconClass;
      });
    }

    function applyTheme(theme) {
      document.documentElement.dataset.theme = theme;
      document.documentElement.dataset.bsTheme = theme;

      if (storageAvailable) {
        window.localStorage.setItem(themeStorageKey, theme);
      }

      updateThemeControls(theme);
    }

    function initThemeToggle() {
      applyTheme(getPreferredTheme(storageAvailable));

      Array.prototype.forEach.call(themeToggles, function (button) {
        button.addEventListener("click", function () {
          var currentTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
          applyTheme(currentTheme === "dark" ? "light" : "dark");
        });
      });
    }

    initValidation();
    initTableSearch();
    initThemeToggle();

    if (!sidebarToggle) {
      return;
    }

    function setToggleExpanded() {
      var expanded = isDesktop()
        ? !body.classList.contains("sidebar-mini")
        : body.classList.contains("sidebar-open");

      sidebarToggle.setAttribute("aria-expanded", String(expanded));
    }

    function closeMobileSidebar() {
      body.classList.remove("sidebar-open");
      setToggleExpanded();
    }

    function toggleSidebar() {
      if (isDesktop()) {
        body.classList.toggle("sidebar-mini");
        saveMiniState(storageAvailable, body.classList.contains("sidebar-mini"));
      } else {
        body.classList.toggle("sidebar-open");
      }

      setToggleExpanded();
    }

    function addCloseHandlers(items) {
      Array.prototype.forEach.call(items, function (item) {
        item.addEventListener("click", function () {
          if (!isDesktop()) {
            closeMobileSidebar();
          }
        });
      });
    }

    if (getSavedMiniState(storageAvailable) && isDesktop()) {
      body.classList.add("sidebar-mini");
    }

    sidebarToggle.addEventListener("click", toggleSidebar);
    addCloseHandlers(closeButtons);
    addCloseHandlers(sidebarLinks);
    setToggleExpanded();

    function handleBreakpointChange() {
      if (isDesktop()) {
        body.classList.remove("sidebar-open");
        setClass(body, "sidebar-mini", getSavedMiniState(storageAvailable));
      } else {
        body.classList.remove("sidebar-mini");
      }

      setToggleExpanded();
    }

    mediaQuery.addEventListener("change", handleBreakpointChange);
  });

  // Interrupteurs (case à cocher role="switch") : aria-checked suit l'état de la case
  document.addEventListener("change", function (event) {
    var champ = event.target;
    if (champ.matches?.('input[type="checkbox"][role="switch"]')) {
      champ.setAttribute("aria-checked", champ.checked ? "true" : "false");
    }
  });

  // Filtres envoyés dès qu'ils changent (remplace les attributs onchange, interdits par la CSP).
  // data-vider="champ" : vide ce champ avant l'envoi (ex. la province quand la région change) ;
  // data-desactiver="champ" : le retire de l'envoi.
  document.addEventListener("change", function (event) {
    var champ = event.target.closest?.("[data-auto-submit]");
    var form = champ?.form;
    if (!form) {
      return;
    }

    var aVider = champ.dataset.vider && form.elements.namedItem(champ.dataset.vider);
    if (aVider) {
      aVider.value = "";
    }

    var aDesactiver = champ.dataset.desactiver && form.elements.namedItem(champ.dataset.desactiver);
    if (aDesactiver) {
      aDesactiver.disabled = true;
    }

    form.submit();
  });

  // Lien « Page précédente » (remplace href="javascript:…", interdit par la CSP)
  document.addEventListener("click", function (event) {
    if (event.target.closest?.("[data-page-precedente]")) {
      event.preventDefault();
      window.history.back();
    }
  });
})();
