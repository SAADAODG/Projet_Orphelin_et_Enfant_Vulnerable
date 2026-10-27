<?php

/**
 * Base de PERFORMANCE : rattache les OEV scolarisés générés à une école de leur commune, donne une banque et
 * un RIB à 80 % des écoles, et reporte l'établissement sur les bénéficiaires des sessions (onglet « Paiement »).
 *
 *   php scripts/perf/rattacher-etablissements.php
 */

require __DIR__ . '/_amorce.php';

$pdo = perf_pdo();
$debut = microtime(true);
$etape = function (string $libelle, string $sql) use ($pdo, $debut) {
    printf("  %-40s %10s lignes  %6.1f s\n", $libelle, number_format((int) $pdo->exec($sql), 0, ',', ' '), microtime(true) - $debut);
};

$etape('OEV scolarisés → école de la commune', <<<'SQL'
    with e as (
        select id, commune_id, row_number() over (partition by commune_id order by id) as rn, count(*) over (partition by commune_id) as n
        from ecoles where nature = 'ecole'
    )
    update oevs o set ecole_id = e.id
    from e
    where e.commune_id = o.commune_id and e.rn = 1 + (o.id % e.n) and o.situation_scolaire = 'scolarise' and o.ecole_id is null
    SQL);
$etape('Banque et RIB (80 % des écoles)', <<<'SQL'
    update ecoles set banque = (array['Coris Bank', 'Ecobank', 'BOA', 'SGBF'])[1 + id % 4],
        rib = 'BF' || lpad((id % 999)::text, 3, '0') || ' 01001 ' || lpad(id::text, 12, '0') || ' ' || lpad((id % 97)::text, 2, '0')
    where id % 5 <> 0 and rib is null
    SQL);
$etape('Bénéficiaires → établissement', <<<'SQL'
    update beneficiaires_session b set etablissement_id = o.ecole_id
    from oevs o where o.id = b.oev_id and b.type_appui <> 'formation_professionnelle'
    SQL);
$etape('ANALYZE', 'analyze');
