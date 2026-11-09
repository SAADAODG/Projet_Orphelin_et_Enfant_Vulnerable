<?php

namespace App\Services\Parrainage;

use App\Models\BeneficiaireSession;
use App\Models\Ecole;
use App\Models\SessionParrainage;
use App\Models\SuiviParrainage;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Données du module « Sélection » fournies aux modules Sessions, Parrains et Pilotage (contrat SourceSelection),
 * lues dans la base : éligibles par région, liste définitive, montants engagés, établissements payés,
 * résultats scolaires du module « Suivi des parrainés » (SuiviParrainage).
 */
final class SourceSelectionParrainage implements SourceSelectionEnBase
{
    public function __construct(private readonly CriteresSelection $criteres)
    {
    }

    public function disponible(): bool
    {
        return true;
    }

    public function eligiblesParRegion(SessionParrainage $session): array
    {
        return $this->criteres->baseEligibles($session)
            ->whereNotNull('oevs.region_id')
            ->selectRaw('oevs.region_id, count(*) as total')
            ->groupBy('oevs.region_id')
            ->pluck('total', 'region_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** La liste n'est définitive qu'une fois la session validée : avant, montant et liste sont « non disponibles ». */
    private function listeArretee(SessionParrainage $session): bool
    {
        return $session->etat !== SessionParrainage::ETAT_EN_COURS;
    }

    public function montantEngage(SessionParrainage $session): ?int
    {
        return $this->listeArretee($session) ? (int) $session->beneficiaires()->retenus()->sum('montant_retenu') : null;
    }

    public function listeDefinitive(SessionParrainage $session): Collection
    {
        if (! $this->listeArretee($session)) {
            return collect();
        }

        // Lignes brutes (pas de modèles) : la liste peut compter des dizaines de milliers de bénéficiaires
        return $session->beneficiaires()->retenus()->orderByDesc('score')->orderBy('id')->toBase()
            ->get(['oev_id', 'type_appui', 'etablissement_id', 'frais_reels', 'montant_retenu'])
            ->map(fn ($b) => [
                'oev_id' => (int) $b->oev_id,
                'type_appui' => $b->type_appui,
                'etablissement_id' => self::entier($b->etablissement_id),
                'frais_reels' => self::entier($b->frais_reels ?? $b->montant_retenu),
                'montant_retenu' => self::entier($b->montant_retenu),
            ]);
    }

    /** Même conversion que le cast « integer » du modèle : null reste null. */
    private static function entier(mixed $valeur): ?int
    {
        return $valeur === null ? null : (int) $valeur;
    }

    /** Établissements payés par au moins une session (écoles et centres de formation du référentiel). */
    public function etablissements(): Collection
    {
        return Ecole::query()
            ->whereIn('ecoles.id', BeneficiaireSession::whereNotNull('etablissement_id')->select('etablissement_id'))
            ->join('communes as c', 'c.id', '=', 'ecoles.commune_id')
            ->join('provinces as p', 'p.id', '=', 'c.province_id')
            ->toBase()
            ->get(['ecoles.id', 'ecoles.nom', 'ecoles.nature', 'ecoles.statut', 'p.region_id', 'c.province_id', 'ecoles.commune_id', 'ecoles.banque', 'ecoles.rib'])
            ->mapWithKeys(fn ($e) => [(int) $e->id => [
                'id' => (int) $e->id,
                'nom' => $e->nom,
                'type' => trim((Ecole::NATURES[$e->nature] ?? '') . ($e->statut ? ' · ' . (Ecole::STATUTS[$e->statut] ?? $e->statut) : '')),
                'region_id' => (int) $e->region_id,
                'province_id' => (int) $e->province_id,
                'commune_id' => (int) $e->commune_id,
                'banque' => $e->banque,
                'rib' => $e->rib === '' ? null : $e->rib,
            ]]);
    }

    public function naturesAppuiEtat(int $oevId, string $annee): array
    {
        return BeneficiaireSession::retenus()
            ->where('oev_id', $oevId)
            ->whereHas('session', fn ($q) => $q->where('annee', $annee)->where('etat', '!=', SessionParrainage::ETAT_EN_COURS))
            ->distinct()
            ->pluck('type_appui')
            ->all();
    }

    public function requeteListeDefinitive(Collection $sessions): QueryBuilder
    {
        $arretees = $sessions->filter(fn (SessionParrainage $s) => $this->listeArretee($s))->pluck('id')->all();

        return BeneficiaireSession::retenus()->whereIn('session_parrainage_id', $arretees)->toBase()
            ->select(['oev_id', 'type_appui', 'montant_retenu']);
    }

    public function requeteResultatsScolaires(string $annee): QueryBuilder
    {
        return SuiviParrainage::where('annee', $annee)->where('type_appui', SessionParrainage::TYPE_SCOLAIRE)->toBase()->select(['oev_id', 'resultat']);
    }

    /** Résultats scolaires du module « Suivi des parrainés » (appui scolaire seulement). */
    public function resultatsScolaires(string $annee): Collection
    {
        return SuiviParrainage::where('annee', $annee)
            ->where('type_appui', SessionParrainage::TYPE_SCOLAIRE)
            ->get(['oev_id', 'annee', 'resultat'])
            ->map(fn (SuiviParrainage $s) => ['oev_id' => $s->oev_id, 'annee' => $s->annee, 'resultat' => $s->resultat])
            ->values();
    }
}
