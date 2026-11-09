<?php

namespace App\Models\Concerns\Oev;

use Illuminate\Database\Eloquent\Builder;

/**
 * Dossier OEV (App\Models\Oev) — Pièces du dossier : seules celles qui s’appliquent à l’enfant sont exigées ; tailles maximales des fichiers.
 * Les constantes (états, listes) restent dans le modèle : self:: les désigne.
 */
trait Pieces
{
    public static function tailleMaxFichierKo(string $type): int
    {
        $souhaitee = $type === 'photo' ? self::TAILLE_MAX_PHOTO_KO : self::TAILLE_MAX_DOCUMENT_KO;

        return min($souhaitee, intdiv(self::octetsIni('upload_max_filesize'), 1024));
    }

    /** Taille totale maximale d'un envoi de formulaire (Ko), fixée par post_max_size. */
    public static function tailleMaxEnvoiKo(): int
    {
        return intdiv(self::octetsIni('post_max_size'), 1024);
    }

    /** Convertit une valeur php.ini ("2M", "8M", "1G"…) en octets ; 0 ou vide = illimité. */
    private static function octetsIni(string $cle): int
    {
        $valeur = trim((string) ini_get($cle));
        $nombre = (int) $valeur;
        if ($nombre <= 0) {
            return PHP_INT_MAX;
        }

        return match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1024 ** 3,
            'm' => $nombre * 1024 ** 2,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    }

    /**
     * Pièces exigées pour cet enfant, d'après ses réponses :
     * pas d'acte de naissance → pas d'acte à charger ; non scolarisé → pas de certificat de scolarité ;
     * tuteur sans CNIB → pas de CNIB à charger. La photo et le RIB sont toujours demandés.
     *
     * @return array<string, string> type => libellé
     */
    public function piecesRequises(): array
    {
        return array_filter(self::DOCUMENTS, fn ($type) => self::pieceRequise($type, [
            'a_acte_naissance' => $this->a_acte_naissance,
            'situation_scolaire' => $this->situation_scolaire,
            'etablissement_precedent' => $this->etablissement_precedent,
            'tuteur_a_cnib' => $this->tuteur_a_cnib,
        ]), ARRAY_FILTER_USE_KEY);
    }

    /** Règle d'exigence d'une pièce à partir des réponses (utilisée aussi à la validation). */
    public static function pieceRequise(string $type, array $reponses): bool
    {
        $faux = fn ($valeur) => $valeur === false || $valeur === 0 || $valeur === '0';

        return match ($type) {
            'acte_naissance' => ! $faux($reponses['a_acte_naissance'] ?? null),
            'certificat_scolarite' => ($reponses['situation_scolaire'] ?? null) === 'scolarise',
            // Bulletin, diplôme ou attestation : quand l'enfant a fréquenté un établissement l'année précédente
            'bulletin' => ($reponses['situation_scolaire'] ?? 'non_scolarise') !== 'non_scolarise' && filled($reponses['etablissement_precedent'] ?? null),
            'cnib_tuteur' => ! $faux($reponses['tuteur_a_cnib'] ?? null),
            default => true,
        };
    }

    /** Types des pièces exigées déjà fournies. */
    public function piecesFournies(): array
    {
        $types = $this->relationLoaded('documents') ? $this->documents->pluck('type')->all() : $this->documents()->pluck('type')->all();

        return array_values(array_intersect(array_keys($this->piecesRequises()), $types));
    }

    public function nombreDocuments(): int
    {
        return count($this->piecesFournies());
    }

    public function estComplet(): bool
    {
        return count($this->piecesFournies()) >= count($this->piecesRequises());
    }

    /** Équivalent SQL de estComplet() : chaque pièce exigée par les réponses est jointe. */
    public function scopeDossierComplet(Builder $query): Builder
    {
        $fournie = fn (Builder $q, string $type) => $q->whereHas('documents', fn ($d) => $d->where('type', $type));

        return $query
            ->where(fn ($q) => $fournie($q, 'photo'))
            ->where(fn ($q) => $fournie($q, 'enquete_sociale'))
            ->where(fn ($q) => $q->where('a_acte_naissance', false)->orWhere(fn ($q) => $fournie($q, 'acte_naissance')))
            ->where(fn ($q) => $q->whereNull('situation_scolaire')->orWhere('situation_scolaire', '!=', 'scolarise')
                ->orWhere(fn ($q) => $fournie($q, 'certificat_scolarite')))
            ->where(fn ($q) => $q->whereNull('situation_scolaire')->orWhere('situation_scolaire', 'non_scolarise')
                ->orWhereNull('etablissement_precedent')->orWhere('etablissement_precedent', '')
                ->orWhere(fn ($q) => $fournie($q, 'bulletin')))
            ->where(fn ($q) => $q->where('tuteur_a_cnib', false)->orWhere(fn ($q) => $fournie($q, 'cnib_tuteur')));
    }
}
