<?php

namespace App\Models\Concerns\Oev;

use App\Models\Commune;
use App\Models\ListeReference;

/**
 * Dossier OEV (App\Models\Oev) — Fiche d’identification : statut, listes paramétrables, libellés, noms, téléphones, tuteur, lieux, vulnérabilités déduites.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait Fiche
{
    /**
     * Statut OEV déduit de la situation des parents : un parent décédé (« non ») fait de l'enfant un orphelin ;
     * sinon (vivants ou inconnus) l'enfant est considéré comme vulnérable.
     */
    public static function calculerStatut(?string $mereVivante, ?string $pereVivant): string
    {
        return match (true) {
            $mereVivante === 'non' && $pereVivant === 'non' => 'orphelin_double',
            $mereVivante === 'non' => 'orphelin_mere',
            $pereVivant === 'non' => 'orphelin_pere',
            default => 'vulnerable',
        };
    }

    /*
     * Listes paramétrables (Paramètres) : code => libellé. $actifs : seulement les choix proposés à la saisie
     * (formulaire, validation) ; sinon tous, pour afficher les dossiers enregistrés avant une désactivation.
     */
    public static function groupesPopulation(bool $actifs = false): array
    {
        return ListeReference::options('groupes-population', $actifs);
    }

    public static function liensTuteur(bool $actifs = false): array
    {
        return ListeReference::options('liens-tuteur', $actifs);
    }

    public static function lieuxDeVie(bool $actifs = false): array
    {
        return ListeReference::options('lieux-de-vie', $actifs);
    }

    public static function vulnerabilites(bool $actifs = false): array
    {
        return ListeReference::options('vulnerabilites', $actifs);
    }

    /** Libellés d'un champ à choix multiples (tableau de codes), ex. vulnérabilités. */
    public function libelles(string $champ, array $liste): array
    {
        return collect((array) $this->{$champ})->map(fn ($code) => $liste[$code] ?? $code)->values()->all();
    }

    public function nomComplet(): string
    {
        return $this->nom . ' ' . $this->prenom;
    }

    /** Chiffres du numéro sans l'indicatif du Burkina Faso, ex. "+226 70 12 34 56" → "70123456". */
    public static function numeroLocal(?string $numero): string
    {
        $chiffres = preg_replace('/\D/', '', (string) $numero);

        return strlen($chiffres) === 11 && str_starts_with($chiffres, '226') ? substr($chiffres, 3) : $chiffres;
    }

    /** "70123456" → "+226 70 12 34 56" */
    public static function formaterTelephone(string $numeroLocal): string
    {
        return self::INDICATIF . ' ' . implode(' ', str_split($numeroLocal, 2));
    }

    /** Initiales pour l'avatar, ex. "OUEDRAOGO Awa" → "OA". */
    public function initiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->nom, 0, 1) . mb_substr((string) $this->prenom, 0, 1));
    }

    public function nomCompletTuteur(): string
    {
        return $this->nom_tuteur . ' ' . $this->prenom_tuteur;
    }

    /** Libellé d'une valeur codée, ex. $oev->libelle('statut', self::STATUTS). */
    public function libelle(string $champ, array $liste): string
    {
        return $liste[$this->{$champ}] ?? (string) $this->{$champ};
    }

    /**
     * Vulnérabilités déduites des réponses (cochées automatiquement, non modifiables à la main) :
     * orphelin si un parent est décédé, handicap, absence d'acte de naissance.
     */
    public static function vulnerabilitesAutomatiques(array $donnees): array
    {
        return array_keys(array_filter([
            'orphelin' => ($donnees['mere_vivante'] ?? null) === 'non' || ($donnees['pere_vivant'] ?? null) === 'non',
            'handicap' => (bool) ($donnees['handicap'] ?? false),
            'sans_acte_naissance' => isset($donnees['a_acte_naissance']) && ! $donnees['a_acte_naissance'],
        ]));
    }

    /** Le parent ou les parents qui s'occupent de l'enfant (selon le lien choisi). */
    public static function parentsTuteurs(?string $lien): array
    {
        return match ($lien) {
            'mere' => ['mere'],
            'pere' => ['pere'],
            'parents' => ['pere', 'mere'],
            default => [],
        };
    }

    /** Commune de naissance au Burkina, sinon le lieu saisi (autre lieu / étranger). */
    public function lieuDeNaissance(): ?string
    {
        return $this->lieuNaissanceCommune?->nom ?? $this->lieu_naissance;
    }

    /** Commune de provenance au Burkina, sinon le lieu saisi (autre lieu / étranger). */
    public function lieuDeProvenance(): ?string
    {
        return $this->lieuProvenanceCommune?->nom ?? $this->lieu_provenance;
    }
}
