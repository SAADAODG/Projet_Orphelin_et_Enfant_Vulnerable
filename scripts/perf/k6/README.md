# Test de montée en charge (k6)

Des agents connectés naviguent entre les pages les plus utilisées (tableau de bord, listes et fiches OEV,
circuit des dossiers, parrainage…), avec un poids par page et 1 à 4 s de réflexion entre deux clics.

| Fichier | Rôle |
|---|---|
| `charge.js` | scénario k6 (profils `fumee`, `charge`, `pic`) |
| `serveurs.ps1` | lance / arrête N serveurs locaux sur `.env.perf` |
| `resultats/` | résumés `.md` et `.json` de chaque essai (ignorés par Git) |

## Règles

- **Base dédiée uniquement** : `.env.perf` doit viser une base dont le nom se termine par `_perf`
  (`serveurs.ps1` refuse sinon). La base de développement n'est jamais touchée.
- **Machine locale uniquement** : `charge.js` refuse toute cible autre que `127.0.0.1`.
  Viser un serveur partagé ou de production demande un **accord explicite** de l'équipe, puis
  `-e CONFIRMER_CIBLE=oui`.
- **Aucun mot de passe dans le code** : les comptes sont passés en ligne de commande (`COMPTES`).

## Préparation (une fois)

1. Base de performance et volume (voir `scripts/perf/`) :
   ```
   php scripts/perf/preparer-base.php
   php scripts/perf/generer-volume.php 100000
   php scripts/perf/migrer.php            # après chaque nouvelle migration
   ```
2. `.env.perf` à la racine : copie de `.env` avec `DB_DATABASE=plateforme_oev_perf`, `APP_DEBUG=false`,
   `LOG_LEVEL=error`. Ce fichier est ignoré par Git. Les sessions doivent rester en base
   (`SESSION_DRIVER=database`) pour être partagées par tous les serveurs.
3. k6 installé (`winget install k6` ou https://k6.io).

## Lancement

`php artisan serve` ne traite qu'une requête à la fois sous Windows : on lance plusieurs serveurs.

```
powershell -File scripts/perf/k6/serveurs.ps1 -Nombre 8          # ports 8098 à 8105, affiche BASE_URLS

k6 run -e COMPTES=admin@oev.gov.bf:MDP,agent@oev.gov.bf:MDP,dr@oev.gov.bf:MDP,dp@oev.gov.bf:MDP ^
       -e BASE_URLS=http://127.0.0.1:8098,...,http://127.0.0.1:8105 ^
       -e PROFIL=fumee scripts/perf/k6/charge.js

powershell -File scripts/perf/k6/serveurs.ps1 -Nombre 8 -Arreter
```

| Variable | Défaut | Rôle |
|---|---|---|
| `COMPTES` | — (obligatoire) | `email:motdepasse,…` ; chaque compte peut avoir son mot de passe |
| `BASE_URLS` | `http://127.0.0.1:8098` | serveurs, les utilisateurs virtuels sont répartis entre eux |
| `PROFIL` | `fumee` | `fumee` (2 utilisateurs, 30 s), `charge` (montée 1 min, palier, descente), `pic` (4 paliers croissants) |
| `VUS` | `20` | nombre d'utilisateurs virtuels visé (`charge`, `pic`) |
| `PALIER` | `3m` | durée du palier du profil `charge` |
| `RESULTATS` | `scripts/perf/k6/resultats` | dossier des résumés |
| `CONFIRMER_CIBLE` | — | `oui` pour viser autre chose que 127.0.0.1 (accord explicite requis) |

**Ordre conseillé** : `fumee` d'abord (valide le script et les comptes), puis `charge` par paliers
croissants (ex. 10, 20, 50). Surveillez la RAM libre de la machine et arrêtez tout si elle passe sous ~0,8 Go.

## Fonctionnement

- **Connexion** : chaque compte se connecte une seule fois dans `setup()` ; ses cookies de session sont
  partagés par les utilisateurs virtuels. Fin de `setup()` : chaque session est vérifiée sur chaque
  serveur avec un stock de cookies vierge.
- **Cookies** : k6 vide le stock de cookies par défaut à chaque itération ; chaque utilisateur virtuel a
  donc son propre `http.CookieJar()`. Les redirections ne sont pas suivies : un renvoi vers `/login`
  compte dans `sessions_perdues`.
- **Droits par rôle** : au démarrage, chaque page est essayée pour chaque compte ; seules celles qui
  répondent 200 sont gardées (ces essais ne comptent pas comme des erreurs). Les identifiants de
  dossiers, sessions et parrains sont relevés par compte : un DR ou un DP ne voit que sa zone.
- **Exclus** : pages publiques (signalement, plainte) et exports Excel / PDF (ce n'est pas de la navigation ;
  ils sont mesurés par `scripts/perf/lancer-profils.php`).

Seuils d'échec : erreurs HTTP < 1 %, sessions perdues < 1 %, vérifications > 99 %, p95 < 2 s.

## Lire les résultats

- **Toutes les pages ralentissent ensemble** et le nombre de pages par seconde plafonne : le serveur
  (PHP ou PostgreSQL) sature, aucune page n'est en cause en particulier.
- **Une seule page reste lente** quand les autres sont rapides : c'est elle qu'il faut optimiser
  (profilage : `php scripts/perf/profiler.php <url> <email>`).
- **Un test local ne donne pas la capacité de production** : serveur de développement PHP mono-requête,
  PostgreSQL non réglé, k6 et la base sur la même machine. Faites le test final sur une préproduction
  configurée comme la production (PHP-FPM, OPcache, réglages PostgreSQL), avec l'accord de l'équipe.
