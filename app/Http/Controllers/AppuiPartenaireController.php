<?php

namespace App\Http\Controllers;

use App\Models\AppuiPartenaire;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Parrain;
use App\Models\User;
use App\Services\Parrainage\ParrainageService;
use App\Support\Montant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module « Parrains », vue « Appuis enregistrés » : un parrain appuie un OEV pendant une année.
 * Le DP enregistre les appuis des OEV de sa province, le niveau central ceux de tout le pays ;
 * chacun ne voit que les appuis de sa zone. Contrôle RG-01 à la saisie et à l'import.
 */
class AppuiPartenaireController extends Controller
{
    /** Colonnes du modèle d'import, dans l'ordre. */
    private const COLONNES_IMPORT = [
        'code_oev' => 'Code OEV (ex : OEV-2026-0001)',
        'parrain_id' => 'N° du parrain (feuille « Parrains »)',
        'annee' => 'Année scolaire (ex : 2026-2027)',
        'nature' => 'Code nature (feuille « Natures »)',
        'nature_precision' => 'Précision (nature « autre »)',
        'montant' => 'Montant FCFA (vide si en nature)',
        'date_debut' => 'Date de début (jj/mm/aaaa)',
        'date_fin' => 'Date de fin (jj/mm/aaaa)',
        'observations' => 'Observations',
        'motif_doublon' => 'Motif si l’OEV a déjà un appui de même nature cette année',
    ];

    public function __construct(private readonly ParrainageService $parrainage)
    {
    }

    public function index(Request $request): View
    {
        $filtres = [
            'q' => trim((string) $request->query('q', '')),
            'annee' => (string) $request->query('annee', ''),
            'nature' => $request->integer('nature') ?: null,
            'parrain' => $request->integer('parrain') ?: null,
        ];

        $appuis = AppuiPartenaire::with(['oev.commune', 'parrain', 'nature'])
            ->dansLePerimetreDe($request->user())
            ->when($filtres['q'] !== '', fn ($q) => $q->whereHas('oev', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$filtres['q']}%")
                ->orWhere('nom', 'like', "%{$filtres['q']}%")
                ->orWhere('prenom', 'like', "%{$filtres['q']}%"))))
            ->when($filtres['annee'] !== '', fn ($q) => $q->where('annee', $filtres['annee']))
            ->when($filtres['nature'], fn ($q) => $q->where('nature_appui_id', $filtres['nature']))
            ->when($filtres['parrain'], fn ($q) => $q->where('parrain_id', $filtres['parrain']))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('parrainage.appuis.index', [
            'appuis' => $appuis,
            'filtres' => $filtres,
            'annees' => AppuiPartenaire::distinct()->orderByDesc('annee')->pluck('annee'),
            'natures' => NatureAppui::actives()->get(),
            'parrains' => Parrain::orderBy('nom')->pluck('nom', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $oev = $request->integer('oev') ? $this->oevsSaisissables($request->user())->find($request->integer('oev')) : null;

        return view('parrainage.appuis.create', $this->donneesFormulaire(new AppuiPartenaire([
            'annee' => SessionParrainageController::anneeScolaireCourante(),
            'oev_id' => $oev?->id,
        ])->setRelation('oev', $oev)));
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);
        if ($donnees instanceof RedirectResponse) {
            return $donnees;
        }

        $appui = DB::transaction(function () use ($request, $donnees) {
            $appui = AppuiPartenaire::create($donnees + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $this->tracer('appui_partenaire.creation', $appui, 'Enregistrement');

            return $appui;
        });

        return redirect()->route('parrainage.appuis.index')
            ->with('success', "Appui enregistré pour {$appui->oev->nomComplet()} ({$appui->oev->reference()}).");
    }

    public function edit(Request $request, AppuiPartenaire $appui): View
    {
        $this->verifierAcces($request->user(), $appui);

        return view('parrainage.appuis.edit', $this->donneesFormulaire($appui->load('oev')));
    }

    public function update(Request $request, AppuiPartenaire $appui): RedirectResponse
    {
        $this->verifierAcces($request->user(), $appui);
        $donnees = $this->valider($request, $appui);
        if ($donnees instanceof RedirectResponse) {
            return $donnees;
        }

        DB::transaction(function () use ($request, $appui, $donnees) {
            $appui->update($donnees + ['updated_by' => $request->user()->id]);
            $this->tracer('appui_partenaire.modification', $appui, 'Modification', [
                'champs' => array_values(array_diff(array_keys($appui->getChanges()), ['updated_at', 'updated_by'])),
            ]);
        });

        return redirect()->route('parrainage.appuis.index')->with('success', 'Appui mis à jour.');
    }

    /** Recherche d'un OEV intégré de la zone de l'utilisateur, par code, nom ou prénom (liste du formulaire). */
    public function rechercheOev(Request $request): JsonResponse
    {
        $terme = trim((string) $request->query('q', ''));
        if (mb_strlen($terme) < 2) {
            return response()->json([]);
        }

        // Chaque mot doit se retrouver dans le code, le nom ou le prénom (« ouedraogo awa »)
        $requete = $this->oevsSaisissables($request->user())->with('commune');
        foreach (preg_split('/\s+/', $terme) as $mot) {
            $requete->where(fn ($q) => $q
                ->where('code', 'like', "%{$mot}%")
                ->orWhere('nom', 'like', "%{$mot}%")
                ->orWhere('prenom', 'like', "%{$mot}%"));
        }

        $oevs = $requete
            ->orderBy('nom')
            ->limit(15)
            ->get();

        return response()->json($oevs->map(fn (Oev $oev) => [
            'id' => $oev->id,
            'libelle' => $oev->reference() . ' — ' . $oev->nomComplet() . ($oev->commune ? " ({$oev->commune->nom})" : ''),
        ]));
    }

    /** Modèle Excel : feuille « Appuis » à remplir, plus les référentiels (parrains, natures). */
    public function modele(): StreamedResponse
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet()->setTitle('Appuis');
        $feuille->fromArray([array_keys(self::COLONNES_IMPORT), array_values(self::COLONNES_IMPORT)]);
        $feuille->getStyle('A1:J1')->getFont()->setBold(true);
        $feuille->getStyle('A2:J2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        foreach (range('A', 'J') as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }

        $parrains = $classeur->createSheet()->setTitle('Parrains');
        $parrains->fromArray([['N°', 'Nom', 'Type']]);
        $parrains->fromArray(Parrain::actifs()->orderBy('nom')->get()->map(fn (Parrain $p) => [$p->id, $p->nom, $p->libelleType()])->all(), null, 'A2');

        $natures = $classeur->createSheet()->setTitle('Natures');
        $natures->fromArray([['Code', 'Libellé']]);
        $natures->fromArray(NatureAppui::actives()->get()->map(fn (NatureAppui $n) => [$n->code, $n->libelle])->all(), null, 'A2');

        foreach ([$parrains, $natures] as $referentiel) {
            $referentiel->getStyle('A1:C1')->getFont()->setBold(true);
            foreach (['A', 'B', 'C'] as $colonne) {
                $referentiel->getColumnDimension($colonne)->setAutoSize(true);
            }
        }
        $classeur->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($classeur) {
            (new Xlsx($classeur))->save('php://output');
        }, 'modele-import-appuis.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function importForm(): View
    {
        return view('parrainage.appuis.import', ['rapport' => session('rapportImport')]);
    }

    /**
     * Import Excel : chaque ligne est contrôlée comme une saisie unitaire (OEV de la zone, RG-01…).
     * Les lignes valides sont enregistrées, les autres listées dans le rapport avec leur motif de rejet.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(
            ['fichier' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']],
            ['fichier.required' => 'Choisissez le fichier Excel à importer.', 'fichier.mimes' => 'Le fichier doit être au format Excel (.xlsx ou .xls).'],
        );

        try {
            $lignes = IOFactory::load($request->file('fichier')->getRealPath())->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return back()->withErrors(['fichier' => 'Le fichier n’a pas pu être lu : vérifiez qu’il s’agit bien du modèle Excel.']);
        }

        $colonnes = array_keys(self::COLONNES_IMPORT);
        $importes = 0;
        $rejets = [];

        foreach (array_slice($lignes, 2, null, true) as $index => $cellules) {
            $numero = $index + 1;
            $ligne = array_combine($colonnes, array_pad(array_slice(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $cellules), 0, count($colonnes)), count($colonnes), null));
            if (collect($ligne)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $resultat = $this->importerLigne($request->user(), $ligne);
            if ($resultat === true) {
                $importes++;
            } else {
                $rejets[] = ['ligne' => $numero, 'code_oev' => (string) $ligne['code_oev'], 'motifs' => $resultat];
            }
        }

        JournalActivite::consigner('appui_partenaire.import', null, "Import Excel des appuis : {$importes} ligne(s) importée(s), " . count($rejets) . ' rejetée(s)', [
            'fichier' => $request->file('fichier')->getClientOriginalName(),
            'importees' => $importes,
            'rejetees' => count($rejets),
        ]);

        return redirect()->route('parrainage.appuis.import')
            ->with('rapportImport', ['importes' => $importes, 'rejets' => $rejets])
            ->with($importes ? 'success' : 'error', "{$importes} appui(s) importé(s), " . count($rejets) . ' ligne(s) rejetée(s).');
    }

    /** @return true|array<int, string> true si la ligne est enregistrée, sinon les motifs de rejet */
    private function importerLigne(User $utilisateur, array $ligne): true|array
    {
        $oev = $ligne['code_oev'] ? $this->oevsSaisissables($utilisateur)->where('code', $ligne['code_oev'])->first() : null;
        $nature = $ligne['nature'] ? NatureAppui::where('code', $ligne['nature'])->where('actif', true)->first() : null;

        $donnees = [
            'oev_id' => $oev?->id,
            'parrain_id' => is_numeric($ligne['parrain_id']) ? (int) $ligne['parrain_id'] : $ligne['parrain_id'],
            'annee' => (string) $ligne['annee'],
            'nature_appui_id' => $nature?->id,
            'nature_precision' => $ligne['nature_precision'] ?: null,
            'montant' => Montant::normaliserSaisie(is_numeric($ligne['montant']) ? (string) (int) $ligne['montant'] : $ligne['montant']),
            'date_debut' => self::dateExcel($ligne['date_debut']),
            'date_fin' => self::dateExcel($ligne['date_fin']),
            'observations' => $ligne['observations'] ?: null,
        ];

        $validateur = Validator::make($donnees, $this->regles($nature), [
            'oev_id.required' => $ligne['code_oev']
                ? "OEV « {$ligne['code_oev']} » introuvable, non intégré ou hors de votre zone."
                : 'Code OEV manquant.',
            'nature_appui_id.required' => $ligne['nature'] ? "Nature « {$ligne['nature']} » inconnue." : 'Code nature manquant.',
        ] + $this->messages());
        if ($validateur->fails()) {
            return $validateur->errors()->all();
        }

        $doublons = $this->parrainage->doublonsAppui($oev->id, $donnees['annee'], $nature);
        $motif = trim((string) $ligne['motif_doublon']);
        if ($doublons && $motif === '') {
            return ['Appui de même nature déjà enregistré cette année (' . implode(' ; ', $doublons) . ') : indiquez un motif dans la colonne « motif_doublon » pour confirmer.'];
        }

        DB::transaction(function () use ($utilisateur, $donnees, $doublons, $motif) {
            $appui = AppuiPartenaire::create($donnees + [
                'motif_doublon' => $doublons ? $motif : null,
                'created_by' => $utilisateur->id,
                'updated_by' => $utilisateur->id,
            ]);
            $this->tracer('appui_partenaire.creation', $appui, 'Import', [], $utilisateur);
        });

        return true;
    }

    /**
     * Validation d'une saisie unitaire. Si l'OEV a déjà un appui de même nature cette année (RG-01),
     * le formulaire revient avec un avertissement : l'agent confirme en indiquant un motif.
     *
     * @return array|RedirectResponse données à enregistrer, ou retour au formulaire pour confirmation
     */
    private function valider(Request $request, ?AppuiPartenaire $appui = null): array|RedirectResponse
    {
        $request->merge(['montant' => Montant::normaliserSaisie($request->input('montant'))]);
        $nature = NatureAppui::find($request->integer('nature_appui_id'));

        $regles = $this->regles($nature, $appui);
        // L'OEV choisi doit être intégré et dans la zone de l'utilisateur
        $regles['oev_id'][] = Rule::in($this->oevsSaisissables($request->user())->whereKey($request->integer('oev_id'))->pluck('id'));
        $regles['motif_doublon'] = ['nullable', 'string', 'max:2000'];

        $donnees = $request->validate($regles, $this->messages());

        $doublons = $this->parrainage->doublonsAppui($donnees['oev_id'], $donnees['annee'], $nature, $appui?->id);
        $motif = trim((string) ($donnees['motif_doublon'] ?? ''));

        if ($doublons && (! $request->boolean('confirmer_doublon') || $motif === '')) {
            return back()->withInput()->with('doublons', $doublons)->withErrors(
                $request->boolean('confirmer_doublon') ? ['motif_doublon' => 'Indiquez le motif de ce deuxième appui de même nature.'] : [],
            );
        }

        return collect($donnees)->except('motif_doublon')->all() + [
            'nature_precision' => $nature?->code === NatureAppui::AUTRE ? ($donnees['nature_precision'] ?? null) : null,
            'motif_doublon' => $doublons ? $motif : null,
        ];
    }

    private function regles(?NatureAppui $nature, ?AppuiPartenaire $appui = null): array
    {
        return [
            'oev_id' => ['required', 'integer'],
            // Un parrain désactivé ne reçoit plus de nouvel appui (sauf l'appui déjà enregistré qu'on modifie)
            'parrain_id' => ['required', 'integer', Rule::exists('parrains', 'id')->where(fn ($q) => $q
                ->where('actif', true)
                ->when($appui, fn ($q) => $q->orWhere('id', $appui->parrain_id)))],
            'annee' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribut, $valeur, \Closure $echec) {
                [$debut, $fin] = array_map('intval', explode('-', (string) $valeur) + [1 => 0]);
                if ($fin !== $debut + 1) {
                    $echec('L’année scolaire doit couvrir deux années consécutives (ex : 2026-2027).');
                }
            }],
            'nature_appui_id' => ['required', 'integer', Rule::exists('natures_appui', 'id')->where('actif', true)],
            'nature_precision' => [Rule::requiredIf($nature?->code === NatureAppui::AUTRE), 'nullable', 'string', 'max:150'],
            'montant' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function messages(): array
    {
        return [
            'oev_id.required' => 'Choisissez l’OEV appuyé.',
            'oev_id.in' => 'Cet OEV n’est pas intégré ou n’est pas dans votre zone.',
            'parrain_id.required' => 'Choisissez le parrain.',
            'parrain_id.exists' => 'Parrain inconnu ou désactivé.',
            'annee.regex' => 'L’année scolaire s’écrit sous la forme 2026-2027.',
            'nature_appui_id.required' => 'Choisissez la nature de l’appui.',
            'nature_precision.required' => 'Précisez la nature de l’appui.',
            'montant.integer' => 'Le montant doit être un nombre entier de FCFA.',
            'date_fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
        ];
    }

    /** OEV pour lesquels l'utilisateur peut enregistrer un appui : intégrés, dans sa zone. */
    private function oevsSaisissables(User $utilisateur)
    {
        return Oev::etat(Oev::ETAT_INTEGRE)->dansLePerimetreDe($utilisateur);
    }

    private function verifierAcces(User $utilisateur, AppuiPartenaire $appui): void
    {
        abort_unless(AppuiPartenaire::whereKey($appui->id)->dansLePerimetreDe($utilisateur)->exists(), 403);
    }

    private function donneesFormulaire(AppuiPartenaire $appui): array
    {
        return [
            'appui' => $appui,
            'parrains' => Parrain::query()
                ->where(fn ($q) => $q->where('actif', true)->when($appui->parrain_id, fn ($q) => $q->orWhere('id', $appui->parrain_id)))
                ->orderBy('nom')
                ->get(),
            'natures' => NatureAppui::actives()->get(),
            'oevChoisi' => old('oev_id')
                ? Oev::find(old('oev_id'))
                : $appui->oev,
        ];
    }

    private function tracer(string $action, AppuiPartenaire $appui, string $origine, array $details = [], ?User $utilisateur = null): void
    {
        $appui->loadMissing(['oev', 'parrain', 'nature']);
        JournalActivite::consigner($action, $appui, sprintf(
            '%s — appui « %s » de %s à %s (%s), %s',
            $origine,
            $appui->libelleNature(),
            $appui->parrain->nom,
            $appui->oev->nomComplet(),
            $appui->oev->reference(),
            $appui->annee,
        ), $details + array_filter(['motif_doublon' => $appui->motif_doublon]), $utilisateur);
    }

    /** Date d'une cellule Excel : nombre de série Excel ou texte jj/mm/aaaa ; invalide → renvoyée telle quelle (rejetée). */
    private static function dateExcel(mixed $valeur): mixed
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        if (is_numeric($valeur)) {
            return Carbon::instance(DateExcel::excelToDateTimeObject((float) $valeur))->toDateString();
        }
        try {
            return Carbon::createFromFormat('!d/m/Y', (string) $valeur)->toDateString();
        } catch (\Throwable) {
            return $valeur;
        }
    }
}
