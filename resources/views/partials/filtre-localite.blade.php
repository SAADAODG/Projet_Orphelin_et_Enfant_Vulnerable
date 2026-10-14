{{--
  Filtre « Région / Province » d'une liste, limité à la zone de l'utilisateur (voir CloisonneParZone::filtreLocalite).
  À placer dans un formulaire GET. Variables : $localites, $regionId, $provinceId
  Le DP (une seule province) n'a rien à choisir : rien n'est affiché. La province n'apparaît qu'une fois la région choisie.
--}}
@php
  $regionChoisie = collect($localites)->firstWhere('id', $regionId) ?? (count($localites) === 1 ? $localites[0] : null);
  $provinces = $regionChoisie['provinces'] ?? [];
@endphp
@if (count($localites) > 1 || count($localites[0]['provinces'] ?? []) > 1)
  <select class="form-select w-auto" name="region_id" aria-label="Filtrer par région" onchange="this.form.province_id && (this.form.province_id.value = ''); this.form.submit()">
    @if (count($localites) > 1)<option value="">Toutes les régions</option>@endif
    @foreach ($localites as $region)
      <option value="{{ $region['id'] }}" @selected($regionId === $region['id'])>{{ $region['nom'] }}</option>
    @endforeach
  </select>
  @if ($regionChoisie && count($provinces) > 1)
    <select class="form-select w-auto" name="province_id" aria-label="Filtrer par province" onchange="this.form.submit()">
      <option value="">Toutes les provinces</option>
      @foreach ($provinces as $province)
        <option value="{{ $province['id'] }}" @selected($provinceId === $province['id'])>{{ $province['nom'] }}</option>
      @endforeach
    </select>
  @endif
@endif
