{{-- Champs d'un parrain. Variable : $parrain. La zone d'intervention se choisit à l'extraction (Pilotage). --}}
@use('App\Models\Parrain')
<div class="row g-3">
  <div class="col-sm-4">
    <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
      @foreach (Parrain::TYPES as $cle => $libelle)
        <option value="{{ $cle }}" @selected(old('type', $parrain->type) === $cle)>{{ $libelle }}</option>
      @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-8">
    <label class="form-label" for="nom">Nom ou raison sociale <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" maxlength="200" value="{{ old('nom', $parrain->nom) }}" required>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-sm-6">
    <label class="form-label" for="contact_nom">Personne de contact</label>
    <input class="form-control @error('contact_nom') is-invalid @enderror" id="contact_nom" name="contact_nom" type="text" maxlength="150" value="{{ old('contact_nom', $parrain->contact_nom) }}">
    @error('contact_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="telephone">Téléphone</label>
    <input class="form-control @error('telephone') is-invalid @enderror" id="telephone" name="telephone" type="tel" maxlength="30" value="{{ old('telephone', $parrain->telephone) }}" placeholder="+226 70 00 00 00">
    @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="email">E-mail</label>
    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" maxlength="150" value="{{ old('email', $parrain->email) }}">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-sm-6">
    <label class="form-label" for="adresse">Adresse</label>
    <input class="form-control @error('adresse') is-invalid @enderror" id="adresse" name="adresse" type="text" maxlength="255" value="{{ old('adresse', $parrain->adresse) }}">
    @error('adresse')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-12">
    <div class="form-check form-switch">
      <input type="hidden" name="actif" value="0">
      <input class="form-check-input" type="checkbox" role="switch" id="actif" name="actif" value="1" @checked(old('actif', $parrain->actif))>
      <label class="form-check-label" for="actif">Parrain actif</label>
    </div>
  </div>
</div>

