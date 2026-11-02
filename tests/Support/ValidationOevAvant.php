<?php

namespace Tests\Support;

use App\Models\Commune;
use App\Models\Ecole;
use App\Models\Oev;
use App\Models\ParametreOev;
use App\Models\User;
use App\Support\Perimetre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Copie de OevController::valider() avant son découpage en service (ValidationOev),
 * pour vérifier que le service donne exactement les mêmes résultats.
 */
class ValidationOevAvant
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

    public function valider(Request $request): array
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

        $choix = fn (array $liste) => ['nullable', Rule::in(array_keys($liste))];
        $entree = fn (string $champ) => $request->input($champ);

        // Conditions entre champs (les mêmes que l'affichage conditionnel du formulaire)
        $situation = $entree('situation_scolaire');
        $estScolarise = $situation === 'scolarise';
        $aEteScolarise = in_array($situation, ['scolarise', 'descolarise'], true);
        $nonScolarise = in_array($situation, ['non_scolarise', 'descolarise'], true);
        $vulnerabilitesSaisies = (array) $entree('vulnerabilites');
        $ageLimite = ParametreOev::ageLimite($entree('handicap') === '1');
        $sansActivite = Oev::sansScolariteNiFormation($situation, $entree('formation_professionnelle'), $entree('formation_etat'));
        // Exclu l'année précédente : il ne peut plus prétendre retourner à l'école (formation, ou rien pour le moment)
        $exclu = $situation !== 'non_scolarise' && (filled($entree('ecole_precedente_id')) || filled($entree('etablissement_precedent')))
            && $entree('appreciation') === 'exclu';
        $scolariseRequis = Rule::requiredIf($estScolarise);
        // École de l'année en cours : choisie parmi celles de la commune de l'enfant (Paramètres > Écoles),
        // ou « autre » pour un établissement hors liste, alors saisi
        $ecoleDeLaListe = filled($entree('ecole_id')) && $entree('ecole_id') !== 'autre';
        $ecoleDeLaCommune = fn (bool $demandee) => function (string $attribut, $valeur, \Closure $echec) use ($entree, $demandee): void {
            if ($demandee && $valeur !== 'autre' && ! Ecole::whereKey($valeur)->where('commune_id', $entree('commune_id'))->exists()) {
                $echec('Choisissez l’établissement dans la liste des écoles de la commune de l’enfant.');
            }
        };
        // Année précédente : école de la liste, « autre » (alors saisie) ou aucune
        $ecolePrecedenteDemandee = filled($situation) && $situation !== 'non_scolarise';

        // Parent(s) qui s'occupent de l'enfant : ils doivent être déclarés vivants, et leurs nom et prénoms
        // (saisis dans la partie « Parents ») sont repris comme ceux du tuteur.
        $parentsTuteurs = Oev::parentsTuteurs($entree('tuteur_lien'));
        $tuteurEstParent = $parentsTuteurs !== [];
        $tuteurVivant = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            foreach (Oev::parentsTuteurs($valeur) as $parent) {
                $etat = $entree($parent === 'mere' ? 'mere_vivante' : 'pere_vivant');
                if ($etat === 'non') {
                    $echec($parent === 'mere'
                        ? 'La mère est déclarée décédée : elle ne peut pas être la tutrice de l’enfant.'
                        : 'Le père est déclaré décédé : il ne peut pas être le tuteur de l’enfant.');
                } elseif ($etat !== 'oui') {
                    $echec($parent === 'mere'
                        ? 'On ne sait pas si la mère est vivante : elle ne peut pas être indiquée comme tutrice.'
                        : 'On ne sait pas si le père est vivant : il ne peut pas être indiqué comme tuteur.');
                }
            }
        };

        // Lieu de provenance (enfant déplacé, réfugié…) : même principe que le lieu de naissance
        $groupeMobile = in_array($entree('groupe_population'), Oev::GROUPES_MOBILES, true);
        $lieuProvenanceAutre = $groupeMobile && $entree('lieu_provenance_commune_id') === 'autre';
        $communeDeProvenance = function (string $attribut, $valeur, \Closure $echec): void {
            if ($valeur !== 'autre' && ! Commune::whereKey($valeur)->exists()) {
                $echec('Choisissez le lieu de provenance dans la liste.');
            }
        };
        $deuxParentsDecedes = $entree('mere_vivante') === 'non' && $entree('pere_vivant') === 'non';

        // Moyenne : sur 10 au préscolaire et au primaire, sur 20 ensuite (selon la classe de l'année précédente)
        $bareme = Oev::baremeMoyenne($entree('classe_precedente'));
        $moyenneDansLeBareme = function (string $attribut, $valeur, \Closure $echec) use ($bareme, $entree): void {
            if (is_numeric($valeur) && $valeur > $bareme) {
                $classe = Oev::toutesLesClasses()[$entree('classe_precedente')] ?? null;
                $echec("La moyenne doit être comprise entre 0 et {$bareme}" . ($classe ? " (notation sur {$bareme} en {$classe})." : '.'));
            }
        };
        // Appréciation cohérente avec la classe actuelle : un redoublant reste dans la même classe, un admis en change
        $appreciationCoherente = function (string $attribut, $valeur, \Closure $echec) use ($entree, $estScolarise): void {
            $actuelle = $entree('classe');
            $precedente = $entree('classe_precedente');
            if (! $estScolarise || ! $actuelle || ! $precedente) {
                return;
            }
            // Une classe plus avancée l'an dernier qu'aujourd'hui est signalée sur la classe précédente
            $rangActuel = Oev::rangClasse($actuelle);
            $rangPrecedent = Oev::rangClasse($precedente);
            if ($rangActuel !== null && $rangPrecedent !== null && $rangPrecedent > $rangActuel) {
                return;
            }
            if ($valeur === 'redouble' && $actuelle !== $precedente) {
                $echec('L’enfant a redoublé : sa classe actuelle devrait être la même que l’année précédente.');
            }
            if ($valeur === 'admis' && $actuelle === $precedente) {
                $echec('L’enfant a été admis : sa classe actuelle ne peut pas être la même que l’année précédente.');
            }
        };
        // Formation : la durée déjà reçue ne peut pas dépasser la durée prévue
        $dureeRecueCoherente = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            $totale = $entree('formation_duree_mois');
            if (is_numeric($valeur) && is_numeric($totale) && (int) $valeur > (int) $totale) {
                $echec('La durée déjà reçue ne peut pas dépasser la durée de la formation.');
            }
        };
        // Le père peut être décédé pendant la grossesse (jusqu'à 9 mois avant la naissance), pas avant
        $decesPereCoherent = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            $naissance = strtotime((string) $entree('date_naissance'));
            if ($naissance && strtotime((string) $valeur) < strtotime('-9 months', $naissance)) {
                $echec('Le père ne peut pas être décédé plus de 9 mois avant la naissance de l’enfant.');
            }
        };
        // La classe en cours ne peut pas être en dessous de celle de l'année précédente (ex. 5e puis 6e)
        $classeActuelleCoherente = function (string $attribut, $valeur, \Closure $echec) use ($entree): void {
            $rangActuel = Oev::rangClasse($valeur);
            $rangPrecedent = filled($entree('etablissement_precedent')) ? Oev::rangClasse($entree('classe_precedente')) : null;
            if ($rangActuel !== null && $rangPrecedent !== null && $rangActuel < $rangPrecedent) {
                $echec('La classe de l’année en cours ne peut pas être inférieure à celle de l’année précédente.');
            }
        };
        // Fonds propres de la famille : impossible quand les deux parents sont décédés
        $sourceRevenuCoherente = function (string $attribut, $valeur, \Closure $echec) use ($deuxParentsDecedes): void {
            if ($deuxParentsDecedes && in_array($valeur, Oev::SOURCES_REVENU_PARENTS_VIVANTS, true)) {
                $echec('Les deux parents sont décédés : la source de revenu ne peut pas être « ' . Oev::SOURCES_REVENU[$valeur] . ' ».');
            }
        };

        $regles = [
            // Identité
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:150'],
            'sexe' => ['required', Rule::in(array_keys(Oev::SEXES))],
            // Âge de sortie (Paramètres > Âge des OEV) : plus élevé pour un enfant en situation de handicap
            'date_naissance' => ['required', 'date', 'before_or_equal:today', 'after:' . now()->subYears($ageLimite)->toDateString()],
            'date_naissance_estimee' => ['nullable', 'boolean'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'a_acte_naissance' => ['required', 'boolean'],
            'numero_acte_naissance' => ['nullable', 'required_if:a_acte_naissance,1', 'string', 'max:50'],
            'groupe_population' => $choix(Oev::groupesPopulation(true)),

            // Adresse : région, province et commune choisies dans le référentiel des localités
            ...Oev::reglesLocalite($request),
            // Village facultatif, mais forcément dans la commune choisie
            'village_id' => ['nullable', 'integer', Rule::exists('villages', 'id')->where('commune_id', $request->input('commune_id'))],
            'quartier' => ['nullable', 'string', 'max:150'],
            'lieu_provenance_commune_id' => ['nullable', $communeDeProvenance],
            'lieu_provenance' => ['nullable', Rule::requiredIf($lieuProvenanceAutre), 'string', 'max:150'],

            // Parents (situation d'orphelin) : « vivant ? » obligatoire, le statut OEV en est déduit
            // Nom et prénoms obligatoires pour le parent qui s'occupe de l'enfant (repris comme tuteur)
            'mere_nom' => ['nullable', Rule::requiredIf(in_array('mere', $parentsTuteurs, true)), 'string', 'max:100'],
            'mere_prenoms' => ['nullable', Rule::requiredIf(in_array('mere', $parentsTuteurs, true)), 'string', 'max:150'],
            'mere_vivante' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'mere_date_deces' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'mere_deces_confirme' => ['nullable', 'boolean'],
            'pere_nom' => ['nullable', Rule::requiredIf(in_array('pere', $parentsTuteurs, true)), 'string', 'max:100'],
            'pere_prenoms' => ['nullable', Rule::requiredIf(in_array('pere', $parentsTuteurs, true)), 'string', 'max:150'],
            'pere_vivant' => ['required', Rule::in(array_keys(Oev::PARENT_VIVANT))],
            'pere_date_deces' => ['nullable', 'date', 'before_or_equal:today', $decesPereCoherent],
            'pere_deces_confirme' => ['nullable', 'boolean'],

            // Tuteur
            'tuteur_lien' => ['required', Rule::in(array_keys(Oev::liensTuteur(true))), $tuteurVivant],
            'tuteur_lien_precision' => ['nullable', 'required_if:tuteur_lien,autre_parent', 'string', 'max:100'],
            // Nom, prénoms et sexe demandés seulement si le tuteur n'est pas un parent (déjà saisi plus haut)
            'nom_tuteur' => ['nullable', Rule::requiredIf(! $tuteurEstParent), 'string', 'max:100'],
            'prenom_tuteur' => ['nullable', Rule::requiredIf(! $tuteurEstParent), 'string', 'max:150'],
            'contact_tuteur' => ['required', 'regex:/^\d{8}$/'],
            'tuteur_sexe' => ['nullable', Rule::requiredIf(! $tuteurEstParent), Rule::in(['M', 'F'])],
            'tuteur_a_cnib' => ['required', 'boolean'],
            'tuteur_cnib' => ['nullable', 'required_if:tuteur_a_cnib,1', 'string', 'max:50'],
            'tuteur_pret_continuer' => ['required', 'boolean'],
            'tuteur_raison_arret' => ['nullable', 'required_if:tuteur_pret_continuer,0', 'string', 'max:255'],

            // Conditions de vie et vulnérabilités
            'lieu_de_vie' => $choix(Oev::lieuxDeVie(true)),
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

            // Ménage
            'source_revenu' => [...$choix(Oev::SOURCES_REVENU), $sourceRevenuCoherente],
            'niveau_revenu' => $choix(Oev::NIVEAUX),
            'logement' => $choix(Oev::LOGEMENTS),

            // Identification du cas
            'date_identification' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'identifie_par' => $choix(Oev::IDENTIFIE_PAR),
            'niveau_priorite' => $choix(Oev::NIVEAUX),

            // Gestionnaire du cas (agent qui suit l'enfant)
            'gestionnaire_nom' => ['nullable', 'string', 'max:150'],
            'gestionnaire_fonction' => ['nullable', 'string', 'max:150'],
            'gestionnaire_contact' => ['nullable', 'regex:/^\d{8}$/'],

            // Scolarité
            'situation_scolaire' => ['required', Rule::in(array_keys(Oev::SITUATIONS_SCOLAIRES))],
            'niveau_etude' => ['nullable', Rule::requiredIf($aEteScolarise), Rule::in(array_keys(Oev::NIVEAUX_ETUDE))],
            'raison_non_scolarisation' => ['nullable', Rule::requiredIf($nonScolarise), Rule::in(array_keys(Oev::RAISONS_NON_SCOLARISATION))],
            'raison_non_scolarisation_precision' => ['nullable', Rule::requiredIf($nonScolarise && $entree('raison_non_scolarisation') === 'autre'), 'string', 'max:255'],
            'ecole_precedente_id' => ['nullable', $ecoleDeLaCommune($ecolePrecedenteDemandee)],
            'etablissement_precedent' => ['nullable', Rule::requiredIf($ecolePrecedenteDemandee && $entree('ecole_precedente_id') === 'autre'), 'string', 'max:255'],
            // La classe de l'année précédente fixe le barème de la moyenne (/10 ou /20)
            'classe_precedente' => ['nullable', Rule::requiredIf(filled($entree('moyenne_annuelle'))), Rule::in(array_keys(Oev::toutesLesClasses()))],
            'moyenne_annuelle' => ['nullable', 'numeric', 'min:0', $moyenneDansLeBareme],
            'appreciation' => [...$choix(Oev::APPRECIATIONS), $appreciationCoherente],
            'performance_scolaire' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::PERFORMANCES_SCOLAIRES))],
            'performance_difficultes' => ['nullable', Rule::requiredIf($estScolarise && $entree('performance_scolaire') === 'difficultes'), 'string', 'max:255'],
            'systeme_educatif' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::SYSTEMES_EDUCATIFS))],
            'ecole_id' => ['nullable', $ecoleDeLaCommune($estScolarise)],
            'etablissement_actuel' => ['nullable', Rule::requiredIf($estScolarise && ! $ecoleDeLaListe), 'string', 'max:255'],
            'type_etablissement' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'rib_etablissement' => ['nullable', 'string', 'max:60'],
            // La classe doit appartenir au niveau d'étude choisi (ex. pas de « 6e » en primaire)
            'classe' => ['nullable', $scolariseRequis, Rule::in(array_keys(Oev::classesDuNiveau($entree('niveau_etude')))), $classeActuelleCoherente],
            'frais_scolarite' => ['nullable', 'integer', 'min:0'],
            'formation_professionnelle' => ['nullable', 'boolean'],
            'formation_etat' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::ETATS_FORMATION))],
            'formation_filiere' => ['nullable', 'required_if:formation_professionnelle,1', 'string', 'max:150'],
            'formation_type_centre' => ['nullable', 'required_if:formation_professionnelle,1', Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            // Centre du référentiel (son RIB sert au paiement d'un parrainage en formation) ; facultatif
            'centre_formation_id' => ['nullable', 'integer', Rule::exists('ecoles', 'id')->where('nature', Ecole::NATURE_CENTRE)],
            'formation_duree_mois' => ['nullable', 'required_if:formation_professionnelle,1', 'integer', 'min:1', 'max:120'],
            // Formation achevée : la durée reçue est la durée totale (non demandée)
            'formation_duree_recue_mois' => ['nullable', Rule::requiredIf($entree('formation_professionnelle') === '1' && $entree('formation_etat') === 'en_cours'), 'integer', 'min:0', $dureeRecueCoherente],
            // Ni scolarisé ni en formation : son souhait (école, formation…) oriente un futur parrainage
            'souhait_avenir' => ['nullable', Rule::requiredIf($sansActivite), Rule::in(array_keys(Oev::SOUHAITS_AVENIR)),
                Rule::when($exclu, [Rule::notIn(['ecole'])])],
            'souhait_avenir_precision' => ['nullable', 'string', 'max:150'],

        ];

        $messagesFichiers = $this->limiterALaZone($request, $regles);
        foreach (array_keys(Oev::DOCUMENTS) as $type) {
            $max = Oev::tailleMaxFichierKo($type);
            $regles[$type] = $type === 'photo'
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png', "max:{$max}"]
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$max}"];

            // Fichier refusé par PHP lui-même (au-delà de upload_max_filesize)
            $messagesFichiers["{$type}.uploaded"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo). Veuillez le sélectionner à nouveau.';
            $messagesFichiers["{$type}.max"] = Oev::DOCUMENTS[$type] . ' : fichier trop volumineux (maximum ' . round($max / 1024, 1) . ' Mo).';
        }

        $obligatoireScolarise = 'Le champ « :attribute » est obligatoire pour un enfant scolarisé.';
        if ($entree('handicap') === '1') {
            $messageAge = "Un enfant en situation de handicap doit avoir moins de {$ageLimite} ans pour avoir le statut d’OEV.";
        } else {
            $ageHandicap = ParametreOev::ageLimite(true);
            $messageAge = "L’enfant doit avoir moins de {$ageLimite} ans pour avoir le statut d’OEV"
                . ($ageLimite < $ageHandicap ? " ({$ageHandicap} ans en situation de handicap)." : '.');
        }
        $validated = $request->validate($regles, $messagesFichiers + [
            'types_handicap.required_if' => 'Précisez le type de handicap.',
            'required_if' => 'Le champ « :attribute » est obligatoire avec cette réponse.',
            'systeme_educatif.required' => $obligatoireScolarise,
            'etablissement_actuel.required' => $obligatoireScolarise,
            'type_etablissement.required' => $obligatoireScolarise,
            'classe.required' => $obligatoireScolarise,
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
        ] + self::MESSAGES, Oev::DOCUMENTS + self::ATTRIBUTS);

        $validated['contact_tuteur'] = Oev::formaterTelephone($validated['contact_tuteur']);
        $validated['gestionnaire_contact'] = filled($validated['gestionnaire_contact'] ?? null) ? Oev::formaterTelephone($validated['gestionnaire_contact']) : null;
        $validated['date_naissance_estimee'] = (bool) ($validated['date_naissance_estimee'] ?? false);
        // Liste des villages vide ou désactivée : non envoyée, l'ancien village ne doit pas rester
        $validated['village_id'] ??= null;
        $validated['statut'] = Oev::calculerStatut($validated['mere_vivante'], $validated['pere_vivant']);

        // Les informations masquées par une réponse ne sont pas conservées (ex. date de décès d'une mère vivante).
        $effacer = function (bool $condition, array $champs) use (&$validated): void {
            if ($condition) {
                foreach ($champs as $champ) {
                    $validated[$champ] = null;
                }
            }
        };
        $effacer($validated['mere_vivante'] !== 'non', ['mere_date_deces', 'mere_deces_confirme']);
        $effacer($validated['pere_vivant'] !== 'non', ['pere_date_deces', 'pere_deces_confirme']);
        $effacer(($validated['lieu_de_vie'] ?? null) !== 'autre', ['lieu_de_vie_precision']);
        $effacer(! $validated['handicap'], ['types_handicap', 'nature_handicap']);
        $effacer(! in_array($validated['groupe_population'] ?? null, Oev::GROUPES_MOBILES, true), ['lieu_provenance', 'lieu_provenance_commune_id']);
        $effacer(! $validated['tuteur_a_cnib'], ['tuteur_cnib']);
        $effacer($validated['tuteur_lien'] !== 'autre_parent', ['tuteur_lien_precision']);
        $effacer((bool) $validated['tuteur_pret_continuer'], ['tuteur_raison_arret']);
        $effacer(! $validated['a_acte_naissance'], ['numero_acte_naissance']);
        $effacer(! ($validated['maladie_chronique'] ?? false), ['maladie_nom', 'suivi_clinique']);
        $effacer($validated['situation_scolaire'] !== 'scolarise', ['systeme_educatif', 'etablissement_actuel', 'rib_etablissement', 'type_etablissement', 'classe', 'frais_scolarite', 'performance_scolaire', 'performance_difficultes']);
        $effacer(($validated['performance_scolaire'] ?? null) !== 'difficultes', ['performance_difficultes']);
        $effacer(! in_array($validated['situation_scolaire'], ['scolarise', 'descolarise'], true), ['niveau_etude']);
        $effacer($validated['situation_scolaire'] === 'non_scolarise', ['etablissement_precedent', 'classe_precedente', 'moyenne_annuelle', 'appreciation']);
        $effacer(blank($validated['etablissement_precedent'] ?? null), ['classe_precedente', 'moyenne_annuelle', 'appreciation']);
        $effacer(! in_array($validated['situation_scolaire'], ['non_scolarise', 'descolarise'], true), ['raison_non_scolarisation', 'raison_non_scolarisation_precision']);
        $effacer(($validated['raison_non_scolarisation'] ?? null) !== 'autre', ['raison_non_scolarisation_precision']);
        $effacer(! ($validated['formation_professionnelle'] ?? false), ['formation_etat', 'formation_filiere', 'formation_type_centre', 'centre_formation_id', 'formation_duree_mois', 'formation_duree_recue_mois']);
        // Souhait seulement pour un enfant ni scolarisé ni en formation ; la formation souhaitée seulement s'il choisit la formation
        $effacer(! $sansActivite, ['souhait_avenir', 'souhait_avenir_precision']);
        $effacer(($validated['souhait_avenir'] ?? null) !== 'formation', ['souhait_avenir_precision']);
        // Formation achevée : toute la durée a été reçue
        if (($validated['formation_etat'] ?? null) === 'achevee') {
            $validated['formation_duree_recue_mois'] = $validated['formation_duree_mois'];
        }

        // École de la liste : son nom est repris comme établissement fréquenté ; sinon établissement saisi, sans école rattachée
        if ($validated['situation_scolaire'] === 'scolarise' && filled($validated['ecole_id'] ?? null) && $validated['ecole_id'] !== 'autre') {
            $validated['etablissement_actuel'] = Ecole::findOrFail($validated['ecole_id'])->nom;
        } else {
            $validated['ecole_id'] = null;
        }
        // École précédente de la liste (son nom a été repris avant la validation) ; sinon établissement saisi ou aucun
        if (filled($validated['etablissement_precedent'] ?? null) && filled($validated['ecole_precedente_id'] ?? null) && $validated['ecole_precedente_id'] !== 'autre') {
            $validated['ecole_precedente_id'] = (int) $validated['ecole_precedente_id'];
        } else {
            $validated['ecole_precedente_id'] = null;
        }

        // Lieu de naissance saisi librement : l'ancien rattachement à une commune est abandonné
        $validated['lieu_naissance_commune_id'] = null;
        // Lieu de provenance : commune du référentiel, ou lieu saisi quand il n'y figure pas
        if (($validated['lieu_provenance_commune_id'] ?? null) === 'autre') {
            $validated['lieu_provenance_commune_id'] = null;
        } else {
            $validated['lieu_provenance'] = null;
        }

        // Tuteur = parent(s) : nom et prénoms repris de la partie « Parents », sexe déduit
        $parents = Oev::parentsTuteurs($validated['tuteur_lien']);
        if ($parents !== []) {
            $validated['nom_tuteur'] = collect($parents)->map(fn ($p) => $validated["{$p}_nom"])->unique()->implode(' / ');
            $validated['prenom_tuteur'] = collect($parents)->map(fn ($p) => $validated["{$p}_prenoms"])->implode(' et ');
        }
        $validated['tuteur_sexe'] = match ($validated['tuteur_lien']) {
            'mere' => 'F',
            'pere' => 'M',
            'parents' => null,
            default => $validated['tuteur_sexe'] ?? null,
        };

        // Vulnérabilités : celles déduites des réponses sont ajoutées d'office ; « grossesse » ne concerne que les filles
        $vulnerabilites = array_diff((array) ($validated['vulnerabilites'] ?? []), ['orphelin', 'handicap', 'sans_acte_naissance']);
        if ($validated['sexe'] !== 'F') {
            $vulnerabilites = array_diff($vulnerabilites, ['grossesse']);
        }
        $vulnerabilites = array_values(array_unique(array_merge(Oev::vulnerabilitesAutomatiques($validated), $vulnerabilites)));
        $validated['vulnerabilites'] = $vulnerabilites ?: null;
        $effacer(! in_array('autre', $vulnerabilites, true), ['vulnerabilite_precision']);

        return collect($validated)->except(array_keys(Oev::DOCUMENTS))->all();
    }

    /**
     * Un DP ne saisit que des dossiers de sa province, un DR (complément) que de sa région.
     * Ajoute la règle correspondante à $regles et renvoie son message d'erreur.
     */
    private function limiterALaZone(Request $request, array &$regles): array
    {
        $perimetre = Perimetre::pour($request->user());

        if (! $perimetre->defini) {
            $champ = $perimetre->niveau === User::NIVEAU_REGION ? 'region_id' : 'province_id';
            $regles[$champ][] = Rule::in([]);

            return ["{$champ}.in" => 'Votre compte n’est rattaché à aucune localité : demandez à un administrateur de le compléter.'];
        }
        if ($perimetre->province) {
            $regles['province_id'][] = Rule::in([$perimetre->province->id]);

            return ['province_id.in' => "Vous ne pouvez enregistrer que des dossiers de votre province ({$perimetre->province->nom})."];
        }
        if ($perimetre->region) {
            $regles['region_id'][] = Rule::in([$perimetre->region->id]);

            return ['region_id.in' => "Vous ne pouvez enregistrer que des dossiers de votre région ({$perimetre->region->nom})."];
        }

        return [];
    }
}
