<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Oev;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jeu de démonstration : dossiers OEV remplis comme le formulaire de constitution les accepte
 * (mêmes listes, mêmes champs conditionnels et mêmes règles de cohérence que App\Services\Oev\ValidationOev).
 *
 *   php artisan db:seed --class=OevDemoSeeder
 *
 * Par défaut 120 dossiers : 100 intégrés (avec code OEV), les autres à une étape antérieure du circuit.
 * Environ 40 % sont rattachés à la province du compte DP (s'il existe) pour qu'il les voie.
 *
 * Tirages reproductibles (mt_srand) : les méthodes de la fiche sont appelées dans un ordre fixe, qui est
 * celui des tirages ; changer cet ordre change les dossiers produits.
 */
class OevDemoSeeder extends Seeder
{
    private const INTEGRES = 100;
    private const EN_CIRCUIT = [Oev::ETAT_BROUILLON => 8, Oev::ETAT_SOUMIS => 6, Oev::ETAT_VALIDE => 6];

    private const NOMS = [
        'OUEDRAOGO', 'SAWADOGO', 'KABORE', 'ZONGO', 'COMPAORE', 'TRAORE', 'SANKARA', 'ILBOUDO', 'KONATE', 'DIALLO',
        'BARRY', 'SOME', 'DABIRE', 'KAMBOU', 'PODA', 'NIKIEMA', 'TAPSOBA', 'YAMEOGO', 'BELEM', 'ZOUNGRANA',
        'KINDA', 'ROAMBA', 'BONKOUNGOU', 'GUIRO', 'SANOU', 'COULIBALY', 'DRABO', 'KIEMA', 'NANA', 'TIENDREBEOGO',
    ];
    private const PRENOMS_F = [
        'Awa', 'Aminata', 'Fatimata', 'Salimata', 'Mariam', 'Rasmata', 'Adjaratou', 'Alimata', 'Safiatou', 'Bintou',
        'Habibou', 'Rokia', 'Assita', 'Nafissatou', 'Clarisse', 'Pauline', 'Estelle', 'Larissa', 'Odile', 'Kadidiatou',
    ];
    private const PRENOMS_M = [
        'Issa', 'Moussa', 'Boukary', 'Souleymane', 'Abdoulaye', 'Ousmane', 'Adama', 'Hamidou', 'Seydou', 'Lassané',
        'Idrissa', 'Inoussa', 'Arouna', 'Salif', 'Yacouba', 'Ibrahim', 'Rodrigue', 'Wendkouni', 'Christian', 'Franck',
    ];
    private const FILIERES = ['Couture', 'Mécanique auto', 'Menuiserie', 'Coiffure', 'Électricité bâtiment', 'Maçonnerie', 'Soudure', 'Restauration'];
    private const MALADIES = ['Drépanocytose', 'Asthme', 'VIH', 'Épilepsie', 'Diabète'];

    /** Champs de scolarité et de formation, vides par défaut. */
    private const SCOLARITE_VIDE = [
        'niveau_etude' => null, 'raison_non_scolarisation' => null, 'raison_non_scolarisation_precision' => null,
        'etablissement_precedent' => null, 'classe_precedente' => null, 'moyenne_annuelle' => null, 'appreciation' => null,
        'performance_scolaire' => null, 'performance_difficultes' => null, 'systeme_educatif' => null, 'etablissement_actuel' => null,
        'type_etablissement' => null, 'classe' => null, 'frais_scolarite' => null, 'formation_etat' => null, 'formation_filiere' => null,
        'formation_type_centre' => null, 'formation_duree_mois' => null, 'formation_duree_recue_mois' => null,
        'formation_professionnelle' => false,
    ];

    /** @var Collection<int, Commune> */
    private Collection $communes;

    /** @var Collection<int, Commune> */
    private Collection $communesDp;

    public function run(): void
    {
        mt_srand(2026);

        $this->communes = Commune::with('province:id,region_id', 'villages:id,commune_id')->get();
        if ($this->communes->isEmpty()) {
            $this->command?->error('Référentiel des localités vide : lancez d’abord LocaliteSeeder, CommuneSeeder et VillageSeeder.');

            return;
        }

        $dp = User::role('DP')->whereNotNull('province_id')->first();
        $dr = User::role('DR')->first();
        $central = User::role(['agent DGFE', 'responsable DGFE', 'superAdmin'])->first();
        $this->communesDp = $dp ? $this->communes->where('province_id', $dp->province_id)->values() : collect();

        $etats = array_merge(array_fill(0, self::INTEGRES, Oev::ETAT_INTEGRE), ...array_map(
            fn ($etat, $nombre) => array_fill(0, $nombre, $etat), array_keys(self::EN_CIRCUIT), self::EN_CIRCUIT));

        foreach ($etats as $etat) {
            $donnees = $this->fiche();
            $identification = Carbon::parse($donnees['date_identification']);

            Oev::create($donnees + array_filter([
                'numero_dossier' => Oev::genererNumeroDossier(),
                'statut_dossier' => $etat,
                'created_by' => $dp?->id,
                'soumis_at' => $etat !== Oev::ETAT_BROUILLON ? $identification->copy()->addDays(3) : null,
                'soumis_par' => $etat !== Oev::ETAT_BROUILLON ? $dp?->id : null,
                'verifie_at' => in_array($etat, [Oev::ETAT_VALIDE, Oev::ETAT_INTEGRE], true) ? $identification->copy()->addDays(8) : null,
                'verifie_par' => in_array($etat, [Oev::ETAT_VALIDE, Oev::ETAT_INTEGRE], true) ? $dr?->id : null,
            ]) + ($etat === Oev::ETAT_INTEGRE ? [
                'code' => Oev::genererCode(),
                'integre_at' => $identification->copy()->addDays(15),
                'integre_par' => $central?->id,
            ] : []));
        }

        $this->command?->info(count($etats) . ' dossiers OEV créés (' . self::INTEGRES . ' intégrés).');
    }

    /** Une fiche complète et cohérente, telle que le formulaire l'enregistrerait (sections dans l'ordre des tirages). */
    private function fiche(): array
    {
        $profil = $this->profil();
        $donnees = $this->identite($profil)
            + $this->parents($profil)
            + $this->tuteurEtFoyer($profil)
            + $this->santeEtRessources($profil)
            + $this->suiviDuCas()
            + $this->scolarite($profil['age'], $profil['sexe']);

        $donnees['vulnerabilites'] = $this->vulnerabilites($donnees, $profil);
        $donnees['date_identification'] = max($donnees['date_identification'], $profil['naissance']->copy()->addDay()->toDateString());

        return $donnees;
    }

    /** Tirages qui commandent le reste de la fiche : sexe, âge, localité, parents, tuteur, situations particulières. */
    private function profil(): array
    {
        $sexe = $this->hasard(['F', 'M']);
        $age = $this->pondere([3 => 4, 4 => 4, 5 => 5, 6 => 8, 7 => 8, 8 => 8, 9 => 8, 10 => 8, 11 => 8, 12 => 7, 13 => 7, 14 => 6, 15 => 6, 16 => 5, 17 => 4, 18 => 4, 19 => 3]);
        $naissance = now()->subYears($age)->subDays(mt_rand(15, 350))->startOfDay();
        $nom = $this->hasard(self::NOMS);
        $commune = $this->communesDp->isNotEmpty() && mt_rand(1, 100) <= 40 ? $this->communesDp->random() : $this->communes->random();

        // Parents : le statut OEV (orphelin de père, de mère, double, vulnérable) en est déduit
        [$mereVivante, $pereVivant] = $this->hasard([['non', 'non'], ['non', 'oui'], ['oui', 'non'], ['non', 'ne_sait_pas'], ['oui', 'oui'], ['ne_sait_pas', 'non'], ['oui', 'non']]);
        $mere = ['mere_nom' => $this->hasard(self::NOMS), 'mere_prenoms' => $this->hasard(self::PRENOMS_F)];
        $pere = ['pere_nom' => $nom, 'pere_prenoms' => $this->hasard(self::PRENOMS_M)];

        $lien = $this->hasard($this->liensTuteur($mereVivante, $pereVivant));
        $tuteur = $this->tuteur($lien, $mere, $pere, $nom);
        $pretContinuer = mt_rand(1, 100) <= 85;
        $aCnib = mt_rand(1, 100) <= 75;
        $aActe = mt_rand(1, 100) <= 80;
        $handicap = mt_rand(1, 100) <= 10;
        $maladie = mt_rand(1, 100) <= 8;
        $groupe = $this->pondere(['communaute' => 70, 'pdi' => 20, 'migrant' => 3, 'rapatrie' => 3, 'refugie' => 4]);

        return [
            'sexe' => $sexe, 'age' => $age, 'naissance' => $naissance, 'nom' => $nom, 'commune' => $commune,
            'mere_vivante' => $mereVivante, 'pere_vivant' => $pereVivant, 'mere' => $mere, 'pere' => $pere,
            'lien' => $lien, 'parents_tuteurs' => Oev::parentsTuteurs($lien), 'tuteur' => $tuteur,
            'pret_continuer' => $pretContinuer, 'a_cnib' => $aCnib, 'a_acte' => $aActe,
            'deux_decedes' => $mereVivante === 'non' && $pereVivant === 'non',
            'handicap' => $handicap, 'maladie' => $maladie,
            'groupe' => $groupe, 'mobile' => in_array($groupe, Oev::GROUPES_MOBILES, true),
        ];
    }

    /** Liens possibles avec le tuteur : un parent seulement s'il est déclaré vivant. */
    private function liensTuteur(string $mereVivante, string $pereVivant): array
    {
        return array_values(array_filter([
            $mereVivante === 'oui' && $pereVivant === 'oui' ? 'parents' : null,
            $mereVivante === 'oui' ? 'mere' : null,
            $pereVivant === 'oui' ? 'pere' : null,
            'grand_parent', 'oncle_tante', 'frere_soeur', 'autre_parent',
        ]));
    }

    private function tuteur(string $lien, array $mere, array $pere, string $nom): array
    {
        return match ($lien) {
            'mere' => ['nom_tuteur' => $mere['mere_nom'], 'prenom_tuteur' => $mere['mere_prenoms'], 'tuteur_sexe' => 'F'],
            'pere' => ['nom_tuteur' => $pere['pere_nom'], 'prenom_tuteur' => $pere['pere_prenoms'], 'tuteur_sexe' => 'M'],
            'parents' => ['nom_tuteur' => collect([$pere['pere_nom'], $mere['mere_nom']])->unique()->implode(' / '), 'prenom_tuteur' => $pere['pere_prenoms'] . ' et ' . $mere['mere_prenoms'], 'tuteur_sexe' => null],
            default => $this->tuteurDeLaFamille($lien, $nom),
        };
    }

    /** Tuteur de la famille élargie : même nom que l'enfant pour un frère, une sœur ou un grand-parent. */
    private function tuteurDeLaFamille(string $lien, string $nom): array
    {
        $tuteurSexe = $this->hasard(['F', 'M']);

        return [
            'nom_tuteur' => in_array($lien, ['frere_soeur', 'grand_parent'], true) ? $nom : $this->hasard(self::NOMS),
            'prenom_tuteur' => $this->hasard($tuteurSexe === 'F' ? self::PRENOMS_F : self::PRENOMS_M),
            'tuteur_sexe' => $tuteurSexe,
        ];
    }

    private function identite(array $p): array
    {
        $commune = $p['commune'];
        $groupe = $p['groupe'];

        return [
            'nom' => $p['nom'],
            'prenom' => $this->hasard($p['sexe'] === 'F' ? self::PRENOMS_F : self::PRENOMS_M),
            'sexe' => $p['sexe'],
            'date_naissance' => $p['naissance']->toDateString(),
            'date_naissance_estimee' => mt_rand(1, 100) <= 10,
            'lieu_naissance' => $commune->nom,
            'lieu_naissance_commune_id' => null,
            'nationalite' => $groupe === 'refugie' ? $this->hasard(['Malienne', 'Nigérienne']) : 'Burkinabè',
            'a_acte_naissance' => $p['a_acte'],
            'numero_acte_naissance' => $p['a_acte'] ? 'N° ' . $p['naissance']->year . '/' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) : null,
            'groupe_population' => $groupe,
            'region_id' => $commune->province->region_id,
            'province_id' => $commune->province_id,
            'commune_id' => $commune->id,
            'village_id' => $commune->villages->isNotEmpty() && mt_rand(1, 100) <= 60 ? $commune->villages->random()->id : null,
            'quartier' => mt_rand(1, 100) <= 50 ? 'Secteur ' . mt_rand(1, 30) : null,
            'lieu_provenance_commune_id' => $p['mobile'] && $groupe !== 'refugie' ? $this->communes->random()->id : null,
            'lieu_provenance' => $groupe === 'refugie' ? $this->hasard(['Mopti (Mali)', 'Tillabéri (Niger)', 'Gao (Mali)']) : null,
        ];
    }

    private function parents(array $p): array
    {
        $mereDecedee = $p['mere_vivante'] === 'non';
        $pereDecede = $p['pere_vivant'] === 'non';

        return [
            'mere_vivante' => $p['mere_vivante'],
            'mere_date_deces' => $mereDecedee ? $this->dateDeces($p['naissance']) : null,
            'mere_deces_confirme' => $mereDecedee ? mt_rand(0, 1) === 1 : null,
            'pere_vivant' => $p['pere_vivant'],
            'pere_date_deces' => $pereDecede ? $this->dateDeces($p['naissance']) : null,
            'pere_deces_confirme' => $pereDecede ? mt_rand(0, 1) === 1 : null,
            ...(in_array('mere', $p['parents_tuteurs'], true) || mt_rand(0, 1) ? $p['mere'] : ['mere_nom' => null, 'mere_prenoms' => null]),
            ...(in_array('pere', $p['parents_tuteurs'], true) || mt_rand(0, 1) ? $p['pere'] : ['pere_nom' => null, 'pere_prenoms' => null]),
            'statut' => Oev::calculerStatut($p['mere_vivante'], $p['pere_vivant']),
        ];
    }

    /** Date de décès d'un parent : après la naissance de l'enfant, au moins un mois avant aujourd'hui. */
    private function dateDeces(Carbon $naissance): string
    {
        return $naissance->copy()->addDays(mt_rand(30, max(31, (int) $naissance->diffInDays(now()) - 30)))->toDateString();
    }

    private function tuteurEtFoyer(array $p): array
    {
        return [
            'tuteur_lien' => $p['lien'],
            'tuteur_lien_precision' => $p['lien'] === 'autre_parent' ? $this->hasard(['Cousin', 'Cousine', 'Belle-sœur']) : null,
            ...$p['tuteur'],
            'contact_tuteur' => Oev::formaterTelephone($this->hasard(['70', '71', '72', '76', '78', '65', '66', '56']) . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)),
            'tuteur_a_cnib' => $p['a_cnib'],
            'tuteur_cnib' => $p['a_cnib'] ? 'B' . mt_rand(10000000, 99999999) : null,
            'tuteur_pret_continuer' => $p['pret_continuer'],
            'tuteur_raison_arret' => $p['pret_continuer'] ? null : $this->hasard(['Manque de moyens financiers', 'Âge avancé et maladie', 'Déménagement prévu']),
        ];
    }

    private function santeEtRessources(array $p): array
    {
        $mobile = $p['mobile'];

        return [
            'lieu_de_vie' => $this->pondere(['famille_biologique' => 25, 'famille_elargie' => 40, 'famille_accueil' => 10, 'site_deplaces' => $mobile ? 20 : 2, 'structure_accueil' => 5, 'frere_soeur_adulte' => 5]),
            'handicap' => $p['handicap'],
            'types_handicap' => $p['handicap'] ? [$this->hasard(array_keys(Oev::TYPES_HANDICAP))] : null,
            'nature_handicap' => $p['handicap'] ? $this->hasard(['Malvoyance', 'Paralysie d’un membre', 'Surdité partielle', 'Retard mental léger']) : null,
            'maladie_chronique' => $p['maladie'],
            'maladie_nom' => $p['maladie'] ? $this->hasard(self::MALADIES) : null,
            'suivi_clinique' => $p['maladie'] ? mt_rand(0, 1) === 1 : null,

            'source_revenu' => $this->hasard($p['deux_decedes'] ? ['aide_famille', 'ong_services', 'mendicite'] : ['fonds_propres', 'aide_famille', 'ong_services', 'mendicite', 'aide_famille']),
            'niveau_revenu' => $this->pondere(['faible' => 65, 'moyen' => 30, 'eleve' => 5]),
            'logement' => $this->pondere(['proprietaire' => 20, 'location' => 25, 'zone_non_lotie' => 25, 'site_deplaces' => $mobile ? 25 : 3, 'abri_precaire' => 15, 'sans_abri' => 2]),
        ];
    }

    private function suiviDuCas(): array
    {
        return [
            'date_identification' => now()->subDays(mt_rand(30, 400))->toDateString(),
            'identifie_par' => $this->hasard(array_keys(Oev::IDENTIFIE_PAR)),
            'niveau_priorite' => $this->pondere(['faible' => 25, 'moyen' => 45, 'eleve' => 30]),
            'gestionnaire_nom' => $this->hasard(self::NOMS) . ' ' . $this->hasard(array_merge(self::PRENOMS_F, self::PRENOMS_M)),
            'gestionnaire_fonction' => $this->hasard(['Travailleur social', 'Éducateur spécialisé', 'Agent de l’action sociale']),
            'gestionnaire_contact' => Oev::formaterTelephone('7' . mt_rand(0, 9) . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)),
        ];
    }

    /** Vulnérabilités : celles déduites des réponses, plus quelques-unes cochées par l'agent. */
    private function vulnerabilites(array $donnees, array $p): array
    {
        $possibles = ['precarite', 'negligence', 'separe', 'abandon', 'detresse', 'travail'];
        if ($p['sexe'] === 'F' && $p['age'] >= 13) {
            $possibles[] = 'grossesse';
        }
        if ($p['maladie']) {
            $possibles[] = 'sante_grave';
        }
        $choisies = (array) array_rand(array_flip($possibles), mt_rand(1, 3));

        return array_values(array_unique(array_merge(Oev::vulnerabilitesAutomatiques($donnees), $choisies)));
    }

    /** Scolarité selon l'âge : la classe correspond au niveau, les frais au type d'établissement. */
    private function scolarite(int $age, string $sexe): array
    {
        $situation = match (true) {
            $age <= 5 => $this->pondere(['scolarise' => 45, 'non_scolarise' => 55]),
            $age <= 16 => $this->pondere(['scolarise' => 68, 'non_scolarise' => 12, 'descolarise' => 12, 'foyer_coranique' => 4, 'non_formelle' => 4]),
            default => $this->pondere(['scolarise' => 40, 'descolarise' => 40, 'non_scolarise' => 20]),
        };
        $donnees = ['situation_scolaire' => $situation] + self::SCOLARITE_VIDE;

        if ($situation === 'scolarise') {
            return $this->scolarise($donnees, $age);
        }

        $donnees = $this->horsEcole($donnees, $situation, $age, $sexe);
        $donnees = $this->formation($donnees, $age);

        return $this->souhait($donnees, $situation, $age);
    }

    private function scolarise(array $donnees, int $age): array
    {
        [$niveau, $classe] = match (true) {
            $age <= 5 => ['prescolaire', ['PS', 'MS', 'GS'][min(2, max(0, $age - 3))]],
            $age <= 11 => ['primaire', ['CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2'][min(5, max(0, $age - 6 - mt_rand(0, 1)))]],
            $age <= 18 => ['post_primaire_secondaire', ['6e', '5e', '4e', '3e', '2nde', '1ère', 'Tle'][min(6, max(0, $age - 12 - mt_rand(0, 1)))]],
            default => ['superieur', 'L1'],
        };
        $prive = mt_rand(1, 100) <= 30;
        $frais = $this->fraisAnnuels($niveau, $prive);
        $donnees = array_merge($donnees, [
            'niveau_etude' => $niveau,
            'classe' => $classe,
            'systeme_educatif' => $this->pondere(['classique' => 80, 'franco_arabe' => 15, 'technique' => $niveau === 'post_primaire_secondaire' ? 5 : 0]),
            'type_etablissement' => $prive ? 'prive' : 'public',
            'etablissement_actuel' => $this->etablissement($niveau, $prive),
            // 15 % de frais non renseignés : le plafond sera retenu
            'frais_scolarite' => mt_rand(1, 100) <= 85 ? $frais : null,
            'performance_scolaire' => $perf = $this->pondere(['bonnes' => 30, 'passables' => 40, 'faibles' => 15, 'irregulieres' => 10, 'difficultes' => 5]),
            'performance_difficultes' => $perf === 'difficultes' ? 'Difficultés en lecture et en calcul' : null,
        ]);

        return $this->anneePrecedente($donnees, $classe, $niveau);
    }

    private function fraisAnnuels(string $niveau, bool $prive): int
    {
        return match ($niveau) {
            'prescolaire' => $prive ? mt_rand(30, 60) * 1000 : mt_rand(5, 15) * 1000,
            'primaire' => $prive ? mt_rand(35, 90) * 1000 : mt_rand(2, 10) * 1000,
            'post_primaire_secondaire' => $prive ? mt_rand(60, 150) * 1000 : mt_rand(15, 50) * 1000,
            default => mt_rand(15, 25) * 10000,
        };
    }

    private function etablissement(string $niveau, bool $prive): string
    {
        return match ($niveau) {
            'prescolaire' => 'Centre d’éveil et d’éducation préscolaire ' . $this->hasard(['Les Poussins', 'Wend-Panga', 'La Colombe']),
            'primaire' => ($prive ? 'École privée ' : 'École primaire publique ') . $this->hasard(['A', 'B', 'Centre', 'Secteur ' . mt_rand(1, 12)]),
            'post_primaire_secondaire' => ($prive ? 'Collège privé ' : 'Lycée départemental ') . $this->hasard(['Wend-Kuni', 'Saint-Joseph', 'Nelson Mandela', 'de la Paix']),
            default => 'Université Joseph Ki-Zerbo',
        };
    }

    /** Année précédente (hors première année) : redoublant dans la même classe, sinon admis de la classe d'avant. */
    private function anneePrecedente(array $donnees, string $classe, string $niveau): array
    {
        $rang = Oev::rangClasse($classe);
        if ($rang === null || $rang <= 0 || $niveau === 'superieur') {
            return $donnees;
        }

        $redouble = mt_rand(1, 100) <= 15;
        $precedente = $redouble ? $classe : array_keys(Oev::toutesLesClasses())[$rang - 1];
        $bareme = Oev::baremeMoyenne($precedente);

        return array_merge($donnees, [
            'etablissement_precedent' => $donnees['etablissement_actuel'],
            'classe_precedente' => $precedente,
            'appreciation' => $redouble ? 'redouble' : 'admis',
            'moyenne_annuelle' => round(($redouble ? mt_rand(30, 48) : mt_rand(50, 85)) / 100 * $bareme, 2),
        ]);
    }

    /** Non scolarisé ou déscolarisé : raison, et pour un déscolarisé, sa dernière classe. */
    private function horsEcole(array $donnees, string $situation, int $age, string $sexe): array
    {
        $raison = $this->hasard(array_values(array_diff(array_keys(Oev::RAISONS_NON_SCOLARISATION), $sexe === 'M' ? ['grossesse', 'autre'] : ['autre'])));
        if ($situation === 'non_scolarise') {
            $donnees['raison_non_scolarisation'] = $raison;
        }
        if ($situation !== 'descolarise') {
            return $donnees;
        }

        $niveau = $age >= 12 ? $this->hasard(['primaire', 'post_primaire_secondaire']) : 'primaire';

        return array_merge($donnees, [
            'niveau_etude' => $niveau,
            'raison_non_scolarisation' => $raison,
            'etablissement_precedent' => 'École primaire publique ' . $this->hasard(['A', 'B', 'Centre']),
            'classe_precedente' => $niveau === 'primaire' ? $this->hasard(['CE1', 'CE2', 'CM1', 'CM2']) : $this->hasard(['6e', '5e', '4e']),
            'appreciation' => $this->hasard(['admis', 'exclu']),
        ]);
    }

    /** Formation professionnelle : surtout les adolescents qui ont quitté l'école. */
    private function formation(array $donnees, int $age): array
    {
        if ($age < 14 || mt_rand(1, 100) > ($age >= 17 ? 65 : 30)) {
            return $donnees;
        }

        $duree = $this->hasard([6, 12, 18, 24, 36]);
        $enCours = mt_rand(1, 100) <= 80;

        return array_merge($donnees, [
            'formation_professionnelle' => true,
            'formation_etat' => $enCours ? 'en_cours' : 'achevee',
            'formation_filiere' => $this->hasard(self::FILIERES),
            'formation_type_centre' => $this->hasard(['public', 'prive']),
            'formation_duree_mois' => $duree,
            'formation_duree_recue_mois' => $enCours ? mt_rand(0, $duree - 1) : $duree,
        ]);
    }

    /** Ni à l'école ni en formation : son souhait (école surtout pour les plus jeunes, formation pour les plus grands). */
    private function souhait(array $donnees, string $situation, int $age): array
    {
        if (! Oev::sansScolariteNiFormation($situation, $donnees['formation_professionnelle'], $donnees['formation_etat'])) {
            return $donnees;
        }

        $donnees['souhait_avenir'] = $age <= 13
            ? $this->pondere(['ecole' => 70, 'formation' => 5, 'ne_sait_pas' => 20, 'aucun' => 5])
            : $this->pondere(['ecole' => 25, 'formation' => 55, 'ne_sait_pas' => 15, 'aucun' => 5]);
        $donnees['souhait_avenir_precision'] = $donnees['souhait_avenir'] === 'formation' && mt_rand(0, 1) ? $this->hasard(self::FILIERES) : null;

        return $donnees;
    }

    private function hasard(array $liste): mixed
    {
        return $liste[array_rand($liste)];
    }

    /** Valeur tirée selon des poids, ex. ['faible' => 25, 'moyen' => 45, 'eleve' => 30]. */
    private function pondere(array $poids): mixed
    {
        $poids = array_filter($poids);
        $tirage = mt_rand(1, array_sum($poids));
        foreach ($poids as $valeur => $poid) {
            if (($tirage -= $poid) <= 0) {
                return $valeur;
            }
        }

        return array_key_first($poids);
    }
}
