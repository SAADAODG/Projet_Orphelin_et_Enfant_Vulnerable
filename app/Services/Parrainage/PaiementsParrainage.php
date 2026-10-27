<?php

namespace App\Services\Parrainage;

use App\Models\BeneficiaireSession;
use App\Models\JournalActivite;
use App\Models\Oev;
use App\Models\PaiementEtablissement;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Support\Montant;
use App\Support\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paiement d'une session de parrainage dont la liste est validée : les OEV retenus sont regroupés par
 * établissement payé (école ou centre de formation, avec sa banque et son RIB), puis par région. Le niveau
 * central enregistre qu'un établissement (ou toute une région) a reçu les fonds.
 *
 * L'établissement de chaque bénéficiaire suit la fiche de l'OEV tant qu'il n'a pas été payé : un OEV qui
 * change d'école avant le paiement est payé à sa nouvelle école ; après, il reste rattaché à l'école payée.
 */
class PaiementsParrainage
{
    /** États où les paiements se consultent et s'enregistrent. */
    public const ETATS_PAIEMENT = [SessionParrainage::ETAT_VALIDEE, SessionParrainage::ETAT_PAIEMENT, SessionParrainage::ETAT_CLOTUREE];

    public function __construct(private readonly ParrainageService $parrainage)
    {
    }

    /** Établissement payé pour un OEV selon le type d'appui : son centre de formation, ou son école. */
    public static function etablissementDe(Oev $oev, string $typeAppui): ?int
    {
        return $typeAppui === SessionParrainage::TYPE_FORMATION ? $oev->centre_formation_id : $oev->ecole_id;
    }

    /**
     * L'OEV a changé d'école ou de centre : ses lignes des sessions non clôturées suivent, sauf celles dont
     * l'établissement a déjà reçu les fonds de la session.
     */
    public function suivreChangementEtablissement(Oev $oev): void
    {
        BeneficiaireSession::retenus()
            ->where('oev_id', $oev->id)
            ->whereHas('session', fn ($q) => $q->where('etat', '!=', SessionParrainage::ETAT_CLOTUREE))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('paiements_etablissement as p')
                ->whereColumn('p.session_parrainage_id', 'beneficiaires_session.session_parrainage_id')
                ->whereColumn('p.etablissement_id', 'beneficiaires_session.etablissement_id'))
            ->get()
            ->each(fn (BeneficiaireSession $ligne) => $ligne->update(['etablissement_id' => self::etablissementDe($oev, $ligne->type_appui)]));
    }

    /** Lignes retenues de la session dans la zone de l'utilisateur. */
    private function lignes(SessionParrainage $session, Perimetre $zone): Builder
    {
        return BeneficiaireSession::retenus()
            ->where('beneficiaires_session.session_parrainage_id', $session->id)
            ->whereHas('oev', fn ($q) => $zone->appliquer($q));
    }

    /**
     * Une ligne par établissement payé (OEV sans établissement regroupés par région), avec la banque, le RIB,
     * le nombre d'OEV, le montant à verser et le paiement enregistré.
     */
    public function parEtablissement(SessionParrainage $session, Perimetre $zone, array $f = []): QueryBuilder
    {
        $regionEffective = 'COALESCE(pr.region_id, beneficiaires_session.region_id)';

        return $this->lignes($session, $zone)->toBase()
            ->leftJoin('ecoles as e', 'e.id', '=', 'beneficiaires_session.etablissement_id')
            ->leftJoin('communes as c', 'c.id', '=', 'e.commune_id')
            ->leftJoin('provinces as pr', 'pr.id', '=', 'c.province_id')
            ->leftJoin('paiements_etablissement as pa', fn ($j) => $j->on('pa.etablissement_id', '=', 'beneficiaires_session.etablissement_id')
                ->where('pa.session_parrainage_id', '=', $session->id))
            // Identifiants en entiers : comparés à une expression (COALESCE), un texte ne serait jamais égal sous SQLite
            ->when($f['region_id'] ?? null, fn ($q, $id) => $q->whereRaw("{$regionEffective} = ?", [(int) $id]))
            ->when($f['province_id'] ?? null, fn ($q, $id) => $q->where('pr.id', (int) $id))
            ->when($f['q'] ?? null, fn ($q, $terme) => $q->where(fn ($q) => $q->whereLike('e.nom', "%{$terme}%")->orWhereLike('e.rib', "%{$terme}%")))
            ->when(($f['paiement'] ?? null) === 'recu', fn ($q) => $q->whereNotNull('pa.id'))
            ->when(($f['paiement'] ?? null) === 'a_payer', fn ($q) => $q->whereNull('pa.id'))
            ->selectRaw("beneficiaires_session.etablissement_id, {$regionEffective} as region_id, pr.id as province_id,
                e.nom, e.nature, e.banque, e.rib, c.nom as commune,
                count(*) as nombre_oev, sum(beneficiaires_session.montant_retenu) as montant,
                pa.id as paiement_id, pa.recu_le, pa.reference, pa.montant as montant_paye, pa.nombre_oev as nombre_oev_paye")
            ->groupByRaw("beneficiaires_session.etablissement_id, {$regionEffective}, pr.id, e.nom, e.nature, e.banque, e.rib, c.nom,
                pa.id, pa.recu_le, pa.reference, pa.montant, pa.nombre_oev");
    }

    /**
     * Récapitulatif par région : nombre d'établissements, d'OEV, montant à verser et déjà reçu.
     *
     * @return Collection<int, object> region_id, nombre_etablissements, nombre_oev, montant, etablissements_payes, montant_paye, sans_etablissement
     */
    public function parRegion(SessionParrainage $session, Perimetre $zone, array $f = []): Collection
    {
        return DB::query()->fromSub($this->parEtablissement($session, $zone, $f), 'x')
            ->selectRaw('x.region_id,
                sum(case when x.etablissement_id is not null then 1 else 0 end) as nombre_etablissements,
                sum(x.nombre_oev) as nombre_oev, sum(x.montant) as montant,
                sum(case when x.paiement_id is not null then 1 else 0 end) as etablissements_payes,
                coalesce(sum(case when x.paiement_id is not null then x.montant else 0 end), 0) as montant_paye,
                sum(case when x.etablissement_id is null then x.nombre_oev else 0 end) as sans_etablissement')
            ->groupBy('x.region_id')
            ->get();
    }

    /** Totaux de la session dans la zone. */
    public function totaux(SessionParrainage $session, Perimetre $zone): object
    {
        return DB::query()->fromSub($this->parEtablissement($session, $zone), 'x')
            ->selectRaw('sum(case when x.etablissement_id is not null then 1 else 0 end) as nombre_etablissements,
                coalesce(sum(x.nombre_oev), 0) as nombre_oev, coalesce(sum(x.montant), 0) as montant,
                sum(case when x.paiement_id is not null then 1 else 0 end) as etablissements_payes,
                coalesce(sum(case when x.paiement_id is not null then x.montant else 0 end), 0) as montant_paye,
                coalesce(sum(case when x.etablissement_id is null then x.nombre_oev else 0 end), 0) as sans_etablissement,
                sum(case when x.etablissement_id is not null and (x.rib is null or x.rib = \'\') then 1 else 0 end) as rib_manquants')
            ->first();
    }

    /**
     * Enregistre que ces établissements ont reçu les fonds de la session (date, référence du virement) :
     * montant et nombre d'OEV du moment. Les établissements déjà payés sont ignorés. La première réception
     * fait passer une session « Validée » en « Paiement en cours ».
     *
     * @param  array<int, int>  $etablissementIds
     * @return int nombre d'établissements enregistrés
     */
    public function enregistrerReception(SessionParrainage $session, Perimetre $zone, array $etablissementIds, string $recuLe, ?string $reference, User $utilisateur, ?string $libelle = null): int
    {
        $lignes = DB::query()->fromSub($this->parEtablissement($session, $zone), 'x')
            ->whereIn('x.etablissement_id', $etablissementIds)
            ->whereNull('x.paiement_id')
            ->get();
        if ($lignes->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($session, $lignes, $recuLe, $reference, $utilisateur, $libelle) {
            foreach ($lignes as $ligne) {
                PaiementEtablissement::create([
                    'session_parrainage_id' => $session->id,
                    'etablissement_id' => $ligne->etablissement_id,
                    'montant' => (int) $ligne->montant,
                    'nombre_oev' => (int) $ligne->nombre_oev,
                    'recu_le' => $recuLe,
                    'reference' => $reference,
                    'saisi_par' => $utilisateur->id,
                ]);
            }
            $total = (int) $lignes->sum('montant');
            JournalActivite::consigner('session_parrainage.paiement', $session,
                ($libelle ?? ($lignes->count() === 1 ? "Fonds reçus par « {$lignes->first()->nom} »" : "Fonds reçus par {$lignes->count()} établissement(s)"))
                    . ' : ' . Montant::fcfa($total) . ' le ' . \Illuminate\Support\Carbon::parse($recuLe)->format('d/m/Y'),
                ['etablissements' => $lignes->pluck('etablissement_id')->all(), 'montant' => $total, 'reference' => $reference], $utilisateur);

            if ($session->etat === SessionParrainage::ETAT_VALIDEE) {
                $this->parrainage->changerEtatSession($session->id, SessionParrainage::ETAT_PAIEMENT, $utilisateur);
            }
        });

        return $lignes->count();
    }

    /** Annule l'enregistrement d'une réception (erreur de saisie). */
    public function annuler(PaiementEtablissement $paiement, User $utilisateur): void
    {
        $paiement->loadMissing(['session', 'etablissement']);
        $paiement->delete();
        JournalActivite::consigner('session_parrainage.paiement', $paiement->session,
            "Réception annulée pour « {$paiement->etablissement?->nom} » (" . Montant::fcfa($paiement->montant) . ')',
            ['etablissement' => $paiement->etablissement_id, 'montant' => $paiement->montant, 'reference' => $paiement->reference], $utilisateur);
    }
}
