@php
  $selectedProvinceId = (int) old('province_id', $commune->province_id ?? '');
  $selectedRegionId = old('region_id', optional($commune->province ?? null)->region_id);
@endphp
<div class="row g-3" data-cascade-form>
  <div class="col-md-6">
    <label class="form-label" for="region_id">Région <span class="text-danger">*</span></label>
    <select class="form-select" id="region_id" name="region_id" data-filter-region>
      <option value="">Choisir une région</option>
      @foreach ($regions as $r)
        <option value="{{ $r->id }}" @selected((string) $selectedRegionId === (string) $r->id)>{{ $r->nom }}</option>
      @endforeach
    </select>
    <div class="form-text">Sélectionnez d'abord la région pour filtrer les provinces.</div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="province_id">Province <span class="text-danger">*</span></label>
    <select class="form-select @error('province_id') is-invalid @enderror" id="province_id" name="province_id" data-filter-province required>
      <option value="">Choisir une province</option>
      @foreach ($provinces as $p)
        <option value="{{ $p->id }}" data-region-id="{{ $p->region_id }}" @selected($selectedProvinceId === $p->id)>{{ $p->nom }}</option>
      @endforeach
    </select>
    @error('province_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label class="form-label" for="nom">Nom de la commune <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" value="{{ old('nom', $commune->nom ?? '') }}" required>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
