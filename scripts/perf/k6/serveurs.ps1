<#
.SYNOPSIS
    Lance (ou arrête) N serveurs locaux de PERFORMANCE pour le test de charge k6.

.DESCRIPTION
    Sous Windows, « php artisan serve » ne traite qu'une requête à la fois (PHP_CLI_SERVER_WORKERS n'y est
    pas pris en charge). Ce script lance donc N serveurs sur des ports consécutifs, tous sur .env.perf
    (même base _perf, sessions partagées en base) ; k6 répartit les utilisateurs virtuels entre eux (BASE_URLS).

    Garde-fou : .env.perf doit exister et sa base (DB_DATABASE) se terminer par « _perf ».

.EXAMPLE
    powershell -File scripts/perf/k6/serveurs.ps1 -Nombre 4            # ports 8098 à 8101
    powershell -File scripts/perf/k6/serveurs.ps1 -Arreter -Nombre 4   # arrête ces serveurs
#>
param(
    [int] $Nombre = 4,
    [int] $PortDebut = 8098,
    [switch] $Arreter
)

$ErrorActionPreference = 'Stop'
$racine = Resolve-Path (Join-Path $PSScriptRoot '..\..\..')
$ports = $PortDebut..($PortDebut + $Nombre - 1)

# Processus PHP d'un port : « artisan serve » et le serveur intégré qu'il lance (php -S 127.0.0.1:port)
function Get-ProcessusServeur([int] $port) {
    Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
        Where-Object { $_.CommandLine -match "--port=$port\b" -or $_.CommandLine -match "127\.0\.0\.1:$port\b" }
}

if ($Arreter) {
    foreach ($port in $ports) {
        $processus = @(Get-ProcessusServeur $port)
        $processus | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        Write-Host "Port ${port} : $($processus.Count) processus arrêté(s)"
    }
    exit 0
}

$fichierEnv = Join-Path $racine '.env.perf'
if (-not (Test-Path $fichierEnv)) {
    Write-Error "REFUS : $fichierEnv introuvable (copie de .env avec DB_DATABASE=<base>_perf, voir scripts/perf/k6/README.md)."
}
$base = (Select-String -Path $fichierEnv -Pattern '^DB_DATABASE=(.*)$').Matches | Select-Object -First 1 | ForEach-Object { $_.Groups[1].Value.Trim().Trim('"') }
if (-not $base -or -not $base.EndsWith('_perf')) {
    Write-Error "REFUS : la base de .env.perf (« $base ») ne se termine pas par « _perf »."
}

foreach ($port in $ports) {
    if (@(Get-ProcessusServeur $port).Count -gt 0) {
        Write-Host "Port ${port} : déjà lancé"
        continue
    }
    Start-Process -FilePath 'php' -ArgumentList 'artisan', 'serve', '--env=perf', '--no-reload', '--host=127.0.0.1', "--port=$port" `
        -WorkingDirectory $racine -WindowStyle Hidden
}

# Attente : chaque serveur doit répondre sur /login
$limite = (Get-Date).AddSeconds(60)
foreach ($port in $ports) {
    do {
        try {
            $code = (Invoke-WebRequest -Uri "http://127.0.0.1:$port/login" -UseBasicParsing -TimeoutSec 5).StatusCode
        } catch {
            $code = 0
            Start-Sleep -Milliseconds 500
        }
    } while ($code -ne 200 -and (Get-Date) -lt $limite)
    if ($code -ne 200) {
        Write-Error "Le serveur du port $port ne répond pas."
    }
}

$urls = ($ports | ForEach-Object { "http://127.0.0.1:$_" }) -join ','
Write-Host "$Nombre serveur(s) prêt(s) sur la base $base"
Write-Host "BASE_URLS=$urls"
