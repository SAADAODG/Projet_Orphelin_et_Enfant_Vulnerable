<?php

namespace App\Models;

use App\Services\Parrainage\ScoreBesoin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Élément d'une liste de choix de la fiche OEV, paramétrable dans Paramètres (groupes de population,
 * lien du tuteur, lieux de vie, situations de vulnérabilité).
 *
 * Le code est enregistré dans le dossier, le libellé est affiché. Les éléments « système » sont
 * utilisés par les règles de l'application (ex. « mere », « pdi », « orphelin », « autre ») : leur
 * libellé reste modifiable, mais ils ne peuvent être ni supprimés ni désactivés.
 */
class ListeReference extends Model
{
    protected $table = 'listes_reference';

    protected $fillable = ['liste', 'code', 'libelle', 'ordre', 'actif', 'systeme'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'systeme' => 'boolean', 'ordre' => 'integer'];
    }

    /** Listes paramétrables : slug => titre, champ du dossier OEV, icône, valeurs initiales et codes système. */
    public static function listes(): array
    {
        return [
            'groupes-population' => [
                'titre' => 'Groupes de population',
                'champ' => 'groupe_population',
                'icone' => 'bi-people',
                'aide' => 'Groupe de population de l’enfant (étape 1). Pour les groupes mobiles (PDI, réfugié…), le lieu de provenance est demandé.',
                'defauts' => Oev::GROUPES_POPULATION,
                'systeme' => Oev::GROUPES_MOBILES,
            ],
            'liens-tuteur' => [
                'titre' => 'Qui s’occupe de l’enfant ?',
                'champ' => 'tuteur_lien',
                'icone' => 'bi-person-heart',
                'aide' => 'Lien du parent ou tuteur avec l’enfant (étape 2).',
                'defauts' => Oev::LIENS_TUTEUR,
                'systeme' => ['parents', 'mere', 'pere', 'autre_parent'],
            ],
            'lieux-de-vie' => [
                'titre' => 'Conditions de vie',
                'champ' => 'lieu_de_vie',
                'icone' => 'bi-house-heart',
                'aide' => 'Réponses à « Où vit l’enfant ? » (étape 3, conditions de vie).',
                'defauts' => Oev::LIEUX_DE_VIE,
                'systeme' => ['autre'],
            ],
            'vulnerabilites' => [
                'titre' => 'Situations de vulnérabilité',
                'champ' => 'vulnerabilites',
                'icone' => 'bi-exclamation-diamond',
                'aide' => 'Situations de vulnérabilité à cocher (étape 3). Certaines sont cochées d’office ou comptent dans le score de besoin du parrainage.',
                'defauts' => Oev::VULNERABILITES,
                'systeme' => ['orphelin', 'handicap', 'sans_acte_naissance', 'grossesse', 'autre', ...ScoreBesoin::VULNERABILITES_GRAVES],
            ],
        ];
    }

    /** Valeurs initiales de toutes les listes (migration et seeders). */
    public static function valeursInitiales(): array
    {
        $lignes = [];
        foreach (self::listes() as $liste => $config) {
            $ordre = 0;
            foreach ($config['defauts'] as $code => $libelle) {
                $lignes[] = [
                    'liste' => $liste, 'code' => $code, 'libelle' => $libelle, 'ordre' => ++$ordre,
                    'actif' => true, 'systeme' => in_array($code, $config['systeme'], true),
                ];
            }
        }

        return $lignes;
    }

    /**
     * Choix d'une liste, code => libellé, dans l'ordre défini.
     * $actifs : seulement les éléments actifs (formulaires, validation) ; sinon tous (affichage, filtres, exports).
     */
    public static function options(string $liste, bool $actifs = false): array
    {
        return self::tous()
            ->where('liste', $liste)
            ->when($actifs, fn ($elements) => $elements->where('actif', true))
            ->pluck('libelle', 'code')
            ->all();
    }

    /** Tous les éléments, lus une fois par requête (vidés à chaque modification). */
    private static function tous(): Collection
    {
        if (! app()->bound('listes-reference')) {
            app()->instance('listes-reference', self::orderBy('ordre')->orderBy('id')->get(['liste', 'code', 'libelle', 'actif']));
        }

        return app('listes-reference');
    }

    protected static function booted(): void
    {
        $vider = fn () => app()->forgetInstance('listes-reference');
        static::saved($vider);
        static::deleted($vider);
    }

    /** Nombre de dossiers OEV (même supprimés logiquement) qui ont choisi cet élément. */
    public function nombreDeDossiers(): int
    {
        $champ = self::listes()[$this->liste]['champ'];
        $requete = Oev::withTrashed();

        return $champ === 'vulnerabilites'
            ? $requete->whereRaw('CAST(vulnerabilites AS TEXT) LIKE ?', ['%"' . $this->code . '"%'])->count()
            : $requete->where($champ, $this->code)->count();
    }
}
