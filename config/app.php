<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    // Module « Signalements » de l'espace admin : MODULE_SIGNALEMENTS=false le masque (menu,
    // tableau de bord, accès) sans retirer les permissions des rôles
    'module_signalements' => (bool) env('MODULE_SIGNALEMENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sécurité derrière le reverse proxy ANPTIC (rapport ANSSI du 17/11/2026)
    |--------------------------------------------------------------------------
    |
    | trusted_hosts : hôtes acceptés EN PLUS de celui d'APP_URL (Vul-002), noms ou
    | IP séparés par des virgules. Vide = l'hôte d'APP_URL seulement ; tout autre
    | en-tête Host reçoit une 400.
    |
    | entetes_poses_par_le_proxy : le proxy ANPTIC pose déjà X-Frame-Options,
    | X-Content-Type-Options, Referrer-Policy, Permissions-Policy, COOP et CORP ;
    | l'application ne les pose pas derrière lui (sinon en double). Absent ou vide
    | = vrai dès que TRUSTED_PROXIES est renseigné.
    |
    | L'ancien mode « observation » de la CSP (CSP_SCRIPTS_OBSERVATION, qui
    | rétablissait 'unsafe-inline', Vul-005) est supprimé : la CSP est toujours stricte.
    |
    */

    'trusted_hosts' => env('TRUSTED_HOSTS', ''),

    'entetes_poses_par_le_proxy' => in_array(env('SECURITY_HEADERS_BY_PROXY'), [null, ''], true)
        ? (string) env('TRUSTED_PROXIES', '') !== ''
        : filter_var(env('SECURITY_HEADERS_BY_PROXY'), FILTER_VALIDATE_BOOL),

];
