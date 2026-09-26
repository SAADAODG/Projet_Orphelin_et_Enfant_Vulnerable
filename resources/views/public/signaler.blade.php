@extends('layouts.public')

@section('title', 'Signaler un OEV | OEV')

@section('content')
<div class="bg-light py-4 border-bottom">
  <div class="container px-3 px-lg-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('public.home') }}" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Signaler un OEV</li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold text-dark mb-1">Signaler un Orphelin ou Enfant Vulnérable</h1>
    <p class="text-muted mb-0">Parent, tuteur ou simple citoyen : aidez-nous à identifier un enfant qui a besoin d'être pris en charge par l'État.</p>
  </div>
</div>

<section class="public-section">
  <div class="container px-3 px-lg-4">
    <div class="row g-4 justify-content-center">
      <div class="col-12 col-lg-8">
        @if ($errors->any())
          <div class="alert alert-danger border-0 shadow-sm rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Certains champs sont incomplets. Vérifiez les zones en rouge ci-dessous.
          </div>
        @endif

        <form action="{{ route('public.signaler.store') }}" method="POST" class="signal-form" novalidate>
          @csrf

          <!-- Étape 1 : L'enfant -->
          <div class="signal-step">
            <div class="signal-step-header">
              <span class="signal-step-num">1</span>
              <div>
                <h2 class="signal-step-title">L'enfant</h2>
                <p class="signal-step-sub">Qui est l'enfant que vous souhaitez signaler ?</p>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="enfant_nom">Nom</label>
                <input type="text" class="form-control form-control-lg @error('enfant_nom') is-invalid @enderror" id="enfant_nom" name="enfant_nom" value="{{ old('enfant_nom') }}" placeholder="ex : Ouédraogo" required>
                @error('enfant_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="enfant_prenom">Prénom(s)</label>
                <input type="text" class="form-control form-control-lg @error('enfant_prenom') is-invalid @enderror" id="enfant_prenom" name="enfant_prenom" value="{{ old('enfant_prenom') }}" placeholder="ex : Awa" required>
                @error('enfant_prenom')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="enfant_age">Âge estimé</label>
                <div class="input-group input-group-lg has-validation">
                  <input type="number" min="0" max="17" class="form-control @error('enfant_age') is-invalid @enderror" id="enfant_age" name="enfant_age" value="{{ old('enfant_age') }}" placeholder="ex : 8" required>
                  <span class="input-group-text">ans</span>
                  @error('enfant_age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text">Si vous ne connaissez pas l'âge exact, donnez une estimation.</div>
              </div>
            </div>

            <label class="form-label mt-4 d-block">Situation de vulnérabilité</label>
            <div class="choice-grid choice-grid-3">
              @php $icones = ['orphelin' => 'bi-heartbreak', 'handicape' => 'bi-universal-access', 'autre' => 'bi-three-dots']; @endphp
              @foreach ($vulnerabilites as $valeur => $libelle)
                <label class="choice-card">
                  <input type="radio" name="vulnerabilite" value="{{ $valeur }}" data-toggle-precision="vulnerabilitePrecision" {{ old('vulnerabilite') === $valeur ? 'checked' : '' }} required>
                  <span class="choice-card-body">
                    <i class="bi {{ $icones[$valeur] }}"></i>
                    <span>{{ $libelle }}</span>
                  </span>
                </label>
              @endforeach
            </div>
            @error('vulnerabilite')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div id="vulnerabilitePrecision" class="mt-3 {{ old('vulnerabilite') === 'autre' ? '' : 'd-none' }}">
              <label class="form-label" for="vulnerabilite_precision">Précisez la situation</label>
              <input type="text" class="form-control @error('vulnerabilite_precision') is-invalid @enderror" id="vulnerabilite_precision" name="vulnerabilite_precision" value="{{ old('vulnerabilite_precision') }}" placeholder="ex : enfant de la rue, abandonné, malade chronique...">
              @error('vulnerabilite_precision')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <!-- Étape 2 : Localité -->
          <div class="signal-step">
            <div class="signal-step-header">
              <span class="signal-step-num">2</span>
              <div>
                <h2 class="signal-step-title">Où vit l'enfant ?</h2>
                <p class="signal-step-sub">Sélectionnez la région puis la province.</p>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="region">Région</label>
                <select class="form-select form-select-lg @error('region') is-invalid @enderror" id="region" name="region" required>
                  <option value="">Choisir la région</option>
                  @foreach (array_keys($localites) as $region)
                    <option value="{{ $region }}" {{ old('region') === $region ? 'selected' : '' }}>{{ $region }}</option>
                  @endforeach
                </select>
                @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="province">Province</label>
                <select class="form-select form-select-lg @error('province') is-invalid @enderror" id="province" name="province" data-old="{{ old('province') }}" required disabled>
                  <option value="">Choisir d'abord la région</option>
                </select>
                @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <label class="form-label" for="localite">Ville, village ou quartier</label>
                <input type="text" class="form-control form-control-lg @error('localite') is-invalid @enderror" id="localite" name="localite" value="{{ old('localite') }}" placeholder="ex : Ouagadougou, secteur 15" required>
                @error('localite')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>

          <!-- Étape 3 : Le déclarant -->
          <div class="signal-step">
            <div class="signal-step-header">
              <span class="signal-step-num">3</span>
              <div>
                <h2 class="signal-step-title">Vos informations</h2>
                <p class="signal-step-sub">Pour que nos agents puissent vous recontacter.</p>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="declarant_nom">Votre nom</label>
                <input type="text" class="form-control form-control-lg @error('declarant_nom') is-invalid @enderror" id="declarant_nom" name="declarant_nom" value="{{ old('declarant_nom') }}" required>
                @error('declarant_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="declarant_prenom">Votre prénom(s)</label>
                <input type="text" class="form-control form-control-lg @error('declarant_prenom') is-invalid @enderror" id="declarant_prenom" name="declarant_prenom" value="{{ old('declarant_prenom') }}" required>
                @error('declarant_prenom')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="declarant_telephone">Téléphone</label>
                <input type="tel" class="form-control form-control-lg @error('declarant_telephone') is-invalid @enderror" id="declarant_telephone" name="declarant_telephone" value="{{ old('declarant_telephone') }}" placeholder="ex : 70 00 00 00" required>
                @error('declarant_telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="declarant_profession">Profession</label>
                <input type="text" class="form-control form-control-lg @error('declarant_profession') is-invalid @enderror" id="declarant_profession" name="declarant_profession" value="{{ old('declarant_profession') }}" placeholder="ex : Commerçant, enseignante..." required>
                @error('declarant_profession')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <label class="form-label" for="declarant_adresse">Adresse</label>
                <input type="text" class="form-control form-control-lg @error('declarant_adresse') is-invalid @enderror" id="declarant_adresse" name="declarant_adresse" value="{{ old('declarant_adresse') }}" placeholder="Ville, quartier, repère..." required>
                @error('declarant_adresse')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <label class="form-label mt-4 d-block">Quel est votre lien avec l'enfant ?</label>
            <div class="choice-chips">
              @foreach ($liens as $valeur => $libelle)
                <label class="choice-chip">
                  <input type="radio" name="declarant_lien" value="{{ $valeur }}" data-toggle-precision="lienPrecision" {{ old('declarant_lien') === $valeur ? 'checked' : '' }} required>
                  <span>{{ $libelle }}</span>
                </label>
              @endforeach
            </div>
            @error('declarant_lien')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div id="lienPrecision" class="mt-3 {{ old('declarant_lien') === 'autre' ? '' : 'd-none' }}">
              <label class="form-label" for="declarant_lien_precision">Précisez votre lien</label>
              <input type="text" class="form-control @error('declarant_lien_precision') is-invalid @enderror" id="declarant_lien_precision" name="declarant_lien_precision" value="{{ old('declarant_lien_precision') }}" placeholder="ex : enseignant de l'enfant, agent de santé...">
              @error('declarant_lien_precision')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2">
            <a href="{{ route('public.home') }}" class="btn btn-outline-secondary px-4">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm">
              <i class="bi bi-send-fill me-2"></i> Envoyer le signalement
            </button>
          </div>
        </form>
      </div>

      <div class="col-12 col-lg-4">
        <div class="signal-aside">
          <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h3 class="h6 fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-2"></i> Comment ça marche ?</h3>
            <ol class="signal-howto">
              <li>Remplissez ce formulaire (3 minutes).</li>
              <li>Vous recevez un <strong>numéro de récépissé</strong>. Gardez-le précieusement.</li>
              <li>Un agent examine le signalement.</li>
              <li>Suivez la réponse dans <a href="{{ route('public.suivi') }}">Suivi du signalement</a>.</li>
            </ol>
          </div>

          <div class="card border-0 shadow-sm rounded-4 p-4 bg-primary text-white">
            <h4 class="h6 fw-bold text-uppercase text-white-50 mb-2">Besoin d'aide ?</h4>
            <p class="small mb-3">Contactez le guichet d'assistance OEV en cas de difficulté.</p>
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-telephone-inbound fs-3"></i>
              <div>
                <div class="fw-bold fs-5">+226 25 30 00 00</div>
                <div class="small text-white-50">Appel gratuit - 8h à 16h</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    const localites = @json($localites);
    const region = document.getElementById('region');
    const province = document.getElementById('province');

    function remplirProvinces(selection) {
      const provinces = localites[region.value] || [];
      province.innerHTML = '';
      province.add(new Option(provinces.length ? 'Choisir la province' : "Choisir d'abord la région", ''));
      provinces.forEach(function (nom) {
        province.add(new Option(nom, nom, false, nom === selection));
      });
      province.disabled = provinces.length === 0;
    }

    region.addEventListener('change', function () { remplirProvinces(''); });
    remplirProvinces(province.dataset.old);

    // Affiche le champ « Précisez » quand « Autre » est choisi
    document.querySelectorAll('[data-toggle-precision]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        const bloc = document.getElementById(radio.dataset.togglePrecision);
        const autre = radio.value === 'autre' && radio.checked;
        bloc.classList.toggle('d-none', !autre);
        if (autre) bloc.querySelector('input').focus();
      });
    });
  })();
</script>
@endpush
