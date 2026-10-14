<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CloisonneParZone;
use App\Models\BeneficiaireSession;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;
use App\Services\Parrainage\FicheApresSuivi;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suivi des parrainés : chaque OEV retenu sur la liste définitive d'une session reçoit un suivi de fin
 * d'année (résultat scolaire ou de formation, recommandation pour un nouveau parrainage).
 * Renseigné par le DP pour sa province et par le niveau central ; consulté par tous dans leur zone.
 */
class SuiviParrainageController extends Controller
{
    use CloisonneParZone;

    private const PAR_PAGE = 50;

    public function __construct(private readonly FicheApresSuivi $fiche)
    {
    }

    /** Filtre « état du suivi » (cartes de compteurs et liste déroulante). */
    public const ETATS = [
        'a_renseigner' => 'Suivis à renseigner',
        'renseigne' => 'Suivis renseignés',
        'reconduire' => 'Recommandés pour un nouveau parrainage',
    ];

    public function index(Request $request): View
    {
        $annees = SessionParrainage::where('etat', '!=', SessionParrainage::ETAT_EN_COURS)->distinct()->orderByDesc('annee')->pluck('annee');
        $filtres = [
            'annee' => $annees->contains($request->query('annee')) ? (string) $request->query('annee') : (string) $annees->first(),
            'session' => (int) $request->query('session') ?: null,
            'type' => array_key_exists((string) $request->query('type'), [SessionParrainage::TYPE_SCOLAIRE => 1, SessionParrainage::TYPE_FORMATION => 1]) ? (string) $request->query('type') : null,
            'etat' => array_key_exists((string) $request->query('etat'), self::ETATS) ? (string) $request->query('etat') : null,
            'q' => trim((string) $request->query('q')) ?: null,
        ];

        $base = BeneficiaireSession::parraines()
            ->whereHas('session', fn ($q) => $q->where('annee', $filtres['annee'])->when($filtres['session'], fn ($q, $id) => $q->whereKey($id)))
            ->tap(fn ($q) => $this->cloisonner($q, 'oev'))
            ->whereHas('oev', fn ($q) => $q->when($filtres['q'], fn ($q, $t) => $q->where(fn ($q) => $q
                ->whereLike('code', "%{$t}%")->orWhereLike('nom', "%{$t}%")->orWhereLike('prenom', "%{$t}%"))))
            ->when($filtres['type'], fn ($q, $type) => $q->where('type_appui', $type));

        $compteurs = [
            'total' => (clone $base)->count(),
            'renseignes' => (clone $base)->has('suivi')->count(),
            'reconduire' => (clone $base)->whereHas('suivi', fn ($q) => $q->where('a_reconduire', true))->count(),
        ];

        $lignes = $base
            ->when($filtres['etat'] === 'a_renseigner', fn ($q) => $q->doesntHave('suivi'))
            ->when($filtres['etat'] === 'renseigne', fn ($q) => $q->has('suivi'))
            ->when($filtres['etat'] === 'reconduire', fn ($q) => $q->whereHas('suivi', fn ($q) => $q->where('a_reconduire', true)))
            ->with(['oev.province', 'oev.commune', 'session', 'suivi.auteur'])
            // Les suivis à renseigner d'abord
            ->orderByRaw('(select count(*) from suivis_parrainage s where s.beneficiaire_session_id = beneficiaires_session.id)')
            ->orderBy('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        return view('parrainage.suivis.index', [
            'lignes' => $lignes,
            'filtres' => $filtres,
            'annees' => $annees,
            'sessions' => SessionParrainage::where('annee', $filtres['annee'])->where('etat', '!=', SessionParrainage::ETAT_EN_COURS)->orderBy('numero')->get(),
            'compteurs' => $compteurs,
            'peutRenseigner' => $request->user()->can('suivre parrainages'),
        ]);
    }

    public function enregistrer(Request $request, BeneficiaireSession $ligne): RedirectResponse
    {
        $ligne->load(['session', 'oev']);
        abort_unless($ligne->statut === BeneficiaireSession::RETENU && $ligne->session->etat !== SessionParrainage::ETAT_EN_COURS, 404);
        $this->verifierZone($ligne, 'oev');

        $formation = $ligne->type_appui === SessionParrainage::TYPE_FORMATION;
        $existant = $ligne->suivi()->first();
        // Classe de l'année suivie : celle de la fiche avant le premier enregistrement du suivi
        $classeSuivie = FicheApresSuivi::classeSuivie($ligne->oev, $existant);
        $bareme = Oev::baremeMoyenne($classeSuivie);
        $request->merge([
            'moyenne' => str_replace(',', '.', (string) $request->input('moyenne')) ?: null,
            'prochains_frais' => Montant::normaliserSaisie($request->input('prochains_frais')),
            'prochains_frais_formation' => Montant::normaliserSaisie($request->input('prochains_frais_formation')),
        ]);
        $orientation = $request->input('orientation');
        $resultat = (string) $request->input('resultat');
        // Les frais de l'année prochaine sont demandés dès que l'OEV continue (école ou formation)
        $versEcole = ! $formation && $orientation === SuiviParrainage::ECOLE;
        $versFormation = ! $formation && $orientation === SuiviParrainage::FORMATION;
        $poursuitFormation = $formation && $resultat === 'poursuit';
        // Classe de l'an prochain calculée ; saisie seulement si la classe suivie est inconnue
        $classeCalculee = $formation ? null : SuiviParrainage::classeSuivante($classeSuivie, (string) $request->input('resultat'));

        $donnees = $request->validate([
            'resultat' => ['required', Rule::in(array_keys(SuiviParrainage::resultatsPour($ligne->type_appui)))],
            'moyenne' => $formation ? ['prohibited'] : ['nullable', 'numeric', 'min:0', "max:{$bareme}"],
            'insertion' => ['nullable', Rule::requiredIf($formation && $request->input('resultat') === 'achevee'), Rule::in(array_keys(SuiviParrainage::INSERTIONS))],
            // L'année prochaine (appui scolaire) : continuer l'école, partir en formation professionnelle ou arrêter
            'orientation' => $formation ? ['prohibited'] : ['required', Rule::in(array_keys(SuiviParrainage::ORIENTATIONS))],
            'prochaine_filiere' => ['nullable', Rule::requiredIf(! $formation && $orientation === SuiviParrainage::FORMATION), 'string', 'max:150'],
            'prochaine_classe' => ['nullable', Rule::requiredIf(! $formation && $orientation === SuiviParrainage::ECOLE && $classeCalculee === null), Rule::in(array_keys(Oev::toutesLesClasses()))],
            // Frais et détails de l'année prochaine
            'prochains_frais' => ['nullable', Rule::requiredIf($versEcole || $poursuitFormation), 'integer', 'min:0'],
            'prochains_frais_formation' => ['nullable', Rule::requiredIf($versFormation), 'integer', 'min:0'],
            'prochain_etablissement' => ['nullable', 'string', 'max:255'],
            'prochain_type_etablissement' => ['nullable', Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'prochain_type_centre' => ['nullable', Rule::requiredIf($versFormation), Rule::in(array_keys(Oev::TYPES_ETABLISSEMENT))],
            'prochaine_duree' => ['nullable', Rule::requiredIf($versFormation), 'integer', 'min:1', 'max:120'],
            'a_reconduire' => ['required', 'boolean'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ], [
            'prochains_frais.required' => $versEcole ? 'Indiquez les frais de scolarité de l’année prochaine (0 si l’école est gratuite).' : 'Indiquez les frais de la formation pour l’année prochaine (0 si elle est gratuite).',
            'prochains_frais.integer' => 'Les frais doivent être un montant entier en FCFA.',
            'prochains_frais_formation.required' => 'Indiquez les frais de la formation choisie (0 si elle est gratuite).',
            'prochains_frais_formation.integer' => 'Les frais doivent être un montant entier en FCFA.',
            'prochain_type_centre.required' => 'Indiquez si le centre de formation est public ou privé.',
            'prochaine_duree.required' => 'Indiquez la durée de la formation (en mois).',
            'resultat.required' => 'Indiquez le résultat de l’année.',
            'moyenne.max' => "La moyenne est notée sur {$bareme}.",
            'moyenne.numeric' => 'La moyenne doit être un nombre (ex : 12,5).',
            'insertion.required' => 'Indiquez la situation de l’OEV après sa formation.',
            'orientation.required' => 'Indiquez ce que fera l’OEV l’année prochaine (école, formation professionnelle ou arrêt).',
            'prochaine_filiere.required' => 'Indiquez la formation professionnelle choisie (ex : Couture).',
            'prochaine_classe.required' => 'La classe actuelle n’est pas connue : indiquez la classe de l’année prochaine.',
            'a_reconduire.required' => 'Indiquez si l’OEV est recommandé pour un nouveau parrainage.',
        ]);

        // L'année prochaine, cohérente avec le résultat et l'orientation
        if ($formation) {
            $orientation = SuiviParrainage::orientationFormation($donnees['resultat']);
            $prochaine = $orientation === SuiviParrainage::FORMATION ? ($existant?->fiche_avant['formation_filiere'] ?? $ligne->oev->formation_filiere) : null;
        } else {
            $prochaine = match ($orientation) {
                SuiviParrainage::ECOLE => $classeCalculee ?? $donnees['prochaine_classe'],
                SuiviParrainage::FORMATION => trim($donnees['prochaine_filiere']),
                default => null,
            };
        }

        $frais = isset($donnees['prochains_frais']) ? (int) $donnees['prochains_frais'] : null;
        $details = match (true) {
            $versEcole => [
                'frais' => $frais,
                'etablissement' => trim((string) ($donnees['prochain_etablissement'] ?? '')) ?: null,
                'type_etablissement' => $donnees['prochain_type_etablissement'] ?? null,
            ],
            $versFormation => [
                'frais' => (int) $donnees['prochains_frais_formation'],
                'type_centre' => $donnees['prochain_type_centre'],
                'duree_mois' => (int) $donnees['prochaine_duree'],
            ],
            $poursuitFormation => ['frais' => $frais],
            default => null,
        };

        // Suivi et fiche de l'OEV enregistrés ensemble (jamais l'un sans l'autre)
        [$suivi, $miseAJour] = DB::transaction(function () use ($ligne, $donnees, $formation, $orientation, $prochaine, $details, $existant, $request) {
            $suivi = SuiviParrainage::updateOrCreate(['beneficiaire_session_id' => $ligne->id], [
                'oev_id' => $ligne->oev_id,
                'annee' => $ligne->session->annee,
                'type_appui' => $ligne->type_appui,
                'resultat' => $donnees['resultat'],
                'moyenne' => $formation ? null : ($donnees['moyenne'] ?? null),
                'insertion' => $formation && $donnees['resultat'] === 'achevee' ? $donnees['insertion'] : null,
                'orientation' => $orientation,
                'prochaine_annee' => $prochaine,
                'details_prochaine_annee' => $details,
                'a_reconduire' => (bool) $donnees['a_reconduire'],
                'observation' => $donnees['observation'] ?? null,
                // Scolarité de la fiche avant le premier suivi : la mise à jour de la fiche repart toujours de là
                'fiche_avant' => $existant?->fiche_avant ?? FicheApresSuivi::photographier($ligne->oev),
                'renseigne_par' => $request->user()->id,
            ]);

            // La fiche de l'OEV prend la scolarité ou la formation de l'année prochaine
            return [$suivi, $this->fiche->appliquer($suivi->setRelation('oev', $ligne->oev))];
        });

        JournalActivite::consigner('parrainage.suivi', $ligne->oev, "Suivi {$suivi->annee} de l’OEV {$ligne->oev->reference()} : {$suivi->libelleResultat()}", array_filter([
            'session' => $ligne->session_parrainage_id,
            'a_reconduire' => $suivi->a_reconduire,
            'fiche_mise_a_jour' => $miseAJour,
        ], fn ($v) => $v !== null), $request->user());

        return back()->with('success', "Suivi de {$ligne->oev->nomComplet()} enregistré."
            . ($miseAJour ? " Fiche de l’OEV mise à jour : {$miseAJour}." : ' Un suivi plus récent existe : la fiche de l’OEV n’est pas modifiée.'));
    }
}
