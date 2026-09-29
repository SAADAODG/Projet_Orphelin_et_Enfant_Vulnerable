{{--
  Listes déroulantes en cascade Région > Province > Commune, alimentées par les tables
  regions / provinces / communes (voir Region::arborescence()).

  Paramètres :
    $localites  arborescence fournie par le contrôleur (Region::arborescence())
    $valeurs    ['region_id' => …, 'province_id' => …, 'commune_id' => …] (valeurs actuelles)
    $requis     bool, true par défaut
    $colonnes   classes de colonne Bootstrap par champ (facultatif)
    $attributs  attributs HTML supplémentaires par champ, ex. ['region_id' => 'data-recap="region_id"'] (facultatif)
--}}
@php
  $requis ??= true;
  $valeurs ??= [];
  $attributs ??= [];
  $colonnes = ($colonnes ?? []) + ['region_id' => 'col-md-6', 'province_id' => 'col-md-6', 'commune_id' => 'col-12'];
  $champs = [
    'region_id' => ['label' => 'Région', 'vide' => 'Choisir la région', 'choisir' => 'Choisir la région'],
    'province_id' => ['label' => 'Province', 'vide' => "Choisir d'abord la région", 'choisir' => 'Choisir la province'],
    'commune_id' => ['label' => 'Commune', 'vide' => "Choisir d'abord la province", 'choisir' => 'Choisir la commune'],
  ];
@endphp

<div class="row g-3" data-localites="{{ json_encode($localites) }}">
  @foreach ($champs as $champ => $config)
    <div class="{{ $colonnes[$champ] }}">
      <label class="form-label" for="{{ $champ }}">{{ $config['label'] }} @if ($requis)<span class="text-danger">*</span>@endif</label>
      <select class="form-select @error($champ) is-invalid @enderror" id="{{ $champ }}" name="{{ $champ }}"
              data-localite="{{ $champ }}" data-valeur="{{ old($champ, $valeurs[$champ] ?? '') }}" data-vide="{{ $config['vide'] }}" data-choisir="{{ $config['choisir'] }}"
              @if ($requis) required @endif {!! $attributs[$champ] ?? '' !!}>
        <option value="">{{ $config['vide'] }}</option>
      </select>
      @error($champ)
        <div class="invalid-feedback">{{ $message }}</div>
      @else
        <div class="invalid-feedback">{{ $config['label'] }} obligatoire.</div>
      @enderror
    </div>
  @endforeach
</div>

@once
@push('scripts')
<script>
  document.querySelectorAll('[data-localites]').forEach(function (bloc) {
    var regions = JSON.parse(bloc.dataset.localites);
    var region = bloc.querySelector('[data-localite="region_id"]');
    var province = bloc.querySelector('[data-localite="province_id"]');
    var commune = bloc.querySelector('[data-localite="commune_id"]');

    function remplir(select, elements, valeur) {
      select.innerHTML = '';
      select.add(new Option(elements.length ? select.dataset.choisir : select.dataset.vide, ''));
      elements.forEach(function (e) { select.add(new Option(e.nom, e.id, false, String(e.id) === String(valeur))); });
      select.disabled = elements.length === 0;
    }
    function trouver(liste, id) { return liste.find(function (e) { return String(e.id) === String(id); }); }

    function majProvinces(valeur) {
      var r = trouver(regions, region.value);
      remplir(province, r ? r.provinces : [], valeur);
    }
    function majCommunes(valeur) {
      var r = trouver(regions, region.value);
      var p = r ? trouver(r.provinces, province.value) : null;
      remplir(commune, p ? p.communes : [], valeur);
    }

    remplir(region, regions, region.dataset.valeur);
    majProvinces(province.dataset.valeur);
    majCommunes(commune.dataset.valeur);

    region.addEventListener('change', function () { majProvinces(''); majCommunes(''); });
    province.addEventListener('change', function () { majCommunes(''); });
  });
</script>
@endpush
@endonce
