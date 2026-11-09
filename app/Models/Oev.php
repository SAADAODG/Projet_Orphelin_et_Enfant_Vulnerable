<?php

namespace App\Models;

use App\Models\Concerns\AppartientALocalite;
use App\Models\Concerns\Oev\AgeEtDesactivation;
use App\Models\Concerns\Oev\Circuit;
use App\Models\Concerns\Oev\Fiche;
use App\Models\Concerns\Oev\Pieces;
use App\Models\Concerns\Oev\Relations;
use App\Models\Concerns\Oev\Scolarite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'nom', 'prenom', 'sexe', 'date_naissance', 'statut', 'handicap', 'nature_handicap', 'systeme_educatif',
    'nom_tuteur', 'prenom_tuteur', 'contact_tuteur',
    'etablissement_precedent', 'ecole_precedente_id', 'classe_precedente', 'moyenne_annuelle', 'appreciation', 'performance_scolaire', 'performance_difficultes',
    'etablissement_actuel', 'ecole_id', 'centre_formation_id', 'rib_etablissement', 'type_etablissement', 'classe', 'frais_scolarite',
    'region_id', 'province_id', 'commune_id', 'village_id',
    'nom_structure_rib', 'created_by',
    'numero_dossier', 'statut_dossier', 'soumis_at', 'soumis_par', 'verifie_at', 'verifie_par', 'motif_non_conformite', 'integre_at', 'integre_par',
    'motif_complement', 'complement_at', 'complement_par',
    'motif_rejet', 'rejete_niveau', 'rejete_at', 'rejete_par',
    'desactivation_etat', 'desactivation_motif', 'desactivation_commentaire', 'desactivation_demandee_at', 'desactivation_demandee_par', 'desactive_at', 'desactive_par',
    // Fiche d'identification (champs OEV)
    'date_naissance_estimee', 'lieu_naissance', 'lieu_naissance_commune_id', 'nationalite', 'a_acte_naissance', 'numero_acte_naissance', 'groupe_population',
    'quartier', 'lieu_provenance', 'lieu_provenance_commune_id',
    'mere_nom', 'mere_prenoms', 'mere_vivante', 'mere_date_deces', 'mere_deces_confirme',
    'pere_nom', 'pere_prenoms', 'pere_vivant', 'pere_date_deces', 'pere_deces_confirme',
    'lieu_de_vie', 'lieu_de_vie_precision', 'tuteur_sexe', 'tuteur_lien', 'tuteur_lien_precision', 'tuteur_a_cnib', 'tuteur_cnib', 'tuteur_pret_continuer', 'tuteur_raison_arret',
    'vulnerabilites', 'vulnerabilite_precision',
    'situation_scolaire', 'niveau_etude', 'raison_non_scolarisation', 'raison_non_scolarisation_precision',
    'formation_professionnelle', 'formation_etat', 'formation_filiere', 'formation_type_centre', 'formation_duree_mois', 'formation_duree_recue_mois', 'frais_formation',
    'souhait_avenir', 'souhait_avenir_precision',
    'types_handicap', 'maladie_chronique', 'maladie_nom', 'suivi_clinique',
    'source_revenu', 'niveau_revenu', 'logement',
    'date_identification', 'identifie_par', 'niveau_priorite',
    'gestionnaire_nom', 'gestionnaire_fonction', 'gestionnaire_contact',
])]
class Oev extends Model
{
    use AppartientALocalite, SoftDeletes;
    // Méthodes regroupées par thème (app/Models/Concerns/Oev)
    use AgeEtDesactivation, Circuit, Fiche, Pieces, Relations, Scolarite;

    /*
     * Circuit du dossier : le DP constitue puis soumet ; le DR déclare le dossier conforme (validé)
     * ou non conforme (retour au DP) ; le niveau central intègre l'enfant, qui devient OEV et reçoit son code.
     * Le central peut aussi demander un complément : le dossier revient au DR, qui le complète
     * lui-même puis le valide à nouveau, ou le renvoie au DP (non conforme).
     * Le DR (dossier soumis ou en complément) et le central (dossier validé) peuvent rejeter définitivement le dossier.
     */
    public const ETAT_BROUILLON = 'brouillon';
    public const ETAT_SOUMIS = 'soumis';
    public const ETAT_NON_CONFORME = 'non_conforme';
    public const ETAT_VALIDE = 'valide';
    public const ETAT_COMPLEMENT = 'complement';
    public const ETAT_INTEGRE = 'integre';
    public const ETAT_REJETE = 'rejete';

    /** Niveau ayant prononcé le rejet (colonne rejete_niveau). */
    public const REJET_DR = 'DR';
    public const REJET_CENTRAL = 'Central';

    /** Désactivation d'un OEV intégré : demandée par le DP, validée par le niveau central. */
    public const DESACTIVATION_DEMANDEE = 'demandee';
    public const DESACTIVE = 'desactive';

    public const MOTIFS_DESACTIVATION = [
        'deces' => 'Décès',
        'majorite' => 'Devenu majeur',
        'fin_vulnerabilite' => 'Sortie de la situation de vulnérabilité',
        'perdu_de_vue' => 'Perdu de vue / a quitté la zone',
        'autre' => 'Autre',
    ];

    public const ETATS = [
        self::ETAT_BROUILLON => 'En constitution',
        self::ETAT_SOUMIS => 'Soumis au DR',
        self::ETAT_NON_CONFORME => 'Non conforme',
        self::ETAT_VALIDE => 'Validé par le DR',
        self::ETAT_COMPLEMENT => 'Complément demandé',
        self::ETAT_INTEGRE => 'Intégré (OEV)',
        self::ETAT_REJETE => 'Rejeté',
    ];

    /** Couleur Bootstrap associée à chaque état. */
    public const COULEURS_ETATS = [
        self::ETAT_BROUILLON => 'secondary',
        self::ETAT_SOUMIS => 'info',
        self::ETAT_NON_CONFORME => 'danger',
        self::ETAT_VALIDE => 'primary',
        self::ETAT_COMPLEMENT => 'warning',
        self::ETAT_INTEGRE => 'success',
        self::ETAT_REJETE => 'dark',
    ];

    public const SEXES = ['M' => 'Masculin', 'F' => 'Féminin'];

    public const STATUTS = [
        'orphelin_pere' => 'Orphelin de père',
        'orphelin_mere' => 'Orphelin de mère',
        'orphelin_double' => 'Orphelin de père et de mère',
        'vulnerable' => 'Enfant vulnérable',
    ];

    public const SYSTEMES_EDUCATIFS = [
        'classique' => 'Enseignement classique',
        'franco_arabe' => 'Franco-arabe',
        'technique' => 'Enseignement technique et professionnel',
        'autre' => 'Autre',
    ];

    public const APPRECIATIONS = ['admis' => 'Admis', 'redouble' => 'Redouble', 'exclu' => 'Exclu'];

    public const TYPES_ETABLISSEMENT = ['public' => 'Public', 'prive' => 'Privé'];

    /* ---------- Listes de la fiche d'identification (retenues pour les OEV) ---------- */

    public const OUI_NON = ['1' => 'Oui', '0' => 'Non'];

    /**
     * Lien du tuteur avec l'enfant. « parents » / « mere » / « pere » : seulement si le ou les parents
     * sont déclarés vivants ; leurs nom et prénoms sont alors repris de la partie « Parents ».
     */
    public const LIENS_TUTEUR = [
        'parents' => 'Les deux parents (père et mère)',
        'mere' => 'Mère',
        'pere' => 'Père',
        'grand_parent' => 'Grand-parent',
        'oncle_tante' => 'Oncle / tante',
        'frere_soeur' => 'Frère / sœur',
        'autre_parent' => 'Autre membre de la famille',
        'sans_lien' => 'Sans lien de parenté',
    ];

    /** Groupes de population pour lesquels le lieu de provenance est demandé. */
    public const GROUPES_MOBILES = ['pdi', 'migrant', 'rapatrie', 'refugie', 'demandeur_asile'];

    /** Classes par niveau d'étude (la classe doit correspondre au niveau). */
    public const CLASSES = [
        'prescolaire' => ['PS' => 'Petite section', 'MS' => 'Moyenne section', 'GS' => 'Grande section'],
        'primaire' => ['CP1' => 'CP1', 'CP2' => 'CP2', 'CE1' => 'CE1', 'CE2' => 'CE2', 'CM1' => 'CM1', 'CM2' => 'CM2'],
        'post_primaire_secondaire' => ['6e' => '6e', '5e' => '5e', '4e' => '4e', '3e' => '3e', '2nde' => '2nde', '1ère' => '1ère', 'Tle' => 'Terminale'],
        'superieur' => ['L1' => 'Licence 1', 'L2' => 'Licence 2', 'L3' => 'Licence 3', 'M1' => 'Master 1', 'M2' => 'Master 2', 'BTS' => 'BTS / DUT', 'Doctorat' => 'Doctorat'],
    ];

    public const PARENT_VIVANT = ['oui' => 'Oui', 'non' => 'Non (décédé)', 'ne_sait_pas' => 'Ne sait pas'];

    public const GROUPES_POPULATION = [
        'communaute' => 'Membre de la communauté',
        'pdi' => 'Personne déplacée interne (PDI)',
        'migrant' => 'Migrant',
        'rapatrie' => 'Rapatrié',
        'refugie' => 'Réfugié',
        'demandeur_asile' => 'Demandeur d’asile',
        'apatride' => 'Apatride',
        'autre' => 'Autre',
    ];

    public const LIEUX_DE_VIE = [
        'famille_biologique' => 'Famille biologique',
        'famille_elargie' => 'Famille élargie ou étendue',
        'famille_accueil' => 'Famille d’accueil',
        'structure_accueil' => 'Structure d’accueil / institution',
        'frere_soeur_adulte' => 'Frère ou sœur d’âge adulte',
        'vit_seul' => 'Vit seul',
        'enfant_chef_menage' => 'Sous la responsabilité d’un enfant chef de ménage',
        'site_deplaces' => 'Camp / site de réfugiés ou déplacés',
        'rue' => 'En rue',
        'autre' => 'Autre',
    ];

    public const VULNERABILITES = [
        'orphelin' => 'Orphelin',
        'separe' => 'Séparé',
        'non_accompagne' => 'Non accompagné',
        'abandon' => 'Abandon',
        'negligence' => 'Négligence',
        'sante_grave' => 'Problème de santé grave',
        'handicap' => 'Handicap',
        'sans_acte_naissance' => 'Absence d’acte de naissance',
        'precarite' => 'Précarité du ménage',
        'travail' => 'Travail des enfants',
        'enfant_marie' => 'Enfant marié',
        'grossesse' => 'Grossesse',
        'detresse' => 'Détresse psychosociale',
        'autre' => 'Autre',
    ];

    public const SITUATIONS_SCOLAIRES = [
        'scolarise' => 'Scolarisé',
        'non_scolarise' => 'Non scolarisé',
        'descolarise' => 'Déscolarisé',
        'non_formelle' => 'Éducation non formelle',
        'foyer_coranique' => 'Foyer coranique',
    ];

    public const NIVEAUX_ETUDE = [
        'prescolaire' => 'Préscolaire (maternelle)',
        'primaire' => 'Cycle primaire',
        'post_primaire_secondaire' => 'Cycle post-primaire et secondaire',
        'superieur' => 'Cycle supérieur',
    ];

    public const RAISONS_NON_SCOLARISATION = [
        'financieres' => 'Contraintes financières / matérielles',
        'infrastructures' => 'Absence d’infrastructures',
        'insecurite' => 'Insécurité / crise',
        'sante' => 'Problèmes de santé',
        'mariage' => 'Mariage',
        'grossesse' => 'Grossesse',
        'refus_famille' => 'Refus de la famille',
        'refus_enfant' => 'Refus de l’enfant',
        'autre' => 'Autre',
    ];

    /** Niveaux où les moyennes sont notées sur 10 (sur 20 ailleurs). */
    public const NIVEAUX_NOTES_SUR_10 = ['prescolaire', 'primaire'];

    public const PERFORMANCES_SCOLAIRES = [
        'bonnes' => 'Bonnes',
        'passables' => 'Passables',
        'faibles' => 'Faibles',
        'irregulieres' => 'Irrégulières',
        'difficultes' => 'Difficultés scolaires',
    ];

    public const ETATS_FORMATION = ['en_cours' => 'En cours', 'achevee' => 'Achevée'];

    /** Souhait d'un enfant ni scolarisé ni en formation professionnelle (oriente un futur parrainage). */
    public const SOUHAITS_AVENIR = [
        'ecole' => 'Aller à l’école',
        'formation' => 'Suivre une formation professionnelle',
        'aucun' => 'Ni l’un ni l’autre pour le moment',
        'ne_sait_pas' => 'Ne sait pas encore',
    ];

    public const TYPES_HANDICAP = [
        'auditif' => 'Déficience auditive',
        'visuel' => 'Déficience visuelle',
        'moteur' => 'Déficience motrice / mobilité',
        'mental' => 'Handicap mental',
    ];

    public const SOURCES_REVENU = [
        'fonds_propres' => 'Fonds propres de la famille',
        'aide_famille' => 'Aide d’autres membres de la famille',
        'ong_services' => 'Soutien d’ONG / services sociaux',
        'mendicite' => 'Pratique de la mendicité',
    ];

    /** Sources de revenu impossibles quand les deux parents sont décédés. */
    public const SOURCES_REVENU_PARENTS_VIVANTS = ['fonds_propres'];

    public const NIVEAUX = ['faible' => 'Faible', 'moyen' => 'Moyen', 'eleve' => 'Élevé'];

    public const LOGEMENTS = [
        'proprietaire' => 'Propriétaire',
        'location' => 'Maison louée',
        'zone_non_lotie' => 'Zone non lotie',
        'site_deplaces' => 'Site / camp de réfugiés ou déplacés',
        'abri_precaire' => 'Abri précaire ou inadéquat',
        'sans_abri' => 'Sans-abri / vit dans la rue',
    ];

    public const IDENTIFIE_PAR = [
        'institution' => 'Une institution / école',
        'communaute' => 'La communauté',
        'famille' => 'La famille',
        'travailleur_social' => 'Le travailleur social',
        'services_sociaux' => 'Visite aux services sociaux',
        'particulier' => 'Un particulier',
    ];

    /** Pièces constituant le dossier d'un OEV. */
    public const DOCUMENTS = [
        'acte_naissance' => 'Extrait d’acte de naissance ou jugement supplétif',
        'certificat_scolarite' => 'Certificat de scolarité',
        'bulletin' => 'Bulletin de fin d’année, diplôme ou attestation de succès',
        'photo' => 'Photo de l’enfant',
        'cnib_tuteur' => 'CNIB du parent / tuteur',
        'enquete_sociale' => 'Rapport d’enquête sociale sur la famille',
    ];

    protected $table = 'oevs';

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'handicap' => 'boolean',
            'moyenne_annuelle' => 'decimal:2',
            'frais_scolarite' => 'integer',
            'deleted_at' => 'datetime',
            'soumis_at' => 'datetime',
            'verifie_at' => 'datetime',
            'integre_at' => 'datetime',
            'complement_at' => 'datetime',
            'rejete_at' => 'datetime',
            'desactivation_demandee_at' => 'datetime',
            'desactive_at' => 'datetime',
            'date_naissance_estimee' => 'boolean',
            'a_acte_naissance' => 'boolean',
            'mere_date_deces' => 'date',
            'mere_deces_confirme' => 'boolean',
            'pere_date_deces' => 'date',
            'pere_deces_confirme' => 'boolean',
            'tuteur_pret_continuer' => 'boolean',
            'tuteur_a_cnib' => 'boolean',
            'vulnerabilites' => 'array',
            'formation_professionnelle' => 'boolean',
            'types_handicap' => 'array',
            'maladie_chronique' => 'boolean',
            'suivi_clinique' => 'boolean',
            'formation_duree_mois' => 'integer',
            'formation_duree_recue_mois' => 'integer',
            'frais_formation' => 'integer',
            'date_identification' => 'date',
        ];
    }

    /** Tailles maximales souhaitées par pièce (Ko), plafonnées par la configuration PHP du serveur. */
    public const TAILLE_MAX_PHOTO_KO = 2048;
    public const TAILLE_MAX_DOCUMENT_KO = 5120;

    public const INDICATIF = '+226';
}
