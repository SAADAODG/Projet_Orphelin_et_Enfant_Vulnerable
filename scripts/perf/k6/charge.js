/**
 * Test de montée en charge : des agents connectés naviguent entre les pages les plus utilisées.
 * Mode d'emploi complet : scripts/perf/k6/README.md.
 *
 *   k6 run -e COMPTES=agent@oev.gov.bf:motdepasse,dp@oev.gov.bf:motdepasse \
 *          -e BASE_URLS=http://127.0.0.1:8098,http://127.0.0.1:8099 -e PROFIL=charge -e VUS=20 \
 *          scripts/perf/k6/charge.js
 *
 * Profils : fumee (2 utilisateurs, 30 s : valider le script), charge (montée, palier, descente jusqu'à VUS),
 * pic (paliers croissants jusqu'à VUS).
 *
 * Pièges gérés :
 * - chaque compte se connecte UNE fois, dans setup() ; ses cookies de session sont partagés par les
 *   utilisateurs virtuels (pas de connexion répétée, pas de limite de tentatives) ;
 * - k6 vide le stock de cookies par défaut à chaque itération : chaque utilisateur virtuel a son propre
 *   http.CookieJar(), passé à chaque requête, et les redirections ne sont pas suivies (redirects: 0) pour
 *   repérer un renvoi vers /login (métrique « sessions_perdues ») ;
 * - droits par rôle : au démarrage, chaque page est essayée pour chaque compte et seules celles qui
 *   répondent 200 sont gardées ; les identifiants (dossiers, sessions, parrains) sont relevés par compte,
 *   car un DR ou un DP ne voit que sa zone ;
 * - pages publiques (formulaires de signalement et de plainte) exclues ;
 * - garde-fou : seule la machine locale (127.0.0.1) est visée, sauf -e CONFIRMER_CIBLE=oui.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// --- Configuration -------------------------------------------------------------------------------

const BASES = (__ENV.BASE_URLS || __ENV.BASE_URL || 'http://127.0.0.1:8098')
    .split(',').map((u) => u.trim().replace(/\/+$/, '')).filter((u) => u !== '');
const PROFIL = __ENV.PROFIL || 'fumee';
const VUS = parseInt(__ENV.VUS || '20', 10);
const PALIER = __ENV.PALIER || '3m';
const RESULTATS = (__ENV.RESULTATS || 'scripts/perf/k6/resultats').replace(/\/+$/, '');

for (const base of BASES) {
    const hote = (base.match(/^https?:\/\/([^/:]+)/) || [])[1];
    if (hote !== '127.0.0.1' && __ENV.CONFIRMER_CIBLE !== 'oui') {
        throw new Error(`REFUS : la cible ${base} n'est pas la machine locale (127.0.0.1). `
            + 'Un test de charge sur un serveur partagé ou de production demande un accord explicite : -e CONFIRMER_CIBLE=oui.');
    }
}

const COMPTES = (__ENV.COMPTES || '').split(',').map((c) => c.trim()).filter((c) => c !== '').map((c) => {
    const separateur = c.indexOf(':');
    if (separateur < 1) {
        throw new Error(`COMPTES : « ${c} » n'a pas la forme email:motdepasse.`);
    }
    return { email: c.slice(0, separateur), motDePasse: c.slice(separateur + 1) };
});
if (COMPTES.length === 0) {
    throw new Error('Indiquez les comptes : -e COMPTES=email:motdepasse,email:motdepasse (aucun mot de passe n’est écrit dans le script).');
}

/**
 * Pages visitées et poids (fréquence relative d'utilisation). {oev}, {session}, {parrain} : identifiants
 * relevés au démarrage pour chaque compte. Les exports (Excel, PDF) ne sont pas de la navigation : exclus.
 */
const PAGES = [
    { nom: 'Tableau de bord', chemin: '/admin/dashboard', poids: 12 },
    { nom: 'Liste des OEV', chemin: '/admin/liste-oev', poids: 12 },
    { nom: 'Liste des OEV (recherche)', chemin: '/admin/liste-oev?q={recherche}', poids: 6 },
    { nom: 'Fiche OEV', chemin: '/admin/oevs/{oev}', poids: 14 },
    { nom: 'Constituer dossier', chemin: '/admin/oevs?etat=tous', poids: 8 },
    { nom: 'Validation des dossiers', chemin: '/admin/validation-dossiers?etat=tous', poids: 6 },
    { nom: 'Intégration des OEV', chemin: '/admin/integration-oev?etat=tous', poids: 6 },
    { nom: 'OEV sortants', chemin: '/admin/oevs-sortants', poids: 3 },
    { nom: 'Filtrage et extraction', chemin: '/admin/filtrage-extraction', poids: 5 },
    { nom: 'Sessions de parrainage', chemin: '/admin/parrainage/sessions', poids: 4 },
    { nom: 'Fiche de session', chemin: '/admin/parrainage/sessions/{session}', poids: 3 },
    { nom: 'Sélection : liste', chemin: '/admin/parrainage/sessions/{session}/selection?onglet=liste', poids: 3 },
    { nom: 'Suivi des parrainés', chemin: '/admin/parrainage/suivis', poids: 3 },
    { nom: 'Parrains', chemin: '/admin/parrainage/parrains', poids: 2 },
    { nom: 'Fiche parrain', chemin: '/admin/parrainage/parrains/{parrain}', poids: 2 },
    { nom: 'Appuis', chemin: '/admin/parrainage/appuis', poids: 3 },
    { nom: 'Pilotage', chemin: '/admin/parrainage/pilotage', poids: 3 },
    { nom: 'Signalements', chemin: '/admin/signalements', poids: 2 },
    { nom: 'Journal d’audit', chemin: '/admin/journal', poids: 2 },
];
const RECHERCHES = ['sank', 'oued', 'kabo', 'awa', 'issa', 'traore'];

// Pages où relever les identifiants des enregistrements visibles par le compte
const RELEVES = [
    { cle: 'oev', chemin: '/admin/liste-oev', motif: /\/admin\/oevs\/(\d+)["'?#]/g },
    { cle: 'session', chemin: '/admin/parrainage/sessions', motif: /\/admin\/parrainage\/sessions\/(\d+)["'?#]/g },
    { cle: 'parrain', chemin: '/admin/parrainage/parrains', motif: /\/admin\/parrainage\/parrains\/(\d+)["'?#]/g },
];

// --- Profils et seuils ---------------------------------------------------------------------------

const SCENARIOS = {
    fumee: { executor: 'constant-vus', vus: 2, duration: '30s' },
    charge: {
        executor: 'ramping-vus', startVUs: 0, gracefulRampDown: '30s',
        stages: [{ duration: '1m', target: VUS }, { duration: PALIER, target: VUS }, { duration: '30s', target: 0 }],
    },
    pic: {
        executor: 'ramping-vus', startVUs: 0, gracefulRampDown: '30s',
        stages: [1, 2, 3, 4].flatMap((quart) => {
            const cible = Math.max(1, Math.round((VUS * quart) / 4));
            return [{ duration: '20s', target: cible }, { duration: '1m', target: cible }];
        }).concat([{ duration: '30s', target: 0 }]),
    },
};
if (!SCENARIOS[PROFIL]) {
    throw new Error(`PROFIL inconnu « ${PROFIL} » : fumee, charge ou pic.`);
}

const NAVIGATION = '{phase:navigation}';
const seuils = {
    [`http_req_failed${NAVIGATION}`]: ['rate<0.01'],
    sessions_perdues: ['rate<0.01'],
    checks: ['rate>0.99'],
    [`http_req_duration${NAVIGATION}`]: ['p(95)<2000'],
};
// Sous-métriques par page (tableau du résumé) : k6 ne les calcule que si un seuil les cite
for (const page of PAGES) {
    seuils[`http_req_duration{page:${page.nom}}`] = ['max>=0'];
    seuils[`http_reqs{page:${page.nom}}`] = ['count>=0'];
}

export const options = {
    scenarios: { navigation: Object.assign({ tags: { phase: 'navigation' } }, SCENARIOS[PROFIL]) },
    thresholds: seuils,
    setupTimeout: '10m',
    summaryTrendStats: ['med', 'p(95)', 'max', 'avg'],
};

const sessionsPerdues = new Rate('sessions_perdues');
const pagesVisitees = new Counter('pages_visitees');
// Durée du démarrage (connexions, essais des pages) : retirée de la durée du test pour le débit
const demarrage = new Trend('demarrage', true);

// --- Démarrage : connexion de chaque compte, pages permises, identifiants ------------------------

const ESSAI = { redirects: 0, tags: { phase: 'setup' }, responseCallback: http.expectedStatuses({ min: 200, max: 499 }), timeout: '120s' };

function connecter(compte, base) {
    const jar = new http.CookieJar();
    const page = http.get(`${base}/login`, Object.assign({ jar }, ESSAI));
    const jeton = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
    if (!jeton) {
        throw new Error(`Jeton CSRF introuvable sur ${base}/login (HTTP ${page.status}).`);
    }
    const reponse = http.post(`${base}/login`, { _token: jeton, email: compte.email, password: compte.motDePasse },
        Object.assign({ jar }, ESSAI));
    const destination = reponse.headers.Location || '';
    if (reponse.status !== 302 || destination.includes('/login')) {
        throw new Error(`Connexion refusée pour ${compte.email} (HTTP ${reponse.status}).`);
    }
    // Cookies de session : valables sur tous les serveurs (même APP_KEY, sessions en base)
    const cookies = {};
    for (const [nom, valeurs] of Object.entries(jar.cookiesForURL(`${base}/`))) {
        cookies[nom] = valeurs[0];
    }
    return cookies;
}

function stockDeCookies(cookies, base) {
    const jar = new http.CookieJar();
    for (const [nom, valeur] of Object.entries(cookies)) {
        jar.set(base, nom, valeur, { path: '/' });
    }
    return jar;
}

function remplir(chemin, ids) {
    return chemin
        .replace('{oev}', () => choisir(ids.oev))
        .replace('{session}', () => choisir(ids.session))
        .replace('{parrain}', () => choisir(ids.parrain))
        .replace('{recherche}', () => choisir(RECHERCHES));
}

export function setup() {
    const debut = Date.now();
    const base = BASES[0];
    const comptes = COMPTES.map((compte) => {
        const cookies = connecter(compte, base);
        const jar = stockDeCookies(cookies, base);

        const ids = {};
        for (const releve of RELEVES) {
            const corps = http.get(base + releve.chemin, Object.assign({ jar }, ESSAI)).body || '';
            ids[releve.cle] = [...new Set([...corps.matchAll(releve.motif)].map((m) => m[1]))].slice(0, 50);
        }

        const pages = PAGES.filter((page) => {
            const besoin = (page.chemin.match(/\{(oev|session|parrain)\}/) || [])[1];
            if (besoin && ids[besoin].length === 0) {
                return false;
            }
            return http.get(base + remplir(page.chemin, ids), Object.assign({ jar }, ESSAI)).status === 200;
        });
        console.log(`${compte.email} : ${pages.length} page(s) permise(s) sur ${PAGES.length} · `
            + `${ids.oev.length} dossier(s), ${ids.session.length} session(s), ${ids.parrain.length} parrain(s) relevé(s)`);
        if (pages.length === 0) {
            throw new Error(`${compte.email} : aucune page accessible.`);
        }
        return { email: compte.email, cookies, ids, pages: pages.map((p) => p.nom) };
    });

    // Vérification finale : chaque session est valide sur chaque serveur, depuis un stock de cookies vierge
    for (const compte of comptes) {
        for (const serveur of BASES) {
            const reponse = http.get(`${serveur}/admin/dashboard`, Object.assign({ jar: stockDeCookies(compte.cookies, serveur) }, ESSAI));
            if (reponse.status !== 200) {
                throw new Error(`Session de ${compte.email} refusée par ${serveur} (HTTP ${reponse.status}).`);
            }
        }
    }
    demarrage.add(Date.now() - debut);
    return { comptes };
}

// --- Navigation d'un utilisateur virtuel ---------------------------------------------------------

let jar = null;
let compte = null;
let base = null;
let pagesPermises = null;

function choisir(liste) {
    return liste[Math.floor(Math.random() * liste.length)];
}

function tirerPage() {
    const total = pagesPermises.reduce((somme, page) => somme + page.poids, 0);
    let tirage = Math.random() * total;
    for (const page of pagesPermises) {
        tirage -= page.poids;
        if (tirage < 0) {
            return page;
        }
    }
    return pagesPermises[pagesPermises.length - 1];
}

export default function (donnees) {
    if (jar === null) {
        // Premier passage de cet utilisateur virtuel : son compte, son serveur, son propre stock de cookies
        compte = donnees.comptes[(__VU - 1) % donnees.comptes.length];
        base = BASES[(__VU - 1) % BASES.length];
        jar = stockDeCookies(compte.cookies, base);
        pagesPermises = PAGES.filter((page) => compte.pages.includes(page.nom));
    }

    const page = tirerPage();
    const reponse = http.get(base + remplir(page.chemin, compte.ids), {
        jar,
        redirects: 0,
        tags: { page: page.nom, name: page.nom },
        responseCallback: http.expectedStatuses(200),
        timeout: '60s',
    });
    const perdue = reponse.status >= 300 && reponse.status < 400 && (reponse.headers.Location || '').includes('/login');
    sessionsPerdues.add(perdue);
    pagesVisitees.add(1);
    check(reponse, { 'page affichée (200)': (r) => r.status === 200 });

    // Temps de réflexion de l'agent entre deux clics
    sleep(1 + Math.random() * 3);
}

// --- Résumé : tableau par page, en Markdown et en JSON -------------------------------------------

function ms(valeur) {
    return valeur === undefined ? '—' : `${Math.round(valeur)}`;
}

function pourcentage(metrique) {
    return metrique ? `${(metrique.values.rate * 100).toFixed(2)} %` : '—';
}

export function handleSummary(data) {
    const m = data.metrics;
    // Durée de la navigation seule : durée du test moins le démarrage (setup)
    const duree = (data.state.testRunDurationMs - (m.demarrage ? m.demarrage.values.max : 0)) / 1000;
    const navigation = m[`http_req_duration${NAVIGATION}`];
    const requetes = m.pages_visitees ? m.pages_visitees.values.count : 0;
    const lignes = PAGES.map((page) => {
        const d = m[`http_req_duration{page:${page.nom}}`];
        const n = m[`http_reqs{page:${page.nom}}`];
        return d && n && n.values.count > 0 ? { page: page.nom, count: n.values.count, ...d.values } : null;
    }).filter((l) => l !== null).sort((a, b) => b['p(95)'] - a['p(95)']);
    const seuilsFranchis = Object.entries(m).filter(([, v]) => v.thresholds && Object.values(v.thresholds).some((t) => !t.ok)).map(([nom]) => nom);

    const horodatage = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    const nom = `${RESULTATS}/${PROFIL}-${SCENARIOS[PROFIL].vus || VUS}vu-${horodatage}`;
    const resume = {
        profil: PROFIL,
        utilisateurs_virtuels_max: m.vus_max ? m.vus_max.values.max : null,
        serveurs: BASES,
        comptes: COMPTES.map((c) => c.email),
        duree_s: Math.round(duree),
        requetes: requetes,
        requetes_par_seconde: duree ? Math.round((requetes / duree) * 10) / 10 : 0,
        erreurs_http: m[`http_req_failed${NAVIGATION}`] ? m[`http_req_failed${NAVIGATION}`].values.rate : null,
        sessions_perdues: m.sessions_perdues ? m.sessions_perdues.values.rate : null,
        verifications: m.checks ? m.checks.values.rate : null,
        mediane_ms: navigation ? navigation.values.med : null,
        p95_ms: navigation ? navigation.values['p(95)'] : null,
        max_ms: navigation ? navigation.values.max : null,
        seuils_franchis: seuilsFranchis,
        pages: lignes.map((l) => ({ page: l.page, requetes: l.count, mediane_ms: l.med, p95_ms: l['p(95)'], max_ms: l.max })),
    };

    const md = [
        `# Test de charge — profil ${PROFIL}`,
        '',
        `${resume.utilisateurs_virtuels_max} utilisateur(s) virtuel(s) au plus · ${BASES.length} serveur(s) · ${resume.duree_s} s · `
            + `${requetes} pages · ${resume.requetes_par_seconde} pages/s`,
        '',
        `| Erreurs HTTP | Sessions perdues | Vérifications | Médiane | p95 | Max |`,
        `|---|---|---|---|---|---|`,
        `| ${pourcentage(m[`http_req_failed${NAVIGATION}`])} | ${pourcentage(m.sessions_perdues)} | ${pourcentage(m.checks)} | `
            + `${ms(resume.mediane_ms)} ms | ${ms(resume.p95_ms)} ms | ${ms(resume.max_ms)} ms |`,
        '',
        seuilsFranchis.length ? `**Seuils franchis : ${seuilsFranchis.join(', ')}**` : 'Tous les seuils sont respectés.',
        '',
        '| Page | Requêtes | Médiane (ms) | p95 (ms) | Max (ms) |',
        '|---|---:|---:|---:|---:|',
        ...lignes.map((l) => `| ${l.page} | ${l.count} | ${ms(l.med)} | ${ms(l['p(95)'])} | ${ms(l.max)} |`),
        '',
    ].join('\n');

    return {
        stdout: `\n${md}\nRésultats : ${nom}.md et ${nom}.json\n`,
        [`${nom}.md`]: md,
        [`${nom}.json`]: JSON.stringify(resume, null, 2),
    };
}
