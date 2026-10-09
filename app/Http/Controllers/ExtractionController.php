<?php

namespace App\Http\Controllers;

use App\Models\Oev;
use App\Models\Region;
use App\Support\Perimetre;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Filtrage et extraction » : recherche multicritère dans les dossiers enfants / OEV et export CSV.
 * Chaque utilisateur ne voit que les dossiers de sa zone, à l'étape où il intervient (Oev::visiblesPar).
 */
class ExtractionController extends Controller
{
    private const PAR_PAGE = 25;

    /** Filtres à choix unique : champ => liste des valeurs autorisées. */
    private static function listes(): array
    {
        return [
            'statut_dossier' => Oev::ETATS,
            'statut' => Oev::STATUTS,
            'sexe' => Oev::SEXES,
            'groupe_population' => Oev::GROUPES_POPULATION,
            'lieu_de_vie' => Oev::LIEUX_DE_VIE,
            'mere_vivante' => Oev::PARENT_VIVANT,
            'pere_vivant' => Oev::PARENT_VIVANT,
            'tuteur_lien' => Oev::LIENS_TUTEUR,
            'source_revenu' => Oev::SOURCES_REVENU,
            'niveau_revenu' => Oev::NIVEAUX,
            'logement' => Oev::LOGEMENTS,
            'identifie_par' => Oev::IDENTIFIE_PAR,
            'niveau_priorite' => Oev::NIVEAUX,
            'situation_scolaire' => Oev::SITUATIONS_SCOLAIRES,
            'niveau_etude' => Oev::NIVEAUX_ETUDE,
            'classe' => Oev::toutesLesClasses(),
            'systeme_educatif' => Oev::SYSTEMES_EDUCATIFS,
            'type_etablissement' => Oev::TYPES_ETABLISSEMENT,
            'appreciation' => Oev::APPRECIATIONS,
            'performance_scolaire' => Oev::PERFORMANCES_SCOLAIRES,
        ];
    }

    /** Filtres Oui / Non. */
    private const OUI_NON = ['a_acte_naissance', 'handicap', 'maladie_chronique', 'formation_professionnelle', 'tuteur_a_cnib', 'tuteur_pret_continuer'];

    /** Dates sur lesquelles on peut filtrer une période. */
    public const DATES = [
        'integre_at' => 'Date d’intégration',
        'soumis_at' => 'Date de soumission au DR',
        'created_at' => 'Date de constitution',
        'date_identification' => 'Date d’identification',
    ];

    public const TRIS = [
        'recent' => 'Plus récents d’abord',
        'nom' => 'Nom (A → Z)',
        'age' => 'Âge (plus jeunes d’abord)',
        'localite' => 'Localité',
    ];

    public function index(Request $request): View
    {
        $filtres = $this->lireFiltres($request);
        $requete = $this->requete($request, $filtres);

        $oevs = (clone $requete)->with(['region', 'province', 'commune'])->paginate(self::PAR_PAGE)->withQueryString();

        $synthese = (clone $requete)->reorder()->toBase()->selectRaw(
            "count(*) as total,
             sum(case when sexe = 'F' then 1 else 0 end) as filles,
             sum(case when sexe = 'M' then 1 else 0 end) as garcons,
             sum(case when statut in ('orphelin_pere', 'orphelin_mere', 'orphelin_double') then 1 else 0 end) as orphelins,
             sum(case when situation_scolaire = 'scolarise' then 1 else 0 end) as scolarises,
             sum(case when handicap = 1 then 1 else 0 end) as handicap"
        )->first();

        return view('extraction.index', [
            'oevs' => $oevs,
            'synthese' => $synthese,
            'filtres' => $filtres,
            'nbFiltres' => count(array_filter($filtres, fn ($v, $cle) => ! in_array($cle, ['tri', 'date_champ'], true) && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH)),
            'listes' => self::listes(),
            'localites' => Perimetre::pour($request->user())->filtrerArborescence(Region::arborescence()),
        ]);
    }

    /** Export CSV (séparateur « ; », UTF-8 avec BOM : s'ouvre directement dans Excel) des dossiers filtrés. */
    public function export(Request $request): StreamedResponse
    {
        $requete = $this->requete($request, $this->lireFiltres($request))
            ->with(['region', 'province', 'commune', 'village', 'lieuNaissanceCommune', 'lieuProvenanceCommune']);
        $colonnes = $this->colonnesExport();

        return response()->streamDownload(function () use ($requete, $colonnes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, array_keys($colonnes), ';');
            foreach ($requete->lazy(500) as $oev) {
                fputcsv($sortie, array_map(fn (callable $valeur) => $valeur($oev), $colonnes), ';');
            }
            fclose($sortie);
        }, 'extraction-oev-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Filtres de la requête, limités aux valeurs connues (toute autre valeur est ignorée). */
    private function lireFiltres(Request $request): array
    {
        $texte = fn (string $cle) => trim((string) $request->query($cle)) ?: null;
        $entier = fn (string $cle) => is_numeric($request->query($cle)) ? max(0, (int) $request->query($cle)) : null;
        $date = function (string $cle) use ($request) {
            $valeur = (string) $request->query($cle);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) ? $valeur : null;
        };

        $filtres = ['q' => $texte('q')];
        foreach (['region_id', 'province_id', 'commune_id'] as $cle) {
            $filtres[$cle] = $entier($cle) ?: null;
        }
        foreach (self::listes() as $cle => $liste) {
            $valeur = (string) $request->query($cle);
            $filtres[$cle] = array_key_exists($valeur, $liste) ? $valeur : null;
        }
        $vulnerabilite = (string) $request->query('vulnerabilite');
        $filtres['vulnerabilite'] = array_key_exists($vulnerabilite, Oev::VULNERABILITES) ? $vulnerabilite : null;
        $handicap = (string) $request->query('type_handicap');
        $filtres['type_handicap'] = array_key_exists($handicap, Oev::TYPES_HANDICAP) ? $handicap : null;
        foreach (self::OUI_NON as $cle) {
            $filtres[$cle] = in_array($request->query($cle), ['0', '1'], true) ? $request->query($cle) : null;
        }
        $filtres['age_min'] = $entier('age_min');
        $filtres['age_max'] = $entier('age_max');
        $filtres['date_champ'] = array_key_exists((string) $request->query('date_champ'), self::DATES) ? $request->query('date_champ') : 'integre_at';
        $filtres['date_du'] = $date('date_du');
        $filtres['date_au'] = $date('date_au');
        $filtres['tri'] = array_key_exists((string) $request->query('tri'), self::TRIS) ? $request->query('tri') : 'recent';

        return $filtres;
    }

    private function requete(Request $request, array $f): Builder
    {
        $requete = Oev::visiblesPar($request->user());

        foreach (['region_id', 'province_id', 'commune_id'] as $cle) {
            $requete->when($f[$cle], fn ($q, $id) => $q->where($cle, $id));
        }
        foreach (array_keys(self::listes()) as $cle) {
            $requete->when($f[$cle], fn ($q, $valeur) => $q->where($cle, $valeur));
        }
        foreach (self::OUI_NON as $cle) {
            $requete->when($f[$cle] !== null, fn ($q) => $q->where($cle, $f[$cle] === '1'));
        }
        // Listes enregistrées en JSON (["orphelin","precarite"]) : on cherche la valeur entre guillemets
        $requete->when($f['vulnerabilite'], fn ($q, $v) => $q->where('vulnerabilites', 'like', '%"' . $v . '"%'));
        $requete->when($f['type_handicap'], fn ($q, $v) => $q->where('types_handicap', 'like', '%"' . $v . '"%'));

        // Âge en années révolues
        $requete->when($f['age_min'] !== null, fn ($q) => $q->whereDate('date_naissance', '<=', now()->subYears($f['age_min'])));
        $requete->when($f['age_max'] !== null, fn ($q) => $q->whereDate('date_naissance', '>', now()->subYears($f['age_max'] + 1)));

        $requete->when($f['date_du'], fn ($q, $d) => $q->whereDate($f['date_champ'], '>=', $d));
        $requete->when($f['date_au'], fn ($q, $d) => $q->whereDate($f['date_champ'], '<=', $d));

        $requete->when($f['q'], function ($q, $terme) {
            $q->where(function ($q) use ($terme) {
                foreach (['code', 'numero_dossier', 'nom', 'prenom', 'nom_tuteur', 'prenom_tuteur', 'mere_nom', 'pere_nom', 'etablissement_actuel', 'quartier'] as $champ) {
                    $q->orWhere($champ, 'like', "%{$terme}%");
                }
                $q->ouLocaliteContient("%{$terme}%");
            });
        });

        return match ($f['tri']) {
            'nom' => $requete->orderBy('nom')->orderBy('prenom'),
            'age' => $requete->orderByDesc('date_naissance'),
            'localite' => $requete->orderBy('region_id')->orderBy('province_id')->orderBy('commune_id')->orderBy('nom'),
            default => $requete->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /** Colonnes du fichier exporté : libellé => valeur pour un dossier. */
    private function colonnesExport(): array
    {
        $date = fn (?CarbonInterface $d) => $d?->format('d/m/Y') ?? '';
        $ouiNon = fn ($v) => $v === null ? '' : ($v ? 'Oui' : 'Non');
        $lib = fn (string $champ, array $liste) => fn (Oev $o) => $o->{$champ} === null ? '' : ($liste[$o->{$champ}] ?? $o->{$champ});
        $multi = fn (string $champ, array $liste) => fn (Oev $o) => implode(', ', $o->libelles($champ, $liste));

        return [
            'Code OEV' => fn (Oev $o) => $o->code ?? '',
            'N° de dossier' => fn (Oev $o) => $o->numero_dossier,
            'État du dossier' => fn (Oev $o) => $o->libelleEtat(),
            'Nom' => fn (Oev $o) => $o->nom,
            'Prénom(s)' => fn (Oev $o) => $o->prenom,
            'Sexe' => $lib('sexe', Oev::SEXES),
            'Date de naissance' => fn (Oev $o) => $date($o->date_naissance) . ($o->date_naissance_estimee ? ' (estimée)' : ''),
            'Âge' => fn (Oev $o) => $o->age(),
            'Lieu de naissance' => fn (Oev $o) => $o->lieuDeNaissance() ?? '',
            'Nationalité' => fn (Oev $o) => $o->nationalite ?? '',
            'Acte de naissance' => fn (Oev $o) => $ouiNon($o->a_acte_naissance),
            'N° acte de naissance' => fn (Oev $o) => $o->numero_acte_naissance ?? '',
            'Statut OEV' => $lib('statut', Oev::STATUTS),
            'Groupe de population' => $lib('groupe_population', Oev::GROUPES_POPULATION),
            'Région' => fn (Oev $o) => $o->region?->nom ?? '',
            'Province' => fn (Oev $o) => $o->province?->nom ?? '',
            'Commune' => fn (Oev $o) => $o->commune?->nom ?? '',
            'Village / secteur' => fn (Oev $o) => $o->village?->nom ?? '',
            'Quartier' => fn (Oev $o) => $o->quartier ?? '',
            'Lieu de provenance' => fn (Oev $o) => $o->lieuDeProvenance() ?? '',
            'Mère' => fn (Oev $o) => trim($o->mere_nom . ' ' . $o->mere_prenoms),
            'Mère vivante' => $lib('mere_vivante', Oev::PARENT_VIVANT),
            'Décès de la mère' => fn (Oev $o) => $date($o->mere_date_deces),
            'Père' => fn (Oev $o) => trim($o->pere_nom . ' ' . $o->pere_prenoms),
            'Père vivant' => $lib('pere_vivant', Oev::PARENT_VIVANT),
            'Décès du père' => fn (Oev $o) => $date($o->pere_date_deces),
            'Qui s’occupe de l’enfant' => $lib('tuteur_lien', Oev::LIENS_TUTEUR),
            'Parent / tuteur' => fn (Oev $o) => trim($o->nom_tuteur . ' ' . $o->prenom_tuteur),
            'Téléphone du tuteur' => fn (Oev $o) => $o->contact_tuteur ?? '',
            'CNIB du tuteur' => fn (Oev $o) => $o->tuteur_cnib ?? '',
            'Prêt à continuer' => fn (Oev $o) => $ouiNon($o->tuteur_pret_continuer),
            'Lieu de vie' => $lib('lieu_de_vie', Oev::LIEUX_DE_VIE),
            'Vulnérabilités' => $multi('vulnerabilites', Oev::VULNERABILITES),
            'Handicap' => fn (Oev $o) => $ouiNon($o->handicap),
            'Type de handicap' => $multi('types_handicap', Oev::TYPES_HANDICAP),
            'Maladie' => fn (Oev $o) => $o->maladie_chronique ? ($o->maladie_nom ?: 'Oui') : $ouiNon($o->maladie_chronique),
            'Source de revenu' => $lib('source_revenu', Oev::SOURCES_REVENU),
            'Niveau de revenu' => $lib('niveau_revenu', Oev::NIVEAUX),
            'Logement' => $lib('logement', Oev::LOGEMENTS),
            'Situation scolaire' => $lib('situation_scolaire', Oev::SITUATIONS_SCOLAIRES),
            'Niveau d’étude' => $lib('niveau_etude', Oev::NIVEAUX_ETUDE),
            'Classe précédente' => $lib('classe_precedente', Oev::toutesLesClasses()),
            'Moyenne annuelle' => fn (Oev $o) => $o->moyenne_annuelle !== null ? str_replace('.', ',', (string) $o->moyenne_annuelle) . ' / ' . Oev::baremeMoyenne($o->classe_precedente) : '',
            'Appréciation' => $lib('appreciation', Oev::APPRECIATIONS),
            'Établissement actuel' => fn (Oev $o) => $o->etablissement_actuel ?? '',
            'Public / privé' => $lib('type_etablissement', Oev::TYPES_ETABLISSEMENT),
            'Classe actuelle' => $lib('classe', Oev::toutesLesClasses()),
            'Frais de scolarité (FCFA)' => fn (Oev $o) => $o->frais_scolarite ?? '',
            'Performances' => $lib('performance_scolaire', Oev::PERFORMANCES_SCOLAIRES),
            'Formation professionnelle' => fn (Oev $o) => $o->formation_professionnelle ? ($o->formation_filiere ?: 'Oui') : $ouiNon($o->formation_professionnelle),
            'Date d’identification' => fn (Oev $o) => $date($o->date_identification),
            'Identifié par' => $lib('identifie_par', Oev::IDENTIFIE_PAR),
            'Priorité' => $lib('niveau_priorite', Oev::NIVEAUX),
            'Gestionnaire du cas' => fn (Oev $o) => $o->gestionnaire_nom ?? '',
            'Tél. du gestionnaire' => fn (Oev $o) => $o->gestionnaire_contact ?? '',
            'Constitué le' => fn (Oev $o) => $date($o->created_at),
            'Soumis au DR le' => fn (Oev $o) => $date($o->soumis_at),
            'Intégré le' => fn (Oev $o) => $date($o->integre_at),
        ];
    }
}
