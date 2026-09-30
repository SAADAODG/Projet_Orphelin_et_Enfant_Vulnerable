@include('partials.localite-selects', [
  'localites' => $localites,
  'valeurs' => $valeurs,
  'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
])
<div class="row g-3 mt-0">
  <div class="col-md-8">
    <label class="form-label" for="nom">Nom du village ou secteur <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" maxlength="255"
           value="{{ old('nom', $village->nom ?? '') }}" placeholder="Ex. : Kamboinsin, Secteur 12…" required autofocus>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Pour une commune urbaine, enregistrez ses secteurs ; pour une commune rurale, ses villages.</div>
  </div>
</div>
