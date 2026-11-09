<?php

namespace App\Services\Oev;

use App\Models\Ecole;
use App\Models\Oev;
use App\Models\ParametreOev;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire OEV (création et modification) :
 *  - saisie nettoyée (téléphones, école précédente choisie dans la liste) ;
 *  - règles par partie du formulaire, avec les mêmes conditions que son affichage, et cohérence entre les réponses
 *    (ReglesCoherenceOev) ;
 *  - données mises en forme pour l'enregistrement : champs masqués effacés, écoles, tuteur, vulnérabilités (MiseEnFormeOev).
 */
class ValidationOev
{
    /** Messages de validation en français, propres au formulaire OEV (la langue de l'application n'est pas modifiée). */
    private const MESSAGES = [
        'required' => 'Le champ « :attribute » est obligatoire.',
        'string' => 'Le champ « :attribute » doit être un texte.',
        'max.string' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
        'max.numeric' => 'Le champ « :attribute » ne doit pas être supérieur à :max.',
        'max.file' => 'Le fichier « :attribute » ne doit pas dépasser :max Ko.',
        'min.numeric' => 'Le champ « :attribute » doit être supérieur ou égal à :min.',
        'integer' => 'Le champ « :attribute » doit être un nombre entier.',
        'numeric' => 'Le champ « :attribute » doit être un nombre.',
        'boolean' => 'Le champ « :attribute » est invalide.',
        'in' => 'La valeur choisie pour « :attribute » est invalide.',
        'exists' => 'La valeur choisie pour « :attribute » est invalide.',
        'date' => 'Le champ « :attribute » n’est pas une date valide.',
        'regex' => 'Le format du champ « :attribute » est invalide.',
        'file' => '« :attribute » doit être un fichier.',
        'image' => '« :attribute » doit être une image.',
        'mimes' => '« :attribute » doit être au format : :values.',
        'uploaded' => '« :attribute » n’a pas pu être téléversé.',
    ];

    private const ATTRIBUTS = [
        'nom' => 'nom de l’enfant',
        'prenom' => 'prénom(s) de l’enfant',
        'sexe' => 'sexe',
        'date_naissance' => 'date de naissance',
        'statut' => 'statut',
        'handicap' => 'situation de handicap',
        'nature_handicap' => 'nature du handicap',
        'systeme_educatif' => 'système éducatif',
        'nom_tuteur' => 'nom du parent ou tuteur',
        'prenom_tuteur' => 'prénom(s) du parent ou tuteur',
        'contact_tuteur' => 'téléphone du parent ou tuteur',
        'etablissement_precedent' => 'établissement de l’année précédente',
        'moyenne_annuelle' => 'moyenne annuelle',
        'appreciation' => 'appréciation',
        'etablissement_actuel' => 'établissement de l’année en cours',
        'ecole_id' => 'établissement de l’année en cours',
        'ecole_precedente_id' => 'établissement de l’année précédente',
        'type_etablissement' => 'public ou privé',
        'classe' => 'classe',
        'frais_scolarite' => 'frais de scolarité',
        'region_id' => 'région',
        'province_id' => 'province',
        'commune_id' => 'commune',
        'village_id' => 'village / secteur',
        'rib_etablissement' => 'RIB de l’établissement',
        'date_naissance_estimee' => 'date estimée',
        'lieu_naissance' => 'lieu de naissance',
        'nationalite' => 'nationalité',
        'a_acte_naissance' => 'acte de naissance',
        'groupe_population' => 'groupe de population',
        'quartier' => 'quartier / précision de l’adresse',
        'lieu_provenance' => 'lieu de provenance',
        'lieu_provenance_commune_id' => 'lieu de provenance',
        'gestionnaire_nom' => 'nom du gestionnaire du cas',
        'gestionnaire_fonction' => 'fonction / structure du gestionnaire du cas',
        'gestionnaire_contact' => 'téléphone du gestionnaire du cas',
        'tuteur_sexe' => 'sexe du tuteur',
        'tuteur_lien' => 'lien de parenté du tuteur',
        'tuteur_cnib' => 'numéro CNIB du tuteur',
        'tuteur_pret_continuer' => 'disponibilité du tuteur',
        'tuteur_raison_arret' => 'raison pour laquelle le tuteur ne peut pas continuer',
        'numero_acte_naissance' => 'numéro de l’acte de naissance',
        'maladie_nom' => 'nom de la maladie',
        'suivi_clinique' => 'suivi clinique',
        'classe_precedente' => 'classe de l’année précédente',
        'performance_scolaire' => 'performances scolaires',
        'performance_difficultes' => 'explication des difficultés scolaires',
        'formation_duree_mois' => 'durée de la formation',
        'formation_duree_recue_mois' => 'durée de formation déjà reçue',
        'mere_nom' => 'nom de la mère',
        'mere_prenoms' => 'prénoms de la mère',
        'mere_vivante' => 'mère vivante',
        'mere_date_deces' => 'date du décès de la mère',
        'mere_deces_confirme' => 'décès de la mère confirmé',
        'pere_nom' => 'nom du père',
        'pere_prenoms' => 'prénoms du père',
        'pere_vivant' => 'père vivant',
        'pere_date_deces' => 'date du décès du père',
        'pere_deces_confirme' => 'décès du père confirmé',
        'lieu_de_vie' => 'lieu de vie de l’enfant',
        'lieu_de_vie_precision' => 'précision sur le lieu de vie',
        'vulnerabilites' => 'situations de vulnérabilité',
        'vulnerabilite_precision' => 'autre situation de vulnérabilité',
        'types_handicap' => 'type de handicap',
        'maladie_chronique' => 'maladie chronique',
        'source_revenu' => 'source de revenu',
        'niveau_revenu' => 'niveau de revenu',
        'logement' => 'logement',
        'date_identification' => 'date d’identification',
        'identifie_par' => 'personne ou institution ayant identifié l’enfant',
        'niveau_priorite' => 'niveau de priorité',
        'situation_scolaire' => 'situation scolaire',
        'niveau_etude' => 'niveau d’étude',
        'raison_non_scolarisation' => 'raison de la non-scolarisation',
        'raison_non_scolarisation_precision' => 'précision sur la raison',
        'formation_professionnelle' => 'formation professionnelle',
        'formation_etat' => 'état de la formation',
        'formation_filiere' => 'filière de formation',
        'formation_type_centre' => 'type de centre de formation',
        'centre_formation_id' => 'centre de formation',
    ];

    private const OBLIGATOIRE_SCOLARISE = 'Le champ « :attribute » est obligatoire pour un enfant scolarisé.';

    /** Données validées et mises en forme, prêtes à enregistrer (sans les pièces jointes). */
    public function valider(Request $request): array
    {
        $this->normaliserSaisie($request);
        $c = $this->conditions($request);

        $regles = [
            ...$this->reglesIdentite($c),
            ...$this->reglesAdresse($request),
            ...$this->reglesParents($request, $c),
            ...$this->reglesTuteur($request, $c),
            ...$this->reglesVieEtSante($request),
            ...$this->reglesMenageEtSuivi($c),
            ...$this->reglesScolarite($request, $c),
            ...$this->reglesFormation($request, $c),
        ];
        $messagesZone = $this->limiterALaZone($request, $regles);
        [$reglesFichiers, $messagesFichiers] = $this->reglesFichiers();

        $validated = $request->validate(
            array_merge($regles, $reglesFichiers),
            $messagesZone + $messagesFichiers + $this->messages($request, $c['ageLimite']) + self::MESSAGES,
            Oev::DOCUMENTS + self::ATTRIBUTS,
        );

        $validated = MiseEnFormeOev::appliquer($validated, $c['sansActivite']);

        return collect($validated)->except(array_keys(Oev::DOCUMENTS))->all();
    }

    private function normaliserSaisie(Request $request): void
    {
        // Le numéro est saisi sans indicatif (8 chiffres) ; on retire espaces et +226 éventuels avant validation.
        foreach (['contact_tuteur', 'gestionnaire_contact'] as $champTelephone) {
            if ($request->filled($champTelephone)) {
                $request->merge([$champTelephone => Oev::numeroLocal($request->input($champTelephone))]);
            }
        }

        // École de l'année précédente choisie dans la liste : son nom devient l'établissement précédent
        // (les règles de l'année précédente — classe, moyenne, appréciation — s'appuient sur lui)
        $idEcolePrecedente = $request->input('ecole_precedente_id');
        if (filled($idEcolePrecedente) && $idEcolePrecedente !== 'autre'
            && ($ecolePrecedente = Ecole::whereKey($idEcolePrecedente)->where('commune_id', $request->input('commune_id'))->first())) {
            $request->merge(['etablissement_precedent' => $ecolePrecedente->nom]);
        }
    }

    /** Conditions entre champs (les mêmes que l'affichage conditionnel du formulaire). */
    private function conditions(Request $request): array
    {
        $situation = $request->input('situation_scolaire');
        $parentsTuteurs = Oev::parentsTuteurs($request->input('tuteur_lien'));

        return [
            'situation' => $situation,
            'estScolarise' => $situation === 'scolarise',
            'aEteScolarise' => in_array($situation, ['scolarise', 'descolarise'], true),
            'nonScolarise' => in_array($situation, ['non_scolarise', 'descolarise'], true),
            // Année précédente : école de la liste, « autre » (alors saisie) ou aucune
            'ecolePrecedenteDemandee' => filled($situation) && $situation !== 'non_scolarise',
            'ageLimite' => ParametreOev::ageLimite($request->input('handicap') === '1'),
            'sansActivite' => Oev::sansScolariteNiFormation($situation, $request->input('formation_professionnelle'), $request->input('formation_etat')),
            // Exclu l'année précédente : il ne peut plus prétendre retourner à l'école (formation, ou rien pour le moment)
            'exclu' => $situation !== 'non_scolarise'
                && (filled($request->input('ecole_precedente_id')) || filled($request->input('etablissement_precedent')))
                && $request->input('appreciation') === 'exclu',
            // Parent(s) qui s'occupent de l'enfant : ils doivent être déclarés vivants, et leurs nom et prénoms
            // (saisis dans la partie « Parents ») sont repris comme ceux du tuteur.
            'parentsTuteurs' => $parentsTuteurs,
            'tuteurEstParent' => $parentsTuteurs !== [],
            'deuxParentsDecedes' => $request->input('mere_vivante') === 'non' && $request->input('pere_vivant') === 'non',
        ];
    }

    private static function choix(array $liste): array
    {
        return ['nullable', Rule::in(array_keys($liste))];
    }

    private function reglesIdentite(array $c): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::in(array_keys(Oev::SEXES))],
            // Âge de sortie (Paramètres > Âge des OEV) : plus élevé pour un enfant en situation de handicap
            'date_naissance' => ['required', 'date', 'before_or_equal:today', 'after:' . now()->subYears($c['ageLimite'])->toDateString()],
            'date_naissance_estimee' => ['nullable', 'boolean'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'a_acte_naissance' => ['required', 'boolean'],
            'numero_acte_naissance' => ['nullable', 'required_if:a_acte_naissance,1', 'string', 'max:50'],
            'groupe_population' => self::choix(Oev::groupesPopulation(true)),
        ];
    }

    private function reglesAdresse(Request $request): array
    {
        // Lieu de provenance (enfant déplacé, réfugié…) : même principe que le lieu de naissance
        $groupeMobile = in_array($request->input('groupe_population'), Oev::GROUPES_MOBILES, true);

        return [
            // Adresse : région, province et commune choisies dans le référentiel des localités
            ...Oev::reglesLocalite($request),
            // Village facultatif, mais forcément dans la commune choisie
            'village_id' => ['nullable', 'integer', Rule::exists('villages', 'id')->where('commune_id', $request->input('commune_id'))],
            'quartier' => ['nullable', 'string', 'max:150'],
            'lieu_provenance_commune_id' => ['nullable', ReglesCoherenceOev::communeDeProvenance()],
            'lieu_provenance' => ['nullable', Rule::requiredIf($groupeMobile && $request->input('lieu_provenance_commune_id') === 'autre'), 'string', 'max:150'],
        ];
    }

    private function reglesParents(Request $request, array $c): array
    {
        // Nom et prénoms obligatoires pour le parent qui s'occupe de l'enfant (repris comme tuteur)
        $mereTutrice = in_array('mere', $c['parentsTuteurs'], true);
        $pereTuteur = in_array('pere', $c['parentsTuteurs'], true);

        // Parents (situation d'orphelin) : « vivant ? » obligatoire, le statut OEV en est déduit
        return [
            'mere_nom' => ['nullable', Rule::requiredIf($mereTutrice), 'string', 'max:100'],
            'mere_prenoms' => ['nullable', Rule::requiredIf($mereTutrice), 'string', 'max:150'],
            'mere_vivante' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'mere_date_deces' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'mere_deces_confirme' => ['nullable', 'boolean'],
            'pere_nom' => ['nullable', Rule::requiredIf($pereTuteur), 'string', 'max:100'],
            'pere_prenoms' => ['nullable', Rule::requiredIf($pereTuteur), 'string', 'max:150'],
            'pere_vivant' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'pere_date_deces' => ['nullable', 'date', 'before_or_equal:today', ReglesCoherenceOev::decesPereCoherent($request)],
            'pere_deces_confirme' => ['nullable', 'boolean'],
        ];
    }

    private function reglesTuteur(Request $request, array $c): array
    {
        // Nom, prénoms et sexe demandés seulement si le tuteur n'est pas un parent (déjà saisi plus haut)
        $saisi = Rule::requiredIf(! $c['tuteurEstParent']);

        return [
            'tuteur_lien' => ['required', Rule::in(array_keys(Oev::liensTuteur(true))), ReglesCoherenceOev::tuteurVivant($request)],
            'tuteur_lien_precision' => ['nullable', 'required_if:tuteur_lien,autre_parent', 'string', 'max:100'],
            'nom_tuteur' => ['nullable', $saisi, 'string', 'max:100'],
            'prenom_tuteur' => ['nullable', $saisi, 'string', 'max:150'],
            'contact_tuteur' => ['required', 'regex:/^\d{8}$/'],
            'tuteur_sexe' => ['nullable', $saisi, Rule::in(['M', 'F'])],
            'tuteur_a_cnib' => ['required', 'boolean'],
            'tuteur_cnib' => ['nullable', 'required_if:tuteur_a_cnib,1', 'string', 'max:50'],
            'tuteur_pret_continuer' => ['required', 'boolean'],
            'tuteur_raison_arret' => ['nullable', 'required_if:tuteur_pret_continuer,0', 'string', 'max:255'],
        ];
    }

    private function reglesVieEtSante(Request $request): array
    {
        $vulnerabilitesSaisies = (array) $request->input('vulnerabilites');

        return [
            // Conditions de vie et vulnérabilités
            'lieu_de_vie' => self::choix(Oev::lieuxDeVie(true)),
            'lieu_de_vie_precision' => ['nullable', 'required_if:lieu_de_vie,autre', 'string', 'max:150'],
            'vulnerabilites' => ['nullable', 'array'],
            'vulnerabilites.*' => [Rule::in(array_keys(Oev::vulnerabilites(true)))],
            'vulnerabilite_precision' => ['nullable', Rule::requiredIf(in_array('autre', $vulnerabilitesSaisies, true)), 'string', 'max:255'],

            // Santé
            'handicap' => ['required', 'boolean'],
            'types_handicap' => ['nullable', 'array', 'required_if:handicap,1'],
            'types_handicap.*' => [Rule::in(array_keys(Oev::TYPES_HANDICAP))],
            'nature_handicap' => ['nullable', 'string', 'max:255'],
            'maladie_chronique' => ['nullable', 'boolean'],
            'maladie_nom' => ['nullable', 'required_if:maladie_chronique,1', 'string', 'max:150'],
            'suivi_clinique' => ['nullable', 'required_if:maladie_chronique,1', 'boolean'],
        ];
    }

    private function reglesMenageEtSuivi(array $c): array
    {
        return [
            // Ménage
            'source_revenu' => [...self::choix(Oev::SOURCES_REVENU), ReglesCoherenceOev::sourceRevenuCoherente($c['deuxParentsDecedes'])],
            'niveau_revenu' => self::choix(Oev::NIVEAUX),
            'logement' => self::choix(Oev::LOGEMENTS),

            // Identification du cas
            'date_identification' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'identifie_par' => self::choix(Oev::IDENTIFIE_PAR),
            'niveau_priorite' => self::choix(Oev::NIVEAUX),

            // Gestionnaire du cas (agent qui suit l'enfant)
            'gestionnaire_nom' => ['nullable', 'string', 'max:150'],
            'gestionnaire_fonction' => ['nullable', 'string', 'max:150'],
            'gestionnaire_contact' => ['nullable', 'regex:/^\d{8}$/'],
        ];
    }

    private function reglesScolarite(Request $request, array $c): array
    {
        $estScolarise = $c['estScolarise'];
        $scolariseRequis = Rule::requiredIf($estScolarise);
        // École de l'année en cours : choisie parmi celles de la commune de l'enfant (Paramètres > Écoles),
        // ou « autre » pour un établissement hors liste, alors saisi
        $ecoleDeLaListe = filled($request->input('ecole_id')) && $request->input('ecole_id') !== 'autre';

        return [
            'situation_scolaire' => ['required', Rule::in(array_keys(Oev::SITUATIONS_SCOLAIRES))],
            'niveau_etude' => ['nullable', Rule::requiredIf($c['aEteScolarise']), Rule::in(array_keys(Oev::NIVEAUX_ETUDE))],
            'raison_non_scolarisation' => ['nullable', Rule::requiredIf($c['nonScolarise']), Rule::in(array_keys(Oev::RAISONS_NON_SCOLARISATION))],
            'raison_non_scolarisation_precision' => ['nullable', Rule::requiredIf($c['nonScolarise'] && $request->input('raison_non_scolarisation') === 'autre'), 'string', 'max:255'],
            'ecole_precedente_id' => ['nullable', ReglesCoherenceOev::ecoleDeLaCommune($request, $c['ecolePrecedenteDemandee'])],
            'etablissement_precedent' => ['nullable', Rule::requiredIf($c['ecolePrecedenteDemandee'] && $request->input('ecole_precedente_id') === 'autre'), 'string', 'max:255'],
            // La classe de l'année précédente fixe le barème de la moyenne (/10 ou /20)
            'classe_precedente' => ['nullable', Rule::requiredIf(filled($request->input('moyenne_annuelle'))), Rule::in(array_keys(Oev::toutesLesClasses()))],
            'moyenne_annuelle' => ['nullable', 'numeric', 'min:0', ReglesCoherenceOev::moyenneDansLeBareme($request)],
            'appreciation' => [...self::choix(Oev::APPRECIATIONS), ReglesCoherenceOev::appreciationCoherente($request, $estScolarise)],
            'performance_scolaire' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::PERFORMANCES_SCOLAIRES))],
            'performance_difficultes' => ['nullable', Rule::requiredIf($estScolarise && $request->input('performance_scolaire') === 'difficultes'), 'string', 'max:255'],
            'systeme_educatif' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::SYSTEMES_EDUCATIFS))],
            'ecole_id' => ['nullable', ReglesCoherenceOev::ecoleDeLaCommune($request, $estScolarise)],
            'etablissement_actuel' => ['nullable', Rule::requiredIf($estScolarise && ! $ecoleDeLaListe), 'string', 'max:255'],
            'type_etablissement' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'rib_etablissement' => ['nullable', 'string', 'max:60'],
            // La classe doit appartenir au niveau d'étude choisi (ex. pas de « 6e » en primaire)
            'classe' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::classesDuNiveau($request->input('niveau_etude')))), ReglesCoherenceOev::classeActuelleCoherente($request)],
            'frais_scolarite' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function reglesFormation(Request $request, array $c): array
    {
        return [
            'formation_professionnelle' => ['nullable', 'boolean'],
            'formation_etat' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::ETATS_FORMATION))],
            'formation_filiere' => ['nullable', 'required_if:formation_professionnelle,1', 'string', 'max:150'],
            'formation_type_centre' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            // Centre du référentiel (son RIB sert au paiement d'un parrainage en formation) ; facultatif
            'centre_formation_id' => ['nullable', 'integer', Rule::exists('ecoles', 'id')->where('nature', Ecole::NATURE_CENTRE)],
            'formation_duree_mois' => ['nullable', 'required_if:formation_professionnelle,1', 'integer', 'min:1', 'max:120'],
            // Formation achevée : la durée reçue est la durée totale (non demandée)
            'formation_duree_recue_mois' => ['nullable', Rule::requiredIf($request->input('formation_professionnelle') === '1' && $request->input('formation_etat') === 'en_cours'),
                'integer', 'min:0', ReglesCoherenceOev::dureeRecueCoherente($request)],
            // Ni scolarisé ni en formation : son souhait (école, formation…) oriente un futur parrainage
            'souhait_avenir' => ['nullable', Rule::requiredIf($c['sansActivite']), Rule::in(array_keys(Oev::SOUHAITS_AVENIR)),
                Rule::when($c['exclu'], [Rule::notIn(['ecole'])])],
            'souhait_avenir_precision' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** Pièces jointes : taille maximale propre à chaque type de pièce. */
    private function reglesFichiers(): array
    {
        $regles = [];
        $messages = [];
        foreach (array_keys(Oev::DOCUMENTS) as $type) {
            $max = Oev::tailleMaxFichierKo($type);
            $regles[$type] = $type === 'photo'
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png', "max:{$max}"]
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$max}"];

            // Fichier refusé par PHP lui-même (au-delà de upload_max_filesize)
            $messages["{$type}.uploaded"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo). Veuillez le sélectionner à nouveau.';
            $messages["{$type}.max"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo).';
        }

        return [$regles, $messages];
    }

    /**
     * Un DP ne saisit que des dossiers de sa province, un DR (complément) que de sa région.
     * Ajoute la règle correspondante à $regles et renvoie son message d'erreur.
     */
    private function limiterALaZone(Request $request, array &$regles): array
    {
        $perimetre = Perimetre::pour($request->user());

        [$champ, $valeurs, $message] = match (true) {
            ! $perimetre->defini => [
                $perimetre->niveau === User::NIVEAU_REGION ? 'region_id' : 'province_id',
                [],
                'Votre compte n’est rattaché à aucune localité : demandez à un administrateur de le compléter.',
            ],
            (bool) $perimetre->province => [
                'province_id',
                [$perimetre->province->id],
                "Vous ne pouvez enregistrer que des dossiers de votre province ({$perimetre->province->nom}).",
            ],
            (bool) $perimetre->region => [
                'region_id',
                [$perimetre->region->id],
                "Vous ne pouvez enregistrer que des dossiers de votre région ({$perimetre->region->nom}).",
            ],
            default => [null, [], null],
        };

        if ($champ === null) {
            return [];
        }
        $regles[$champ][] = Rule::in($valeurs);

        return ["{$champ}.in" => $message];
    }

    private function messages(Request $request, int $ageLimite): array
    {
        if ($request->input('handicap') === '1') {
            $messageAge = "Un enfant en situation de handicap doit avoir moins de {$ageLimite} ans pour avoir le statut d’OEV.";
        } else {
            $ageHandicap = ParametreOev::ageLimite(true);
            $messageAge = "L’enfant doit avoir moins de {$ageLimite} ans pour avoir le statut d’OEV"
                . ($ageLimite < $ageHandicap ? " ({$ageHandicap} ans en situation de handicap)." : '.');
        }

        return [
            'types_handicap.required_if' => 'Précisez le type de handicap.',
            'required_if' => 'Le champ « :attribute » est obligatoire avec cette réponse.',
            'systeme_educatif.required' => self::OBLIGATOIRE_SCOLARISE,
            'etablissement_actuel.required' => self::OBLIGATOIRE_SCOLARISE,
            'type_etablissement.required' => self::OBLIGATOIRE_SCOLARISE,
            'classe.required' => self::OBLIGATOIRE_SCOLARISE,
            'classe.in' => 'La classe choisie ne correspond pas au niveau d’étude.',
            'niveau_etude.required' => 'Indiquez le niveau d’étude de l’enfant.',
            'raison_non_scolarisation.required' => 'Indiquez pourquoi l’enfant n’est pas (ou plus) scolarisé.',
            'a_acte_naissance.required' => 'Indiquez si l’enfant a un acte de naissance.',
            'tuteur_a_cnib.required' => 'Indiquez si le tuteur possède une CNIB.',
            'tuteur_cnib.required_if' => 'Saisissez le numéro de CNIB du tuteur.',
            'tuteur_lien.required' => 'Indiquez le lien du tuteur avec l’enfant.',
            'tuteur_lien_precision.required_if' => 'Précisez quel membre de la famille est le tuteur.',
            'mere_date_deces.after_or_equal' => 'La mère ne peut pas être décédée avant la naissance de l’enfant.',
            'date_identification.after_or_equal' => 'La date d’identification ne peut pas précéder la naissance de l’enfant.',
            'mere_vivante.required' => 'Indiquez si la mère de l’enfant est vivante.',
            'pere_vivant.required' => 'Indiquez si le père de l’enfant est vivant.',
            'numero_acte_naissance.required_if' => 'Saisissez le numéro de l’acte de naissance de l’enfant.',
            'mere_nom.required' => 'La mère s’occupe de l’enfant : saisissez son nom (partie « Mère »).',
            'mere_prenoms.required' => 'La mère s’occupe de l’enfant : saisissez ses prénoms (partie « Mère »).',
            'pere_nom.required' => 'Le père s’occupe de l’enfant : saisissez son nom (partie « Père »).',
            'pere_prenoms.required' => 'Le père s’occupe de l’enfant : saisissez ses prénoms (partie « Père »).',
            'tuteur_sexe.required' => 'Indiquez le sexe du tuteur.',
            'tuteur_pret_continuer.required' => 'Indiquez si le parent ou tuteur est prêt à continuer à s’occuper de l’enfant.',
            'tuteur_raison_arret.required_if' => 'Indiquez pourquoi le parent ou tuteur ne peut pas continuer à s’occuper de l’enfant.',
            'maladie_nom.required_if' => 'Indiquez le nom de la maladie.',
            'suivi_clinique.required_if' => 'Indiquez si l’enfant bénéficie d’un suivi clinique.',
            'classe_precedente.required' => 'Indiquez la classe de l’année précédente (elle détermine si la moyenne est sur 10 ou sur 20).',
            'performance_scolaire.required' => 'Indiquez les performances scolaires de l’enfant.',
            'performance_difficultes.required' => 'Expliquez les difficultés scolaires de l’enfant.',
            'formation_duree_mois.required_if' => 'Indiquez la durée de la formation (en mois).',
            'formation_duree_recue_mois.required' => 'Indiquez la durée de formation déjà reçue (en mois).',
            'souhait_avenir.required' => 'L’enfant n’est ni à l’école ni en formation : indiquez ce qu’il souhaite (école, formation…).',
            'souhait_avenir.not_in' => 'L’enfant a été exclu l’année précédente : il ne peut plus prétendre retourner à l’école. Choisissez une formation professionnelle ou une autre réponse.',
            'date_naissance.before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'date_naissance.after' => $messageAge,
            'contact_tuteur.regex' => 'Le numéro de téléphone doit comporter 8 chiffres (ex : 70 12 34 56).',
            'gestionnaire_contact.regex' => 'Le téléphone du gestionnaire du cas doit comporter 8 chiffres (ex : 70 12 34 56).',
            'lieu_provenance.required' => 'Précisez le lieu de provenance (ville, pays) s’il n’est pas dans la liste.',
        ];
    }
}
