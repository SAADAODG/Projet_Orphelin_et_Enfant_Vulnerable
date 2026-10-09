<?php

namespace App\Services\Parrainage;

use App\Models\AppuiPartenaire;
use App\Models\JournalActivite;
use App\Models\NatureAppui;
use App\Models\Region;
use App\Models\SessionParrainage;
use App\Models\User;
use App\Support\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Points d'échange du volet Parrainage (modules Sessions, Parrains, Pilotage) appelés par les
 * modules de Nakoulma (Sélection, Paiement, Suivi scolaire). Obtenir le service avec
 * app(ParrainageService::class).
 */
class ParrainageService
{
    public function __construct(private readonly SourceSelection $sourceSelection)
    {
    }

    /**
     * Paramètres d'une session, pour calculer la sélection.
     *
     * @param  int  $sessionId  Identifiant de la session de parrainage
     * @return array{
     *     id: int,
     *     annee: string,
     *     numero: int,
     *     etat: string,
     *     type_appui: string,
     *     enveloppe: int,
     *     plafond_beneficiaire: int,
     *     bloquer_depassement_enveloppe: bool,
     *     quotas_actifs: bool,
     *     quotas: array<int, int>,
     *     exclure_deja_appuyes: bool,
     * }
     *   type_appui : scolaire, formation_professionnelle ou les_deux (SessionParrainage::TYPES_APPUI) ;
     *   etat : en_cours, validee, paiement_en_cours ou cloturee (SessionParrainage::ETATS) ;
     *   quotas : region_id => montant en FCFA, vide quand quotas_actifs est faux (RG-04) ;
     *   exclure_deja_appuyes : RG-07, à combiner avec getOevAppuyesParPartenaire().
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si la session n'existe pas
     */
    public function getSessionParametres(int $sessionId): array
    {
        $session = SessionParrainage::with('quotas')->findOrFail($sessionId);

        return [
            'id' => $session->id,
            'annee' => $session->annee,
            'numero' => $session->numero,
            'etat' => $session->etat,
            'type_appui' => $session->type_appui,
            'enveloppe' => $session->enveloppe,
            'plafond_beneficiaire' => $session->plafond_beneficiaire,
            'bloquer_depassement_enveloppe' => $session->bloquer_depassement_enveloppe,
            'quotas_actifs' => $session->quotas_actifs,
            'quotas' => $session->quotas_actifs ? $session->quotas->pluck('montant', 'region_id')->map(fn ($m) => (int) $m)->all() : [],
            'exclure_deja_appuyes' => $session->exclure_deja_appuyes,
        ];
    }

    /**
     * Fait passer une session à un autre état, avec contrôle des transitions et trace dans le journal.
     *
     * - Vers l'avant, une étape à la fois : en_cours → validee → paiement_en_cours → cloturee.
     *   Réservé aux utilisateurs ayant « gérer sessions parrainage ». Le module Sélection l'appelle
     *   avec « validee » quand la liste définitive est validée.
     * - Vers l'arrière (vers n'importe quel état antérieur) : administrateurs seulement, motif obligatoire.
     * - Passage à « cloturee » : la date de clôture est renseignée ; elle est effacée en cas de retour arrière.
     *
     * @param  int  $sessionId  Identifiant de la session
     * @param  string  $nouvelEtat  État visé (clé de SessionParrainage::ETATS)
     * @param  User  $utilisateur  Auteur du changement, tracé dans le journal
     * @param  string|null  $motif  Obligatoire pour un retour arrière, facultatif sinon
     * @return SessionParrainage La session à jour
     *
     * @throws TransitionSessionInvalide si la transition, les droits ou le motif ne conviennent pas
     */
    public function changerEtatSession(int $sessionId, string $nouvelEtat, User $utilisateur, ?string $motif = null): SessionParrainage
    {
        $session = SessionParrainage::findOrFail($sessionId);
        $ancienEtat = $session->etat;
        $motif = trim((string) $motif) ?: null;

        if (! array_key_exists($nouvelEtat, SessionParrainage::ETATS)) {
            throw new TransitionSessionInvalide("État inconnu : « {$nouvelEtat} ».");
        }

        $positions = array_flip(array_keys(SessionParrainage::ETATS));
        $retourArriere = $positions[$nouvelEtat] < $positions[$ancienEtat];

        if ($retourArriere) {
            if (! $utilisateur->supervise()) {
                throw new TransitionSessionInvalide('Seul un administrateur peut ramener une session à un état antérieur.');
            }
            if ($motif === null) {
                throw new TransitionSessionInvalide('Indiquez le motif du retour à un état antérieur.');
            }
        } else {
            if (! $utilisateur->can('gérer sessions parrainage')) {
                throw new TransitionSessionInvalide('Vous n’avez pas le droit de changer l’état d’une session.');
            }
            if ($nouvelEtat !== SessionParrainage::etatSuivant($ancienEtat)) {
                throw new TransitionSessionInvalide(sprintf(
                    'Une session « %s » ne peut pas passer directement à « %s ».',
                    $session->libelleEtat(),
                    SessionParrainage::ETATS[$nouvelEtat],
                ));
            }
        }

        DB::transaction(function () use ($session, $nouvelEtat, $ancienEtat, $utilisateur, $motif) {
            $session->update([
                'etat' => $nouvelEtat,
                'date_cloture' => $nouvelEtat === SessionParrainage::ETAT_CLOTUREE ? now()->toDateString() : null,
                'updated_by' => $utilisateur->id,
            ]);

            JournalActivite::consigner(
                'session_parrainage.changement_etat',
                $session,
                sprintf('État : %s → %s', SessionParrainage::ETATS[$ancienEtat], SessionParrainage::ETATS[$nouvelEtat]),
                array_filter(['ancien_etat' => $ancienEtat, 'nouvel_etat' => $nouvelEtat, 'motif' => $motif]),
                $utilisateur,
            );
        });

        return $session->refresh();
    }

    /**
     * RG-01 / RG-07 : l'OEV a-t-il déjà un appui PARTENAIRE de cette nature pour cette année ?
     * Un appui de nature différente ne compte pas (un appui santé n'empêche pas un appui scolaire).
     *
     * @param  int  $oevId  Identifiant de l'OEV
     * @param  string  $annee  Année scolaire, ex : 2026-2027
     * @param  int|string  $natureAppui  Identifiant ou code de natures_appui ; pour une session, son type
     *                                    d'appui : « scolaire » ou « formation_professionnelle »
     *                                    (pour « les_deux », appeler une fois par nature)
     * @return bool true si au moins un appui partenaire de cette nature existe ; false aussi pour une nature inconnue
     */
    public function estDejaAppuye(int $oevId, string $annee, int|string $natureAppui): bool
    {
        $nature = NatureAppui::trouver($natureAppui);

        return $nature !== null && AppuiPartenaire::where('oev_id', $oevId)
            ->where('annee', $annee)
            ->where('nature_appui_id', $nature->id)
            ->exists();
    }

    /**
     * RG-07 : identifiants des OEV déjà appuyés par un partenaire pour cette nature et cette année,
     * pour les exclure en masse de la sélection de l'État.
     *
     * @param  string  $annee  Année scolaire, ex : 2026-2027
     * @param  int|string  $natureAppui  Identifiant ou code de natures_appui (voir estDejaAppuye)
     * @return array<int, int> identifiants d'OEV, sans doublon ; vide pour une nature inconnue
     */
    public function getOevAppuyesParPartenaire(string $annee, int|string $natureAppui): array
    {
        $nature = NatureAppui::trouver($natureAppui);
        if ($nature === null) {
            return [];
        }

        return AppuiPartenaire::where('annee', $annee)
            ->where('nature_appui_id', $nature->id)
            ->distinct()
            ->orderBy('oev_id')
            ->pluck('oev_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Contrôle RG-01 à la saisie d'un appui : appuis déjà reçus par l'OEV, la même année et pour la
     * même nature, d'un partenaire ou de l'État (liste définitive, fournie par le module Sélection).
     *
     * @param  int|null  $sansAppuiId  Appui à ignorer (celui que l'on modifie)
     * @return array<int, string> une phrase par appui existant ; vide s'il n'y a pas de doublon
     */
    public function doublonsAppui(int $oevId, string $annee, NatureAppui $nature, ?int $sansAppuiId = null): array
    {
        $doublons = AppuiPartenaire::with('parrain')
            ->where('oev_id', $oevId)
            ->where('annee', $annee)
            ->where('nature_appui_id', $nature->id)
            ->when($sansAppuiId, fn ($q) => $q->whereKeyNot($sansAppuiId))
            ->get()
            ->map(fn (AppuiPartenaire $appui) => "Appui « {$nature->libelle} » de {$appui->parrain->nom}"
                . ($appui->montant ? ' (' . Montant::fcfa($appui->montant) . ')' : ''))
            ->all();

        if (in_array($nature->code, $this->sourceSelection->naturesAppuiEtat($oevId, $annee), true)) {
            $doublons[] = "Appui « {$nature->libelle} » de l’État (session de parrainage)";
        }

        return $doublons;
    }

    /**
     * Proposition de quotas au prorata du nombre d'OEV éligibles par région (méthode du plus fort
     * reste : la somme des quotas proposés est égale à l'enveloppe). Rien n'est enregistré.
     *
     * @return array<int, int> region_id => montant proposé, pour toutes les régions (0 sans éligible)
     */
    public function proposerQuotasAuProrata(SessionParrainage $session): array
    {
        $eligibles = $this->sourceSelection->eligiblesParRegion($session);
        $regions = Region::orderBy('nom')->pluck('id');
        $total = array_sum(array_intersect_key($eligibles, $regions->flip()->all()));

        if ($total === 0) {
            return $regions->mapWithKeys(fn ($id) => [$id => 0])->all();
        }

        $parts = $regions->mapWithKeys(fn ($id) => [$id => $session->enveloppe * ($eligibles[$id] ?? 0) / $total]);
        $quotas = $parts->map(fn ($part) => (int) floor($part));
        $reste = $session->enveloppe - $quotas->sum();

        // Les FCFA restants vont aux régions dont la partie décimale est la plus forte
        $parts->map(fn ($part) => $part - floor($part))
            ->sortDesc()
            ->keys()
            ->take($reste)
            ->each(fn ($id) => $quotas->put($id, $quotas[$id] + 1));

        return $quotas->all();
    }
}
