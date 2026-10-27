@use('App\Models\Ecole')

@include('partials.localite-selects', [
  'localites' => $localites,
  'valeurs' => $valeurs,
  'colonnes' => ['region_id' => 'col-md-4', 'province_id' => 'col-md-4', 'commune_id' => 'col-md-4'],
])
<div class="row g-3 mt-0">
  <div class="col-md-4">
    <label class="form-label" for="nature">Nature <span class="text-danger">*</span></label>
    <select class="form-select @error('nature') is-invalid @enderror" id="nature" name="nature" required>
      @foreach (Ecole::NATURES as $cle => $libelle)<option value="{{ $cle }}" @selected(old('nature', $ecole->nature ?? Ecole::NATURE_ECOLE) === $cle)>{{ $libelle }}</option>@endforeach
    </select>
    @error('nature')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-8">
    <label class="form-label" for="nom">Nom de l’établissement <span class="text-danger">*</span></label>
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
  {{-- Paiement des OEV parrainés : un seul RIB par établissement, partagé par tous ses OEV --}}
  <div class="col-md-5">
    <label class="form-label" for="banque">Banque</label>
    <input class="form-control @error('banque') is-invalid @enderror" id="banque" name="banque" type="text" maxlength="120"
           value="{{ old('banque', $ecole->banque) }}" placeholder="Ex. : Coris Bank International">
    @error('banque')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-7">
    <label class="form-label" for="rib">RIB</label>
    <input class="form-control @error('rib') is-invalid @enderror" id="rib" name="rib" type="text" maxlength="60"
           value="{{ old('rib', $ecole->rib) }}" placeholder="Code banque, code guichet, numéro de compte, clé" aria-describedby="aide-rib">
    @error('rib')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text" id="aide-rib">Sert au paiement des OEV parrainés qui fréquentent l’établissement.</div>
  </div>
</div>
