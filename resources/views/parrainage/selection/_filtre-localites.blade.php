{{--
  Filtre de localité à choix multiple : régions entières, ou seulement certaines de leurs provinces.
  Une région cochée sans province = toute la région ; avec des provinces cochées = ces provinces seulement.
  « Toutes les régions » (aucune région cochée) : pas de filtre de localité.
  Variables : $localites (arborescence de la zone de l'utilisateur), $regions (ids cochés), $provinces (ids cochés),
  $label (facultatif, « Localité » par défaut)
--}}
@php
  $regions = array_map('intval', (array) $regions);
  $provinces = array_map('intval', (array) $provinces);
  $choix = collect($localites)->filter(fn ($r) => in_array($r['id'], $regions, true))->map(function ($r) use ($provinces) {
      $parProvince = collect($r['provinces'])->filter(fn ($p) => in_array($p['id'], $provinces, true))->pluck('nom');

      return $parProvince->isEmpty() ? $r['nom'] : $r['nom'] . ' (' . $parProvince->implode(', ') . ')';
  });
  $resume = match ($choix->count()) {
      0 => 'Toutes les régions',
      1 => $choix->first(),
      default => $choix->count() . ' régions choisies',
  };
@endphp
<label class="form-label" for="m-localites">{{ $label ?? 'Localité' }}</label>
<div class="dropdown" data-multi>
  <button class="form-select form-select-sm text-start text-truncate" id="m-localites" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="{{ $choix->implode(' · ') }}">
    {{ $resume }}
  </button>
  <div class="dropdown-menu sel-multi-menu sel-multi-menu--large p-2">
    <p class="small text-muted mb-2">Cochez des régions ; pour n’en garder qu’une partie, cochez ensuite ses provinces.</p>
    <div class="form-check border-bottom pb-2 mb-2">
      <input class="form-check-input" type="checkbox" id="m-toutes-regions" @checked($choix->isEmpty()) data-toutes-regions>
      <label class="form-check-label fw-semibold" for="m-toutes-regions">Toutes les régions</label>
    </div>
    @foreach ($localites as $region)
      @php($regionCochee = in_array($region['id'], $regions, true))
      <div class="sel-region" data-region>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="m-region-{{ $region['id'] }}" name="regions[]" value="{{ $region['id'] }}" @checked($regionCochee) data-region-case>
          <label class="form-check-label fw-semibold" for="m-region-{{ $region['id'] }}">{{ $region['nom'] }}</label>
        </div>
        <div class="sel-provinces" data-provinces @unless ($regionCochee) hidden @endunless>
          @foreach ($region['provinces'] as $province)
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="m-province-{{ $province['id'] }}" name="provinces[]" value="{{ $province['id'] }}"
                     @checked($regionCochee && in_array($province['id'], $provinces, true)) @disabled(! $regionCochee)>
              <label class="form-check-label" for="m-province-{{ $province['id'] }}">{{ $province['nom'] }}</label>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
</div>

@once
@push('scripts')
<script>
  // « Toutes les régions » : décoche les régions choisies ; cocher une région la décoche
  document.addEventListener('change', function (e) {
    var menu = e.target.closest('[data-multi]');
    if (!menu || !menu.querySelector('[data-toutes-regions]')) return;
    var toutes = menu.querySelector('[data-toutes-regions]');
    if (e.target === toutes) {
      if (!toutes.checked) { toutes.checked = true; return; }
      menu.querySelectorAll('[data-region-case]:checked').forEach(function (c) {
        c.checked = false;
        c.dispatchEvent(new Event('change', { bubbles: true }));
      });
    } else if (e.target.matches('[data-region-case]')) {
      toutes.checked = !menu.querySelector('[data-region-case]:checked');
    }
  });
</script>
@endpush
@endonce
