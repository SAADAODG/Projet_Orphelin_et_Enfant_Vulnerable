@use('App\Models\Ecole')

@include('partials.localite-selects', [
  'localites' => $localites,
  'valeurs' => $valeurs,
  'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
])
<div class="row g-3 mt-0">
  <div class="col-md-8">
    <label class="form-label" for="nom">Nom de l’école <span class="text-danger">*</span></label>
    <input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" type="text" maxlength="255"
           value="{{ old('nom', $ecole->nom) }}" placeholder="Ex. : Bagassi A" required autofocus>
    @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label class="form-label" for="ceb">CEB</label>
    <input class="form-control @error('ceb') is-invalid @enderror" id="ceb" name="ceb" type="text" maxlength="150"
           value="{{ old('ceb', $ecole->ceb) }}" placeholder="Circonscription d’éducation de base">
    @error('ceb')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label class="form-label" for="statut">Statut</label>
    <select class="form-select @error('statut') is-invalid @enderror" id="statut" name="statut">
      <option value="">Non précisé</option>
      @foreach (Ecole::STATUTS as $cle => $libelle)<option value="{{ $cle }}" @selected(old('statut', $ecole->statut) === $cle)>{{ $libelle }}</option>@endforeach
    </select>
    @error('statut')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label class="form-label" for="type">Type</label>
    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type">
      <option value="">Non précisé</option>
      @foreach (Ecole::TYPES as $cle => $libelle)<option value="{{ $cle }}" @selected(old('type', $ecole->type) === $cle)>{{ $libelle }}</option>@endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
