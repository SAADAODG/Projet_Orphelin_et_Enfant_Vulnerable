{{--
  Lignes de la liste d'une session (OEV retenus).
  Variables : $lignes (BeneficiaireSession, une page), $session, $modifiable, $total (montant de toute la liste)
--}}
<div class="table-responsive">
  <table class="table align-middle mb-0 sel-table oev-liste">
    <thead>
      <tr>
        <th>OEV</th><th>Statut</th><th>Localité</th><th>Appui</th>
        <th>Frais réels</th><th class="text-end">Montant retenu</th>
        @if ($modifiable)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach ($lignes as $ligne)
        @php
          $oev = $ligne->oev;
        @endphp
        <tr>
          <td>@include('parrainage._oev', ['oev' => $oev, 'detail' => '· ' . $oev->age() . ' ans'])</td>
          <td><span class="oev-statut oev-statut--{{ $oev->statut }}">{{ $oev->libelle('statut', App\Models\Oev::STATUTS) }}</span></td>
          <td class="small">{{ $oev->province?->nom }} <span class="text-muted">· {{ $ligne->region?->nom }}</span></td>
          <td class="small">{{ App\Models\SessionParrainage::TYPES_APPUI[$ligne->type_appui] ?? $ligne->type_appui }}</td>
          <td>
            @if ($modifiable)
              <form class="d-flex gap-1" method="POST" action="{{ route('parrainage.sessions.selection.modifier', [$session, $ligne]) }}">
                @csrf
                @method('PATCH')
                <input class="form-control form-control-sm text-end sel-frais" name="frais_reels" type="text" inputmode="numeric"
                       value="{{ $ligne->frais_reels === null ? '' : App\Support\Montant::nombre($ligne->frais_reels) }}" placeholder="Non renseignés" aria-label="Frais réels de {{ $oev->nomComplet() }}">
                <button class="btn btn-sm btn-outline-secondary" type="submit" title="Enregistrer les frais"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
              </form>
            @else
              {{ $ligne->frais_reels === null ? 'Non renseignés' : App\Support\Montant::fcfa($ligne->frais_reels) }}
            @endif
            @if ($ligne->frais_estimes)<span class="d-block small text-muted">Montant estimatif de la session</span>@endif
          </td>
          <td class="text-end fw-semibold">{{ App\Support\Montant::fcfa($ligne->montant_retenu) }}</td>
          @if ($modifiable)
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <form method="POST" action="{{ route('parrainage.sessions.selection.retirer', [$session, $ligne]) }}" data-confirm="Retirer {{ $oev->nomComplet() }} de la sélection ?" data-confirm-danger>
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Retirer de la sélection"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </form>
              </div>
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="fw-semibold">
        {{-- Total de toute la liste (calculé par la base), pas seulement de la page affichée --}}
        <td colspan="5" class="text-end">{{ isset($total) ? 'Total de la liste' : 'Total de la page' }}</td>
        <td class="text-end">{{ App\Support\Montant::fcfa($total ?? $lignes->sum('montant_retenu')) }}</td>
        @if ($modifiable)<td></td>@endif
      </tr>
    </tfoot>
  </table>
</div>
