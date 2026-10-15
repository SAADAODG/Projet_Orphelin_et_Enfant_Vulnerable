# Performances à fort volume — audit et corrections

Mesures sur une base séparée `plateforme_oev_perf` (PostgreSQL, données générées), 100 000 puis 1 000 000 de
dossiers OEV, 4 profils (administrateur, agent DGFE, DR, DP), `memory_limit = 128M`, `max_execution_time = 30`.
La base de développement n'a jamais servi aux mesures.

## Ce que l'équipe doit faire

1. **Migrations** : `php artisan migrate` (deux migrations du 15/10/2026). Sans elles, l'enregistrement d'un OEV
   et la sélection échouent (colonne `oevs.score_besoin` absente).
   - `2026_10_15_000001_add_performance_indexes` : index (instantané sur une petite base).
   - `2026_10_15_000002_add_score_besoin_to_oevs_table` : ajoute le score et le calcule pour tous les dossiers
     (≈ 6 min pour 1 000 000 de dossiers). Sur une grosse base, lancer ensuite `VACUUM ANALYZE oevs;`
     (la mise à jour réécrit toutes les lignes).
2. **Import en SQL direct** de dossiers (hors application) : recalculer les scores avec
   `App\Services\Parrainage\ScoreBesoin::recalculerTout()` (ou `recalculerIds([...])`).
3. **Traitement en masse par le modèle** (import PHP) : l'envelopper dans `ScoreBesoin::differer(fn () => …)`,
   les scores sont alors recalculés en une fois à la fin.
4. **Serveurs PHP** : OPcache est inactif sur le poste de développement (mémoire partagée pleine : 128 Mo pour
   tous les projets). Chaque requête paie ≈ 350 ms de démarrage, même une redirection. Réglage conseillé dans
   `php.ini` : `opcache.memory_consumption=512`, `opcache.interned_strings_buffer=64`,
   `opcache.max_accelerated_files=32531`, puis redémarrer tous les serveurs PHP. Avec `php artisan serve`,
   c'est `opcache.enable_cli` qui compte.
5. Utiliser `http://127.0.0.1:8000` plutôt que `localhost` : le serveur n'écoute qu'en IPv4.

## Outils (scripts/perf)

| Script | Rôle |
|---|---|
| `_amorce.php` | Refuse toute base dont le nom ne finit pas par `_perf` (variable `PERF_DB`). |
| `preparer-base.php` | Crée la base `_perf`, migrations et données de démonstration. |
| `generer-volume.php N` | Ajoute N dossiers (SQL pur, `generate_series`) et les données liées : appuis, sessions, sélection, suivis, journal, signalements, plaintes. Rejouable. |
| `migrer.php` | Migrations en attente sur la base `_perf`. |
| `profiler.php URL email` | Une requête rejouée : démarrage, connexion, nombre / durée SQL, plus lentes, répétées (N+1), vues, mémoire, taille. |
| `lancer-profils.php [fichier.json]` | Toutes les pages et exports, 4 profils, **un processus par mesure** avec les vraies limites : un plantage n'arrête pas la suite. |
| `expliquer.php URL email N` | `EXPLAIN ANALYZE` des N requêtes les plus lentes d'une page. |
| `mesurer-http.php URL N` | Temps HTTP réels (connexion avec jeton CSRF, N répétitions, min / moyenne / max). Refuse le port 8000 (base de développement). |
| `cout-excel.php` | Coût mémoire et durée d'un export Excel par cellule. |

Serveur HTTP de mesure (base `_perf`), depuis `public/` :
`DB_DATABASE=plateforme_oev_perf php -S 127.0.0.1:8098 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
(`php artisan serve` ne transmet pas `DB_DATABASE` au serveur et viserait la base de développement).

## Causes

1. **Listes chargées entièrement en mémoire** puis comptées, triées ou découpées en PHP : pilotage (tous les OEV
   de la zone, puis le profil objet par objet), estimation du coût des éligibles (parcours de tous les éligibles),
   liste pour un parrain, liste pour paiement, fiche de session (tous les retenus affichés).
2. **Index manquants** : PostgreSQL n'indexe pas les clés étrangères (`region_id`, `province_id`, `oev_id`…) ;
   aucun index ne permettait de compter sans lire la table (650 Mo à 1 000 000 de dossiers).
3. **Valeur calculée empêchant l'usage de la base** : le score d'urgence de la sélection était recalculé
   (≈ 15 `CASE` et `LIKE`) sur chaque ligne à chaque page.
4. **Exports sans limite** : PhpSpreadsheet garde tout le classeur en mémoire (≈ 0,7 Ko par cellule, mesuré) ;
   lecture par pages (`OFFSET`) retriant toute la sélection à chaque paquet ; ramasse-miettes de PHP reparcourant
   les centaines de milliers de cellules (×4 sur la durée).
5. **Sous-requêtes « IN (tous les OEV de la zone) »** et listes d'identifiants dans la requête (au-delà de 65 535
   paramètres, PostgreSQL refuse la requête).

## Corrections, fichier par fichier

| Fichier | Correction |
|---|---|
| `database/migrations/2026_10_15_000001_add_performance_indexes.php` | Index des clés de zone, des tris par état et date ; index **partiels** (`WHERE deleted_at IS NULL`, comme toutes les requêtes) : `oevs_comptages_index` (comptages sans lire la table), intégration, tris du filtrage. |
| `database/migrations/2026_10_15_000002_add_score_besoin_to_oevs_table.php` | Colonne `score_besoin` indexée, remplie avec l'expression SQL du barème. |
| `app/Services/Parrainage/ScoreBesoin.php`, `app/Observers/ScoreBesoinObserver.php` | Score recalculé **en SQL direct** à la création et à la modification d'un critère (hors journal d'audit) ; mode différé ; recalcul global. |
| `app/Services/Parrainage/SelectionParrainage.php` | Tri par le score enregistré ; `estimationCout()` en une requête d'agrégat ; listes définitive / d'attente lues en lignes brutes ; requêtes SQL des listes (`SourceSelectionEnBase`). |
| `app/Services/Parrainage/SourceSelectionEnBase.php` | Interface facultative : listes de la sélection fournies comme requêtes. Une source externe (tableaux) reste possible. |
| `app/Services/Parrainage/PilotageParrainage.php` | Indicateurs, couverture, budget, profil et résultats calculés par la base (`count`, `sum(case …)`, `EXISTS`) ; voie en mémoire par paquets pour une source externe. |
| `app/Services/Parrainage/ExtractionsParrainage.php` | Liste pour un parrain : comptée et triée par la base, aperçu limité, lecture par paquets ; exclusion des bénéficiaires de l'État par `NOT EXISTS` ; liste pour paiement lue par paquets en lignes brutes, comptée d'avance. |
| `app/Services/Parrainage/FichiersExtraction.php` | Limites d'export (250 000 cellules, 50 000 lignes, PDF 1 000 lignes) ; mémoire (512 Mo) et durée (300 s) relevées pour ces seules requêtes ; ramasse-miettes suspendu ; largeur automatique réservée aux petits fichiers ; `dansLOrdre()` : identifiants triés une fois, fiches par paquets. |
| `app/Http/Controllers/ExtractionController.php`, `JournalController.php`, `PilotageParrainageController.php` | Contrôle de taille **avant** de préparer le fichier, message explicite ; lecture par paquets. |
| `resources/views/extraction/index.blade.php`, `parrainage/pilotage/extractions.blade.php` | Boutons PDF / Excel désactivés au-delà des limites, avec l'explication (recalculée selon les données cochées). |
| `app/Http/Controllers/SessionParrainageController.php`, `parrainage/sessions/show.blade.php` | Retenus de la session paginés (50), nombre et montant par la base. |
| `app/Http/Controllers/DashboardController.php` | Évolution sur 6 mois en une requête ; « à traiter » lu état par état dans l'ordre de l'index. |

### Équivalence prouvée (valeurs stockées et calculs réécrits)

| Test | Ce qui est comparé |
|---|---|
| `ScoreBesoinStockeTest` | Score enregistré = ancien calcul PHP = ancienne expression SQL, sur **toutes** les combinaisons des critères (40 320) et tous les sous-ensembles de vulnérabilités graves ; recalcul après chaque type de modification ; absent du journal d'audit ; mode différé. Vérifié aussi sur PostgreSQL : 0 écart sur 1 000 000 de dossiers. |
| `PilotageEquivalenceTest` | Ancien calcul en mémoire (`tests/Support/PilotageParrainageAvant.php`) = nouveau, 72 combinaisons (niveau × session × type d'appui × sexe), bornes d'âge exactes, listes en mémoire **et** en base ; nombre compté d'avance de la liste pour paiement = nombre réel. |
| `ListeParrainEquivalenceTest` | Ancienne liste pour un parrain (`tests/Support/ExtractionsParrainageAvant.php`) = nouvelle (mêmes OEV, même ordre, même besoin estimé, même aperçu), 576 combinaisons. |
| `EstimationCoutEquivalenceTest` | Ancien parcours PHP = agrégat SQL, 36 combinaisons de session × dossiers couvrant chaque situation et chaque cas de frais. |

## Avant / après

Temps de traitement en ms (en processus, base `_perf`). « PLANTE » : mémoire épuisée ou 30 s dépassées.

| Page | 100 000 avant | 1 000 000 avant | 1 000 000 après |
|---|---|---|---|
| Tableau de bord (central / DR) | 592 / 870 | 6 033 / 2 169 | **539 / 167** |
| Liste des OEV (central) | 274 | 967 | **275** |
| Constituer dossier, tous états (central) | 138 | 1 957 | **162** |
| Validation des dossiers (central) | 92 | 1 812 | **95** |
| Intégration des OEV (central) | 158 | 948 | **294** |
| Filtrage et extraction (central) | 288 | 917 | 903 |
| Fiche de session (central) | PLANTE | — | **284** |
| Sélection : éligibles (central / DP) | non mesurée | PLANTE / PLANTE | **2 594 / 824** |
| Suivi des parrainés (central) | 230 | 2 772 | **629** |
| Pilotage : tableau de bord (central / DR) | PLANTE / 1 998 | PLANTE / 4 279 | **2 827 / 735** |
| Pilotage : extractions (central) | PLANTE | — | **1 019** |
| Export filtrage Excel (central) | PLANTE | PLANTE | refus explicite (0,07 s) |
| Export filtrage Excel, DP (42 700 lignes × 4) | 1 024 (4 300 lignes) | 45 610 | **8 007** — fichier relu : 42 719 lignes, 767 Ko, pic 188 Mo |
| Export liste parrain Excel, DP | 1 686 | 26 802 | **11 623** — 739 Ko, pic 198 Mo |
| Export paiement Excel (central, 135 000 bénéficiaires) | PLANTE | PLANTE | refus explicite (0,14 s) ; DR : 4 097, 277 Ko, pic 174 Mo |

Détail complet : `storage/perf/*.json` (non versionné), régénérable avec `lancer-profils.php`.

## Limites connues

- **Exports** : 250 000 cellules (ex. 20 833 lignes × 12 colonnes, 50 000 lignes × 5), PDF 1 000 lignes. Au-delà,
  le bouton est désactivé avec l'explication et le serveur refuse ; ≈ 0,2 à 0,3 ms par ligne et par colonne.
- **Recherche libre** (`%texte%` sur le nom, le code, le tuteur, les localités) : parcours de toute la zone ;
  1,5 s pour le niveau central à 1 000 000 de dossiers (0,2 s pour un DR). Un index trigramme (`pg_trgm`)
  rendrait ces recherches rapides, mais il faut installer une extension PostgreSQL.
- **Indicateurs nationaux** (pilotage, synthèse du filtrage, estimation de la sélection) : 1 à 3 s à
  1 000 000 de dossiers pour le niveau central, proportionnel au volume. **Non mesuré à 20 000 000** : prévoir
  des compteurs tenus à jour (table d'agrégats alimentée par observateurs, périodes closes figées) avant
  d'atteindre plusieurs millions de dossiers.
- Temps HTTP réels ≈ +350 ms par requête tant qu'OPcache est inactif (voir plus haut).

## Reste à faire

- Index trigramme pour la recherche libre (extension `pg_trgm`, à valider).
- Compteurs pré-agrégés pour les indicateurs nationaux, si le volume dépasse quelques millions de dossiers.
- Mesure à 20 000 000 (génération ≈ 2 h 30, plusieurs dizaines de Go).
