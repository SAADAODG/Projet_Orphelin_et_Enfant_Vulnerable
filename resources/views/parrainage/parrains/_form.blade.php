{{--
  Champs d'un parrain. Variables : $parrain, $regions (avec leurs provinces), $zones ([['region_id' => …, 'province_id' => …], …])
  Zone d'intervention : une ligne par zone, région puis province (vide = toute la région).
--}}
@use('App\Models\Parrain')
@php
  $zones = array_values(old('zones', $zones)) ?: [['region_id' => null, 'province_id' => null]];
  $provincesParRegion = $regions->mapWithKeys(fn ($r) => [$r->id => $r->provinces->map->only(['id', 'nom'])->values()]);
@endphp

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
    <fieldset class="border rounded p-3">
      <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Zone d’intervention</legend>
      <p class="form-text mt-1">Choisissez la région, puis une province ; laissez « Toute la région » si le parrain intervient dans toute la région.</p>
      <div data-zones data-provinces='@json($provincesParRegion)'>
        @foreach ($zones as $i => $zone)
          @php($provincesRegion = $provincesParRegion[(int) ($zone['region_id'] ?? 0)] ?? collect())
          <div class="row g-2 align-items-start mb-2" data-zone>
            <div class="col-sm-5">
              <label class="visually-hidden" for="zone-region-{{ $i }}">Région</label>
              <select class="form-select @error("zones.{$i}.region_id") is-invalid @enderror" id="zone-region-{{ $i }}" name="zones[{{ $i }}][region_id]" data-zone-region>
                <option value="">Choisir la région</option>
                @foreach ($regions as $region)
                  <option value="{{ $region->id }}" @selected((string) ($zone['region_id'] ?? '') === (string) $region->id)>{{ $region->nom }}</option>
                @endforeach
              </select>
              @error("zones.{$i}.region_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-5">
              <label class="visually-hidden" for="zone-province-{{ $i }}">Province</label>
              <select class="form-select @error("zones.{$i}.province_id") is-invalid @enderror" id="zone-province-{{ $i }}" name="zones[{{ $i }}][province_id]" data-zone-province @disabled($provincesRegion->isEmpty())>
                <option value="">{{ $provincesRegion->isEmpty() ? 'Choisir d’abord la région' : 'Toute la région' }}</option>
                @foreach ($provincesRegion as $province)
                  <option value="{{ $province['id'] }}" @selected((string) ($zone['province_id'] ?? '') === (string) $province['id'])>{{ $province['nom'] }}</option>
                @endforeach
              </select>
              @error("zones.{$i}.province_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-2">
              <button class="btn btn-outline-danger w-100" type="button" data-zone-retirer aria-label="Retirer cette zone"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
          </div>
        @endforeach
      </div>
      <button class="btn btn-outline-primary btn-sm" type="button" data-zone-ajouter><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une zone</button>
    </fieldset>
  </div>

  <div class="col-12">
    <div class="form-check form-switch">
      <input type="hidden" name="actif" value="0">
      <input class="form-check-input" type="checkbox" role="switch" id="actif" name="actif" value="1" @checked(old('actif', $parrain->actif))>
      <label class="form-check-label" for="actif">Parrain actif</label>
    </div>
    <div class="form-text">Un parrain inactif reste dans le répertoire et garde ses appuis, mais ne peut plus en recevoir de nouveaux.</div>
  </div>
</div>

@push('scripts')
<script>
  // Zone d'intervention : provinces de la région choisie, ajout et retrait de lignes
  document.addEventListener('DOMContentLoaded', function () {
    var bloc = document.querySelector('[data-zones]');
    if (!bloc) return;
    var provinces = JSON.parse(bloc.dataset.provinces);
    var compteur = bloc.querySelectorAll('[data-zone]').length;

    var remplirProvinces = function (ligne) {
      var region = ligne.querySelector('[data-zone-region]').value;
      var liste = ligne.querySelector('[data-zone-province]');
      var choix = provinces[region] || [];
      liste.replaceChildren(new Option(region ? 'Toute la région' : 'Choisir d’abord la région', ''));
      choix.forEach(function (p) { liste.add(new Option(p.nom, p.id)); });
      liste.disabled = !region;
    };

    bloc.addEventListener('change', function (e) {
      if (e.target.matches('[data-zone-region]')) remplirProvinces(e.target.closest('[data-zone]'));
    });

    bloc.addEventListener('click', function (e) {
      var bouton = e.target.closest('[data-zone-retirer]');
      if (!bouton) return;
      var ligne = bouton.closest('[data-zone]');
      if (bloc.querySelectorAll('[data-zone]').length > 1) {
        ligne.remove();
      } else {
        ligne.querySelector('[data-zone-region]').value = '';
        remplirProvinces(ligne);
      }
    });

    document.querySelector('[data-zone-ajouter]').addEventListener('click', function () {
      var modele = bloc.querySelector('[data-zone]');
      var ligne = modele.cloneNode(true);
      var i = compteur++;
      ligne.querySelectorAll('select').forEach(function (liste) {
        var champ = liste.matches('[data-zone-region]') ? 'region_id' : 'province_id';
        liste.name = 'zones[' + i + '][' + champ + ']';
        liste.id = 'zone-' + (champ === 'region_id' ? 'region' : 'province') + '-' + i;
        liste.classList.remove('is-invalid');
        liste.previousElementSibling.htmlFor = liste.id;
      });
      ligne.querySelectorAll('.invalid-feedback').forEach(function (n) { n.remove(); });
      ligne.querySelector('[data-zone-region]').value = '';
      remplirProvinces(ligne);
      bloc.append(ligne);
      ligne.querySelector('[data-zone-region]').focus();
    });
  });
</script>
@endpush
