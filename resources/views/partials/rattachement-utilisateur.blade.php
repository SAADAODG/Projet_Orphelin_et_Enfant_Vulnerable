{{--
  Rattachement géographique d'un compte : région pour un DR, province pour un DP, rien au niveau central.
  Le champ affiché suit le rôle choisi dans le même formulaire (select[name="role"]).

  Paramètres :
    $prefixe      préfixe des id des champs (unique par formulaire)
    $regions      régions avec leurs provinces
    $niveauxRoles nom du rôle => niveau (central | region | province)
    $user         utilisateur modifié (facultatif)
--}}
@php
  $user ??= null;
  $roleActuel = $user?->roles->first()?->name;
  // Valeurs saisies (old) uniquement pour le formulaire d'ajout, sinon valeurs du compte
  $niveauChoisi = $niveauxRoles[$user ? $roleActuel : old('role')] ?? null;
  $regionChoisie = (string) ($user ? $user->region_id : old('region_id'));
  $provinceChoisie = (string) ($user ? $user->province_id : old('province_id'));
@endphp

<div class="col-12" data-rattachement data-niveaux="{{ json_encode($niveauxRoles) }}" data-role-actuel="{{ $roleActuel }}">
  <div class="row g-3">
    <div class="col-12" data-niveau="region" @if ($niveauChoisi !== 'region') hidden @endif>
      <label class="form-label" for="{{ $prefixe }}-region">Région du directeur régional <span class="text-danger">*</span></label>
      <select class="form-select" id="{{ $prefixe }}-region" name="region_id">
        <option value="">Choisir la région</option>
        @foreach ($regions as $region)
          <option value="{{ $region->id }}" @selected($regionChoisie === (string) $region->id)>{{ $region->nom }}</option>
        @endforeach
      </select>
      <div class="form-text">Son tableau de bord couvrira toute la région.</div>
    </div>
    <div class="col-12" data-niveau="province" @if ($niveauChoisi !== 'province') hidden @endif>
      <label class="form-label" for="{{ $prefixe }}-province">Province du directeur provincial <span class="text-danger">*</span></label>
      <select class="form-select" id="{{ $prefixe }}-province" name="province_id">
        <option value="">Choisir la province</option>
        @foreach ($regions as $region)
          <optgroup label="{{ $region->nom }}">
            @foreach ($region->provinces as $province)
              <option value="{{ $province->id }}" @selected($provinceChoisie === (string) $province->id)>{{ $province->nom }}</option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
      <div class="form-text">Son tableau de bord couvrira la province ; la région est déduite automatiquement.</div>
    </div>
  </div>
</div>

@once
@push('scripts')
<script>
  // Affiche le champ de rattachement correspondant au rôle choisi (région pour un DR, province pour un DP).
  document.querySelectorAll('[data-rattachement]').forEach(function (bloc) {
    var niveaux = JSON.parse(bloc.dataset.niveaux);
    var role = bloc.closest('form').querySelector('select[name="role"]');
    if (!role) return;

    function majChamps() {
      var niveau = niveaux[role.value || bloc.dataset.roleActuel] || 'central';
      bloc.querySelectorAll('[data-niveau]').forEach(function (champ) {
        var actif = champ.dataset.niveau === niveau;
        champ.hidden = !actif;
        champ.querySelector('select').required = actif;
      });
    }

    role.addEventListener('change', majChamps);
    majChamps();
  });
</script>
@endpush
@endonce
