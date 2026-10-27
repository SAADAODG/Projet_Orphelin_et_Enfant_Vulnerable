<?php

/**
 * Générateur de volume, en SQL pur (generate_series), pour la base de PERFORMANCE uniquement.
 *
 *   php scripts/perf/generer-volume.php 100000      → ajoute 100 000 dossiers OEV et les données liées
 *
 * Rejouable : chaque exécution AJOUTE des lignes (identifiants et numéros dérivés des séquences).
 * Répartition réaliste : toutes les communes, tous les états du circuit (≈ 70 % intégrés), ≈ 3 % de
 * désactivations, vulnérabilités variées ; et, proportionnellement : appuis de partenaires (8 % des
 * intégrés), une session par lot avec 15 % de retenus, suivis des retenus,
 * journal d'audit (1 entrée par dossier), signalements (1/10) et plaintes (1/20).
 */

require __DIR__ . '/_amorce.php';
require __DIR__ . '/../../vendor/autoload.php';

$n = (int) ($argv[1] ?? 0);
if ($n < 1) {
    fwrite(STDERR, "Usage : php scripts/perf/generer-volume.php <nombre de dossiers OEV>\n");
    exit(1);
}

$pdo = perf_pdo();
echo 'Base : ' . $pdo->query('select current_database()')->fetchColumn() . "\n";
$debutTotal = microtime(true);

$etape = function (string $libelle, string $sql) use ($pdo) {
    $debut = microtime(true);
    $lignes = $pdo->exec($sql);
    printf("  %-48s %10s lignes  %6.1f s\n", $libelle, number_format((int) $lignes, 0, ',', ' '), microtime(true) - $debut);
};

// Référentiels nécessaires
$utilisateurs = $pdo->query("select u.id, r.name from users u join model_has_roles m on m.model_id = u.id join roles r on r.id = m.role_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$dp = array_search('DP', $utilisateurs, true) ?: array_key_first($utilisateurs);
$dr = array_search('DR', $utilisateurs, true) ?: $dp;
$central = array_search('agent DGFE', $utilisateurs, true) ?: $dp;
if ((int) $pdo->query('select count(*) from parrains')->fetchColumn() < 50) {
    $etape('parrains (50)', "insert into parrains (type, nom, actif, created_at, updated_at)
        select (array['ong','association','entreprise','institution','particulier'])[1 + g % 5], 'Parrain perf ' || g, g % 10 <> 0, now(), now()
        from generate_series(1, 50) g");
}

$premierOev = (int) $pdo->query("select coalesce(max(id), 0) + 1 from oevs")->fetchColumn();
$lot = (int) $pdo->query("select count(*) from sessions_parrainage")->fetchColumn() + 1;

// Dossiers OEV, par paquets de 100 000 (journal de transactions raisonnable)
$taille = 100_000;
for ($fait = 0; $fait < $n; $fait += $taille) {
    $nb = min($taille, $n - $fait);
    $etape("oevs (paquet " . (intdiv($fait, $taille) + 1) . ')', <<<SQL
        with communes_num as (
            select c.id, c.province_id, p.region_id, row_number() over (order by c.id) as rang from communes c join provinces p on p.id = c.province_id
        ), total as (select count(*) as n from communes_num),
        lignes as (
            select g, nextval(pg_get_serial_sequence('oevs', 'id')) as id, (g::bigint * 7919) % 1000 as h from generate_series(1, {$nb}) g
        )
        insert into oevs (id, numero_dossier, statut_dossier, code, nom, prenom, sexe, date_naissance, statut, region_id, province_id, commune_id,
            nom_tuteur, prenom_tuteur, contact_tuteur, tuteur_lien, handicap, vulnerabilites, situation_scolaire, niveau_etude, systeme_educatif,
            classe, frais_scolarite, etablissement_actuel, type_etablissement, niveau_priorite, mere_vivante, pere_vivant,
            desactivation_etat, desactivation_motif, desactive_at, desactive_par, created_by, soumis_at, verifie_at, integre_at, created_at, updated_at)
        select l.id,
            'DOS-P-' || lpad(l.id::text, 9, '0'),
            e.etat,
            case when e.etat = 'integre' then 'OEV-P-' || lpad(l.id::text, 9, '0') end,
            (array['OUEDRAOGO','SAWADOGO','KABORE','ZONGO','TRAORE','COMPAORE','SANKARA','OUATTARA','DIALLO','KONATE','ILBOUDO','NIKIEMA'])[1 + l.h % 12],
            (array['Awa','Issa','Mariam','Moussa','Fatimata','Adama','Aminata','Boukary','Salif','Rasmata','Ibrahim','Safiatou'])[1 + (l.h / 12) % 12],
            case when l.g % 2 = 0 then 'F' else 'M' end,
            date '2008-01-01' + (l.g * 37 % 5400),
            (array['orphelin_pere','orphelin_mere','orphelin_double','vulnerable'])[1 + l.g % 4],
            c.region_id, c.province_id, c.id,
            'TUTEUR', 'Perf', '+226 70 ' || lpad((l.g % 1000000)::text, 6, '0'),
            (array['mere','pere','grand_parent','oncle_tante','frere_soeur'])[1 + l.g % 5],
            l.h < 50,
            (array['["orphelin"]','["orphelin","precarite"]','["abandon","detresse"]','["handicap"]','["travail","precarite"]','["separe"]'])[1 + l.g % 6]::json,
            case when l.h < 820 then 'scolarise' when l.h < 900 then 'non_scolarise' else 'descolarise' end,
            (array['primaire','post_primaire_secondaire','prescolaire','superieur'])[1 + l.g % 4],
            'classique',
            case when l.h < 820 then (array['CP1','CE1','CM2','6e','4e','2nde','Tle'])[1 + l.g % 7] end,
            case when l.h < 820 then 15000 + (l.g % 20) * 5000 end,
            case when l.h < 820 then 'École perf ' || (l.g % 500) end,
            case when l.h < 820 then (array['public','prive'])[1 + l.g % 2] end,
            (array['faible','moyen','eleve'])[1 + l.g % 3],
            case when l.g % 4 in (1, 2) then 'non' else 'oui' end,
            case when l.g % 4 in (0, 2) then 'non' else 'oui' end,
            case when e.etat = 'integre' and l.h % 37 = 0 then 'desactive' when e.etat = 'integre' and l.h % 101 = 0 then 'demandee' end,
            case when e.etat = 'integre' and (l.h % 37 = 0 or l.h % 101 = 0) then (array['deces','majorite','fin_vulnerabilite','perdu_de_vue'])[1 + l.g % 4] end,
            case when e.etat = 'integre' and l.h % 37 = 0 then now() - (l.g % 300) * interval '1 day' end,
            case when e.etat = 'integre' and l.h % 37 = 0 then {$central} end,
            {$dp},
            case when e.etat <> 'brouillon' then now() - (l.g % 700) * interval '1 day' end,
            case when e.etat in ('valide','integre','complement') then now() - (l.g % 650) * interval '1 day' end,
            case when e.etat = 'integre' then now() - (l.g % 600) * interval '1 day' end,
            now() - (l.g % 730) * interval '1 day', now()
        from lignes l
        cross join total t
        join communes_num c on c.rang = 1 + (l.g::bigint * 104729) % t.n
        cross join lateral (select case
            when l.h < 700 then 'integre' when l.h < 800 then 'brouillon' when l.h < 850 then 'soumis' when l.h < 900 then 'valide'
            when l.h < 930 then 'non_conforme' when l.h < 950 then 'complement' else 'rejete' end as etat) e
        SQL);
}
$dernierOev = (int) $pdo->query('select max(id) from oevs')->fetchColumn();
$plage = "o.id between {$premierOev} and {$dernierOev}";

// Score d'urgence enregistré (oevs.score_besoin), comme le ferait l'application : même expression SQL
if ((bool) $pdo->query("select 1 from information_schema.columns where table_name = 'oevs' and column_name = 'score_besoin'")->fetchColumn()) {
    $etape('score_besoin', 'update oevs set score_besoin = ' . App\Services\Parrainage\ScoreBesoin::sql() . " where id between {$premierOev} and {$dernierOev}");
}

// Appuis des partenaires : 8 % des OEV intégrés du lot
$etape('appuis_partenaires', "insert into appuis_partenaires (oev_id, parrain_id, annee, nature_appui_id, montant, created_by, created_at, updated_at)
    select o.id, (select min(id) from parrains) + o.id % 50, '2026-2027', (select id from natures_appui order by id offset (o.id % (select count(*) from natures_appui)) limit 1),
        case when o.id % 3 = 0 then null else 10000 + (o.id % 10) * 5000 end, {$dp}, now(), now()
    from oevs o where {$plage} and o.statut_dossier = 'integre' and o.id % 100 < 8");

// Une session par lot : 15 % des intégrés retenus
$pdo->exec("insert into sessions_parrainage (description, annee, numero, type_appui, enveloppe, plafond_beneficiaire,
    date_ouverture, quotas_actifs, exclure_deja_appuyes, etat, created_by, created_at, updated_at)
    values ('Session perf {$lot}', '2026-2027', {$lot}, 'scolaire', 9000000000, 75000, current_date, false, false, 'validee', {$central}, now(), now())");
$session = (int) $pdo->query('select max(id) from sessions_parrainage')->fetchColumn();
$etape('beneficiaires_session', "insert into beneficiaires_session (session_parrainage_id, oev_id, type_appui, statut, score, region_id, frais_reels, frais_estimes, montant_retenu, created_by, created_at, updated_at)
    select {$session}, o.id, 'scolaire', 'retenu',
        (o.id % 90), o.region_id, o.frais_scolarite, false, least(coalesce(o.frais_scolarite, 75000), 75000), {$central}, now(), now()
    from oevs o where {$plage} and o.statut_dossier = 'integre' and o.desactivation_etat is null and o.id % 100 < 15");
$etape('suivis_parrainage', "insert into suivis_parrainage (beneficiaire_session_id, oev_id, annee, type_appui, resultat, moyenne, a_reconduire, renseigne_par, created_at, updated_at)
    select b.id, b.oev_id, '2026-2027', 'scolaire', (array['admis','redouble','abandon'])[1 + b.id % 3], 8 + (b.id % 10) * 0.9, b.id % 3 <> 2, {$dp}, now(), now()
    from beneficiaires_session b where b.session_parrainage_id = {$session} and b.statut = 'retenu' and b.id % 2 = 0");

// Journal d'audit : une entrée par dossier
$etape('journal_activites', "insert into journal_activites (user_id, action, sujet_type, sujet_id, description, details, created_at)
    select {$dp}, (array['oev.creation','oev.etat','oev.modification'])[1 + o.id % 3], 'App\\Models\\Oev', o.id,
        'Action perf sur le dossier ' || o.numero_dossier, '{\"ip\":\"127.0.0.1\"}'::json, o.created_at
    from oevs o where {$plage}");

// Signalements (1 pour 10 dossiers) et plaintes (1 pour 20)
$etape('signalements', "insert into signalements (recepisse, enfant_nom, enfant_prenom, enfant_age, vulnerabilite, region_id, province_id, commune_id, localite,
    declarant_nom, declarant_prenom, declarant_telephone, declarant_adresse, declarant_profession, declarant_lien, statut, created_at, updated_at)
    select 'SIG-P-' || lpad(o.id::text, 9, '0'), o.nom, o.prenom, 8 + o.id % 9, (array['orphelin','handicap','rue','precarite'])[1 + o.id % 4],
        o.region_id, o.province_id, o.commune_id, 'Quartier perf', 'DECLARANT', 'Perf', '+226 71 000000', 'Adresse', 'Commerçant', 'voisin',
        (array['en_attente','valide','rejete','cloture'])[1 + o.id % 4], o.created_at, o.created_at
    from oevs o where {$plage} and o.id % 10 = 0");
$etape('plaintes', "insert into plaintes (reference, objet, description, region_id, province_id, commune_id, anonyme, statut, created_at, updated_at)
    select 'PL-P-' || lpad(o.id::text, 9, '0'), (array['contribution','mecontentement','reconnaissance','appreciation'])[1 + o.id % 4],
        'Description perf', o.region_id, o.province_id, o.commune_id, true, (array['nouvelle','en_cours','traitee'])[1 + o.id % 3], o.created_at, o.created_at
    from oevs o where {$plage} and o.id % 20 = 0");

$etape('ANALYZE', 'analyze');
printf("Terminé en %.1f s. Dossiers OEV dans la base : %s\n", microtime(true) - $debutTotal, number_format((int) $pdo->query('select count(*) from oevs')->fetchColumn(), 0, ',', ' '));
