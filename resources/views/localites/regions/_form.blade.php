<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label" for="nom">Nom de la région <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" value="{{ old('nom', $region->nom ?? '') }}" required>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label class="form-label" for="ancien_nom">Ancienne appellation</label>
    <input class="form-control @error('ancien_nom') is-invalid @enderror" id="ancien_nom" name="ancien_nom" type="text" value="{{ old('ancien_nom', $region->ancien_nom ?? '') }}" placeholder="Ex : Boucle du Mouhoun">
    @error('ancien_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
