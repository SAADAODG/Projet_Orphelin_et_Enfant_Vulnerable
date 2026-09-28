<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['oev_id', 'type', 'chemin', 'nom_original', 'mime_type', 'taille', 'uploaded_by'])]
class OevDocument extends Model
{
    protected $table = 'oev_documents';

    public function oev(): BelongsTo
    {
        return $this->belongsTo(Oev::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function libelle(): string
    {
        return Oev::DOCUMENTS[$this->type] ?? $this->type;
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
