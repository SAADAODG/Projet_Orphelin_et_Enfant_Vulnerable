<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label" for="region_id">Région <span class="text-danger">*</span></label>
    <select class="form-select @error('region_id') is-invalid @enderror" id="region_id" name="region_id" required>
      <option value="">Choisir une région</option>
      @foreach ($regions as $r)
        <option value="{{ $r->id }}" @selected((int) old('region_id', $province->region_id ?? '') === $r->id)>{{ $r->nom }}</option>
      @endforeach
    </select>
    @error('region_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label class="form-label" for="nom">Nom de la province <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" value="{{ old('nom', $province->nom ?? '') }}" required>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label class="form-label" for="chef_lieu">Chef-lieu</label>
    <input class="form-control @error('chef_lieu') is-invalid @enderror" id="chef_lieu" name="chef_lieu" type="text" value="{{ old('chef_lieu', $province->chef_lieu ?? '') }}">
    @error('chef_lieu')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
