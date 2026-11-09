<?php

namespace App\Services\Parrainage;

use App\Models\AppuiPartenaire;
use App\Models\BeneficiaireSession;
use App\Models\NatureAppui;
use App\Models\Oev;
use App\Models\Province;
use App\Models\SessionParrainage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Critères d'éligibilité du module « Sélection » (requêtes, rien n'est chargé) : bénéficiaires concernés par
 * les natures de la session, pas déjà financés par une autre session de l'année, filtres de recherche et
 * ordre d'urgence. Utilisés par SelectionParrainage et SourceSelectionParrainage.
 */
final class CriteresSelection
{
    /**
     * Bénéficiaires pas encore choisis pour la session, avec leur score, filtrés et triés par urgence.
     *
     * Chaque filtre accepte une valeur ou une liste de valeurs (« l'une ou l'autre ») : plusieurs régions,
     * plusieurs vulnérabilités… Localité : « regions » (régions entières) et « provinces » (seulement ces
     * provinces dans leur région) ; une région dont des provinces sont cochées est limitée à ces provinces.
     */
    public function recherche(SessionParrainage $session, array $f): Builder
    {
        $liste = fn (string ...$cles) => collect($cles)->flatMap(fn ($cle) => (array) ($f[$cle] ?? []))
            ->filter(fn ($v) => $v !== null && $v !== '')->unique()->values()->all();
        $handicap = $liste('handicap');

        return Oev::beneficiaires()
            ->whereNotIn('oevs.id', BeneficiaireSession::where('session_parrainage_id', $session->id)->select('oev_id'))
            // Entièrement financés par une autre session de l'année : plus proposés (ni éligibles ni affichés)
            ->whereNotIn('oevs.id', $this->entierementFinances($session))
            ->select('oevs.*')
            // Score d'urgence enregistré sur la fiche (ScoreBesoinObserver), indexé
            ->selectRaw('oevs.score_besoin as score')
            ->when($liste('type'), fn ($q, $types) => $this->concernesPar($q, $types))
            ->tap(fn ($q) => $this->dansLesLocalites($q, $liste('regions', 'region_id'), $liste('provinces', 'province_id'), $liste('commune_id')))
            ->when($liste('sexe'), fn ($q, $v) => $q->whereIn('oevs.sexe', $v))
            ->when(isset($f['age_min']), fn ($q) => $q->whereDate('oevs.date_naissance', '<=', now()->subYears((int) $f['age_min'])))
            ->when(isset($f['age_max']), fn ($q) => $q->whereDate('oevs.date_naissance', '>', now()->subYears((int) $f['age_max'] + 1)))
            ->when($liste('statut'), fn ($q, $v) => $q->whereIn('oevs.statut', $v))
            // Au moins une des vulnérabilités choisies (liste JSON lue comme texte : PostgreSQL et SQLite)
            ->when($liste('vulnerabilite'), fn ($q, $codes) => $q->where(function ($q) use ($codes) {
                foreach ($codes as $code) {
                    $q->orWhereRaw('CAST(oevs.vulnerabilites AS TEXT) LIKE ?', ['%"' . $code . '"%']);
                }
            }))
            // Oui et non cochés ensemble : aucun filtre
            ->when(count($handicap) === 1, fn ($q) => $q->where('oevs.handicap', (bool) $handicap[0]))
            ->when($liste('niveau_priorite'), fn ($q, $v) => $q->whereIn('oevs.niveau_priorite', $v))
            ->when($liste('niveau_etude'), fn ($q, $v) => $q->whereIn('oevs.niveau_etude', $v))
            ->when($liste('groupe_population'), fn ($q, $v) => $q->whereIn('oevs.groupe_population', $v))
            ->when($liste('scolarite'), fn ($q, $situations) => $q->where(function ($q) use ($situations) {
                foreach ($situations as $situation) {
                    $q->orWhere(fn ($q) => match ($situation) {
                        SelectionParrainage::SCOLARISE => $this->scolarises($q),
                        SelectionParrainage::A_SCOLARISER => $this->aScolariser($q),
                        SelectionParrainage::EN_FORMATION => $this->enFormation($q),
                        default => $q,
                    });
                }
            }))
            ->when($f['q'] ?? null, fn ($q, $terme) => $q->where(fn ($q) => $q
                ->whereLike('oevs.code', "%{$terme}%")->orWhereLike('oevs.nom', "%{$terme}%")
                ->orWhereLike('oevs.prenom', "%{$terme}%")->orWhereLike('oevs.etablissement_actuel', "%{$terme}%")))
            // Jamais financés cette année d'abord, puis les compléments (frais qui dépassaient le plafond)
            ->tap(function (Builder $q) use ($session) {
                $complements = $this->financementsAnterieurs($session)->select('oev_id')->toBase();
                $q->orderByRaw('case when oevs.id in (' . $complements->toSql() . ') then 1 else 0 end', $complements->getBindings());
            })
            ->orderByDesc('oevs.score_besoin')
            ->orderBy('oevs.date_naissance')
            ->orderBy('oevs.id');
    }

    /**
     * Localités choisies : régions entières, ou seulement certaines provinces de ces régions.
     * Une province cochée limite sa région à cette province ; sans choix, aucune restriction.
     */
    private function dansLesLocalites(Builder $requete, array $regions, array $provinces, array $communes = []): Builder
    {
        $provinces = Province::whereIn('id', $provinces)->get(['id', 'region_id']);
        $regionsEntieres = array_diff(array_map('intval', $regions), $provinces->pluck('region_id')->all());

        return $requete
            ->when($regionsEntieres || $provinces->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('oevs.region_id', $regionsEntieres)
                ->orWhereIn('oevs.province_id', $provinces->pluck('id'))))
            ->when($communes, fn ($q) => $q->whereIn('oevs.commune_id', $communes));
    }

    /** Lignes retenues par les autres sessions de la même année, pour les natures de cette session. */
    public function financementsAnterieurs(SessionParrainage $session): Builder
    {
        return BeneficiaireSession::retenus()
            ->whereIn('type_appui', $session->naturesCouvertes())
            ->whereHas('session', fn ($q) => $q->where('annee', $session->annee)->whereKeyNot($session->id));
    }

    /**
     * OEV dont les frais sont entièrement couverts par l'État cette année (autres sessions) : le total versé
     * atteint les frais, ou les frais n'étaient pas connus. Ils ne sont plus proposés.
     */
    private function entierementFinances(SessionParrainage $session): Builder
    {
        return $this->financementsAnterieurs($session)
            ->select('oev_id')
            ->groupBy('oev_id')
            ->havingRaw('sum(montant_retenu) >= max(coalesce(frais_reels, 0)) or sum(case when frais_reels is null then 1 else 0 end) > 0');
    }

    /** Éligibles sans les filtres de recherche (aussi ceux déjà choisis pour la session). */
    public function baseEligibles(SessionParrainage $session): Builder
    {
        $natures = $session->naturesCouvertes();

        // Une autre session de l'année a déjà financé l'OEV pour la même nature : seul celui dont les frais
        // dépassaient le plafond (financé en partie) reste éligible, pour le complément (pas de double financement)
        $requete = $this->concernesPar(Oev::beneficiaires(), $natures)
            ->whereNotIn('oevs.id', $this->entierementFinances($session));

        // RG-07 : OEV déjà appuyés par un partenaire pour la même nature et la même année
        if ($session->exclure_deja_appuyes) {
            $requete->whereNotIn('oevs.id', AppuiPartenaire::where('annee', $session->annee)
                ->whereIn('nature_appui_id', NatureAppui::whereIn('code', $natures)->select('id'))
                ->select('oev_id'));
        }

        return $requete;
    }

    /**
     * OEV concernés par au moins une des natures : scolarisés ou d'âge scolaire à inscrire (scolaire),
     * en formation professionnelle en cours (formation).
     */
    private function concernesPar(Builder $requete, array $natures): Builder
    {
        return $requete->where(function ($q) use ($natures) {
            if (in_array(SessionParrainage::TYPE_SCOLAIRE, $natures, true)) {
                $q->orWhere(fn ($q) => $this->scolarises($q))->orWhere(fn ($q) => $this->aScolariser($q));
            }
            if (in_array(SessionParrainage::TYPE_FORMATION, $natures, true)) {
                $q->orWhere(fn ($q) => $this->enFormation($q));
            }
        });
    }

    private function scolarises(Builder $q): Builder
    {
        return $q->where('oevs.situation_scolaire', 'scolarise');
    }

    private function enFormation(Builder $q): Builder
    {
        return $q->where('oevs.formation_professionnelle', true)->where('oevs.formation_etat', 'en_cours');
    }

    /** Non scolarisé (ou déscolarisé, foyer coranique…), pas en formation, et d'âge scolaire. */
    private function aScolariser(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('oevs.situation_scolaire')->orWhere('oevs.situation_scolaire', '!=', 'scolarise'))
            ->where(fn ($q) => $q->whereNull('oevs.formation_professionnelle')->orWhere('oevs.formation_professionnelle', false)
                ->orWhereNull('oevs.formation_etat')->orWhere('oevs.formation_etat', '!=', 'en_cours'))
            ->whereDate('oevs.date_naissance', '<=', now()->subYears(SelectionParrainage::AGE_SCOLAIRE_MIN))
            ->whereDate('oevs.date_naissance', '>', now()->subYears(SelectionParrainage::AGE_SCOLAIRE_MAX + 1));
    }
}
