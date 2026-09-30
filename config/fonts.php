<?php

/*
 * Liste vétée des polices proposées dans Paramètres > Apparence (interface admin et
 * site public, réglages indépendants — voir SiteSetting::police_admin/police_public).
 *
 * Chaque entrée :
 * - 'label'      : libellé affiché dans le menu déroulant.
 * - 'family'     : valeur CSS font-family complète (avec ses solutions de repli).
 * - 'stylesheet' : chemin (relatif à public/) du CSS @font-face à charger, ou null pour
 *   la police système (aucun fichier à charger, s'appuie sur celles déjà présentes sur le
 *   poste du visiteur).
 *
 * Toutes les polices web sont hébergées localement (voir public/assets/fonts/), jamais
 * chargées depuis fonts.googleapis.com/fonts.gstatic.com à chaque visite — même logique
 * que SweetAlert2/Chart.js sur ce projet (fiabilité, indépendance vis-à-vis du réseau).
 */

return [
    'systeme' => [
        'label'      => 'Système (Segoe UI)',
        'family'     => '"Segoe UI", Arial, sans-serif',
        'stylesheet' => null,
    ],
    'inter' => [
        'label'      => 'Inter',
        'family'     => "'Inter', -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif",
        'stylesheet' => 'assets/fonts/inter/inter.css',
    ],
    'roboto' => [
        'label'      => 'Roboto',
        'family'     => "'Roboto', Arial, sans-serif",
        'stylesheet' => 'assets/fonts/roboto/roboto.css',
    ],
    'open-sans' => [
        'label'      => 'Open Sans',
        'family'     => "'Open Sans', Arial, sans-serif",
        'stylesheet' => 'assets/fonts/open-sans/open-sans.css',
    ],
    'times-new-roman' => [
        'label'      => 'Times New Roman',
        // Police système (comme "Système (Segoe UI)") : présente nativement sur Windows/macOS,
        // donc pas de fichier à vendoriser. Times/serif en repli sur les systèmes qui ne l'ont pas.
        'family'     => "'Times New Roman', Times, serif",
        'stylesheet' => null,
    ],
];
