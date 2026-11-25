#!/usr/bin/env bash
# =============================================================================
# Pré-scan ANSSI — à lancer AVANT toute demande de scan / revue de correction.
#
# Rejoue, sur le VRAI site public (donc à travers le reverse proxy ANPTIC), les
# contrôles des rapports ANSSI reçus par les plateformes du MFS :
#   VBG (16/11/2026), sociale-03 (13/11/2026), OEV (17/11/2026).
# Les défauts liés au proxy (en-têtes en double, HSTS en double, redirection
# en http://) n'existent PAS en local : seul ce script les voit.
#
# Usage (Git Bash ou Linux, curl requis) :
#   bash scripts/securite/pre-scan-anssi.sh https://vbg-07.mfs-test.gov.bf
#   bash scripts/securite/pre-scan-anssi.sh https://site-a.gov.bf https://site-b.gov.bf
#
# Options (variables d'environnement) :
#   PAGE=/login          page HTML à analyser (défaut /login)
#   STATIQUE=/favicon.ico fichier servi sans PHP (défaut : deviné dans la page)
#   CHEMINS="/accounts/ /build /admin/"  adresses à tester pour les redirections
#
# Code de sortie : 0 si tout est conforme, 1 sinon. Aucune donnée n'est créée :
# le script n'envoie que des requêtes de lecture, plus un POST refusé (> 1 000
# champs) sur la page de connexion. Jamais de scan actif ni de test de charge.
# =============================================================================
set -uo pipefail

PAGE="${PAGE:-/login}"
CHEMINS="${CHEMINS:-/accounts/ /login/ /build /admin/ /assets}"
FORGE="hote-forge-anssi.invalid"
UA="pre-scan-anssi (DSI MFS)"
TOTAL_ECHECS=0

if [ -t 1 ]; then V=$'\e[32m'; R=$'\e[31m'; J=$'\e[33m'; G=$'\e[1m'; Z=$'\e[0m'; else V=; R=; J=; G=; Z=; fi
ok()    { printf '  %s✅ OK%s       %s\n' "$V" "$Z" "$1"; }
echec() { printf '  %s❌ À CORRIGER%s %s\n' "$R" "$Z" "$1"; [ $# -gt 1 ] && printf '                %s\n' "${@:2}"; ECHECS=$((ECHECS + 1)); }
info()  { printf '  %sℹ️  À SAVOIR%s  %s\n' "$J" "$Z" "$1"; }

# En-têtes d'une réponse (sans suivre les redirections), en minuscules, sans \r.
entetes() { curl -sk -o /dev/null -D - -m 25 -A "$UA" "$@" | tr -d '\r'; }
valeurs() { grep -i "^$1:" | cut -d: -f2- | sed 's/^ *//'; }

controler() {
    local BASE="${1%/}" HOTE
    HOTE="$(printf '%s' "$BASE" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"
    ECHECS=0
    printf '\n%s══ %s ══%s\n' "$G" "$BASE" "$Z"

    local H; H="$(entetes "$BASE$PAGE")"
    if ! printf '%s' "$H" | grep -q '^HTTP/'; then
        echec "Site injoignable ($BASE$PAGE)"; TOTAL_ECHECS=$((TOTAL_ECHECS + 1)); return
    fi
    local CORPS; CORPS="$(curl -sk -m 25 -A "$UA" "$BASE$PAGE")"

    # ── Vul-001 : plus de 1 000 champs ─────────────────────────────────────
    local CHAMPS REP CODE
    CHAMPS="$(seq -s '&' -f 'champ%g=1' 1 2100)"
    REP="$(curl -sk -m 30 -A "$UA" -w $'\n%{http_code}' --data "$CHAMPS" "$BASE$PAGE")"
    CODE="${REP##*$'\n'}"
    if printf '%s' "$REP" | grep -qiE 'input variables exceeded|<b>warning</b>|PHP (Warning|Notice|Fatal)|in <b>[^<]*\.php'; then
        echec "Vul-001 : message d'erreur PHP affiché pour une requête de 2 100 champs" \
              "→ display_errors/display_startup_errors/html_errors = Off dans le php.ini du serveur (ou de l'image Docker)"
    elif [ "$CODE" = 400 ]; then
        ok "Vul-001 : requête de 2 100 champs refusée (400), aucun message PHP"
    else
        ok "Vul-001 : aucun message PHP pour 2 100 champs (code $CODE)"
        info "      la requête tronquée devrait être refusée en 400 (middleware RefuserRequeteTronquee)"
    fi

    # ── Vul-002 : en-tête Host et X-Forwarded-Host forgés ──────────────────
    local XFH; XFH="$(curl -sk -m 25 -A "$UA" -D - -H "X-Forwarded-Host: $FORGE" "$BASE$PAGE" | tr -d '\r')"
    if printf '%s' "$XFH" | grep -q "$FORGE"; then
        echec "Vul-002 : X-Forwarded-Host forgé repris $(printf '%s' "$XFH" | grep -o "$FORGE" | wc -l) fois (liens, redirections)" \
              "→ trustProxies sans HEADER_X_FORWARDED_HOST, URL::forceRootUrl(APP_URL), TrustHosts"
    else
        ok "Vul-002 : X-Forwarded-Host forgé jamais repris dans la réponse"
    fi
    local HF CODEHF
    HF="$(curl -sk -m 25 -A "$UA" -D - -H "Host: $FORGE" "$BASE$PAGE" 2>/dev/null | tr -d '\r')"
    CODEHF="$(printf '%s' "$HF" | sed -n 's#^HTTP/[0-9.]* \([0-9]*\).*#\1#p' | head -1)"
    if printf '%s' "$HF" | grep -q "$FORGE"; then
        echec "Vul-002 : en-tête Host forgé repris dans la réponse (code ${CODEHF:-?})"
    elif [ "${CODEHF:-000}" = 200 ] && printf '%s' "$HF" | grep -qi 'csrf\|name="_token"'; then
        echec "Vul-002 : l'application répond normalement (200) sous un Host forgé" "→ TrustHosts : hôte d'APP_URL + TRUSTED_HOSTS seulement"
    else
        ok "Vul-002 : Host forgé refusé (code ${CODEHF:-connexion fermée})"
    fi

    # ── Vul-003 (OEV) : aucune redirection en http:// ──────────────────────
    local CHEMIN LOC PB=""
    for CHEMIN in $CHEMINS; do
        LOC="$(entetes "$BASE$CHEMIN" | valeurs location | head -1)"
        case "$LOC" in http://*) PB="$PB $CHEMIN → $LOC;" ;; esac
    done
    if [ -n "$PB" ]; then
        echec "Vul-003 : redirection vers une adresse en clair (http://)" "$PB" \
              "→ nginx absolute_redirect off ; Apache : retirer « Redirect Trailing Slashes » du .htaccess"
    else
        ok "Vul-003 : aucune redirection vers http:// ($CHEMINS)"
    fi

    # ── En-têtes en double (sociale-03 VUL-004) et HSTS (OEV Vul-004) ───────
    local STAT="${STATIQUE:-}"
    if [ -z "$STAT" ]; then
        STAT="$(printf '%s' "$CORPS" | grep -oE '(src|href)="[^"]+\.(css|js)(\?[^"]*)?"' | head -1 | sed -E 's/^(src|href)="//; s/"$//')"
        STAT="${STAT#https://$HOTE}"; STAT="${STAT#http://$HOTE}"
        [ -z "$STAT" ] && STAT=/favicon.ico
    fi
    local URL NOM DOUBLES
    for URL in "$PAGE" "$STAT"; do
        NOM="page $URL"; [ "$URL" = "$STAT" ] && NOM="fichier statique $URL"
        DOUBLES="$(entetes "$BASE$URL" | grep -E '^[A-Za-z0-9-]+:' | cut -d: -f1 | tr 'A-Z' 'a-z' \
                   | grep -vE '^(set-cookie|vary|link)$' | sort | uniq -d | tr '\n' ' ')"
        if [ -n "$DOUBLES" ]; then
            echec "En-têtes en double ($NOM) : $DOUBLES" \
                  "→ un seul endroit : le proxy ANPTIC pose déjà X-Frame-Options, X-Content-Type-Options, Referrer-Policy," \
                  "  Permissions-Policy, COOP, CORP et HSTS (SECURITY_HEADERS_BY_PROXY, aucun add_header/Header set)"
        else
            ok "Aucun en-tête en double ($NOM)"
        fi
    done
    local HSTS; HSTS="$(printf '%s' "$H" | valeurs strict-transport-security)"
    case "$(printf '%s' "$HSTS" | grep -c .)" in
        0) echec "HSTS absent" "→ à demander à l'ANPTIC (posé par le proxy, jamais par l'application)" ;;
        1) if printf '%s' "$HSTS" | grep -qi 'includesubdomains' && printf '%s' "$HSTS" | grep -qE 'max-age=(6307200[0-9]|[7-9][0-9]{7}|[0-9]{9,})'; then
               ok "HSTS unique et strict ($HSTS)"
           else
               ok "HSTS unique ($HSTS)"; info "      l'ANSSI recommande max-age=63072000; includeSubDomains (côté proxy)"
           fi ;;
        *) echec "HSTS en double : $(printf '%s' "$HSTS" | tr '\n' '|')" "→ ne jamais poser HSTS dans l'application, nginx ou Apache de la VM" ;;
    esac

    # ── CSP (sociale-03 VUL-003, OEV Vul-005) ───────────────────────────────
    local CSP DIR VAL
    CSP="$(printf '%s' "$H" | valeurs content-security-policy | head -1)"
    if [ -z "$CSP" ]; then
        echec "CSP absente sur $PAGE"
    else
        PB=""
        for DIR in script-src style-src; do
            VAL="$(printf '%s' "$CSP" | tr ';' '\n' | sed 's/^ *//' | grep "^$DIR " | head -1)"
            [ -z "$VAL" ] && VAL="$(printf '%s' "$CSP" | tr ';' '\n' | sed 's/^ *//' | grep '^default-src ' | head -1)"
            printf '%s' "$VAL" | grep -qE "'unsafe-(inline|eval)'" && PB="$PB [$VAL]"
        done
        if [ -n "$PB" ]; then
            echec "CSP permissive :$PB" "→ nonce par requête sur <script>/<style>, Alpine @alpinejs/csp, seul style-src-attr 'unsafe-inline'"
        else
            ok "CSP stricte : ni unsafe-inline ni unsafe-eval dans script-src/style-src"
        fi
        printf '%s' "$H" | grep -qi '^content-security-policy-report-only:' && info "      une CSP Report-Only est aussi envoyée (mode observation ?)"
    fi

    # ── Divulgation d'informations (sociale-03 VUL-005) ─────────────────────
    local REVELE
    REVELE="$(printf '%s' "$H" | grep -iE '^(server|x-powered-by|x-aspnet-version|x-generator|x-runtime):' \
              | grep -iE '[0-9]|php|laravel|express|asp' | tr '\n' ' ')"
    if [ -n "$REVELE" ]; then
        echec "Divulgation par les en-têtes : $REVELE" "→ server_tokens off (nginx), expose_php = Off (php.ini)"
    else
        ok "Aucun en-tête ne révèle une technologie ou une version"
    fi
    local PAGE80; PAGE80="$(curl -s -m 15 -A "$UA" "http://$HOTE/" 2>/dev/null)"
    printf '%s' "$PAGE80" | grep -qiE '<center>(nginx|apache)' && \
        info "      la page de redirection HTTP→HTTPS affiche « $(printf '%s' "$PAGE80" | grep -oiE '<center>(nginx|apache)[^<]*' | head -1 | sed 's/<center>//') » : page du proxy ANPTIC, à leur demander"

    # ── Cookies (scan ZAP de l'ANPTIC) ─────────────────────────────────────
    local COOKIE NC PBC=""
    while IFS= read -r COOKIE; do
        [ -z "$COOKIE" ] && continue
        NC="${COOKIE%%=*}"
        printf '%s' "$COOKIE" | grep -qi '; *secure' || PBC="$PBC $NC(sans Secure)"
        printf '%s' "$COOKIE" | grep -qi '; *httponly' || PBC="$PBC $NC(sans HttpOnly)"
    done < <(printf '%s' "$H" | valeurs set-cookie)
    if [ -n "$PBC" ]; then
        echec "Cookies :$PBC" "→ SESSION_SECURE_COOKIE=true ; supprimer XSRF-TOKEN si aucun JavaScript ne le lit"
    else
        ok "Cookies Secure et HttpOnly"
    fi

    printf '  %s→ %s%s\n' "$G" "$([ $ECHECS -eq 0 ] && echo "${V}CONFORME : le scan peut être demandé${Z}" || echo "${R}$ECHECS point(s) à corriger avant de demander le scan${Z}")" "$Z"
    info "Ports ouverts : vérifiés par l'ANSSI côté Internet ; seuls 80 et 443 doivent l'être (à demander à l'ANPTIC)."
    TOTAL_ECHECS=$((TOTAL_ECHECS + ECHECS))
}

if [ $# -eq 0 ]; then
    sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'
    exit 2
fi
for SITE in "$@"; do controler "$SITE"; done
[ "$TOTAL_ECHECS" -eq 0 ]
