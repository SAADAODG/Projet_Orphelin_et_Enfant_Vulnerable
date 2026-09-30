@extends('layouts.app')

@section('title', 'Paramètres généraux | ' . $siteSetting->structure_nom)

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Configuration</p>
        <h1 class="h3 mb-1">Paramètres généraux</h1>
        <p class="text-muted mb-0">Identité du site, structure en charge, contact et liens rapides affichés sur la plateforme publique.</p>
      </div>
    </div>
  </div>


  <section class="row g-3">
    <div class="col-12 col-xl-8">
      <form method="POST" action="{{ route('parametres.update') }}">
        @csrf
        @method('PUT')

        <div class="panel mb-3">
          <div class="panel-header">
            <div>
              <h2 class="h5 mb-1 section-title"><i class="bi bi-badge-tm" aria-hidden="true"></i><span>Identité du site</span></h2>
              <p class="text-muted mb-0">Le nom et le slogan affichés dans l'en-tête et le pied de page publics.</p>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="nom_site">Nom du site <span class="text-danger">*</span></label>
              <input class="form-control @error('nom_site') is-invalid @enderror" id="nom_site" name="nom_site" type="text" value="{{ old('nom_site', $setting->nom_site) }}" required>
              @error('nom_site')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="slogan">Slogan</label>
              <input class="form-control @error('slogan') is-invalid @enderror" id="slogan" name="slogan" type="text" value="{{ old('slogan', $setting->slogan) }}" placeholder="Ex : Programme OEV – Burkina Faso">
              @error('slogan')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        <div class="panel mb-3">
          <div class="panel-header">
            <div>
              <h2 class="h5 mb-1 section-title"><i class="bi bi-bank" aria-hidden="true"></i><span>Structure en charge</span></h2>
              <p class="text-muted mb-0">L'organisme responsable du programme OEV.</p>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="structure_nom">Sigle <span class="text-danger">*</span></label>
              <input class="form-control @error('structure_nom') is-invalid @enderror" id="structure_nom" name="structure_nom" type="text" value="{{ old('structure_nom', $setting->structure_nom) }}" placeholder="Ex : DGFE" required>
              @error('structure_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
              <label class="form-label" for="structure_nom_complet">Nom complet</label>
              <input class="form-control @error('structure_nom_complet') is-invalid @enderror" id="structure_nom_complet" name="structure_nom_complet" type="text" value="{{ old('structure_nom_complet', $setting->structure_nom_complet) }}" placeholder="Ex : Direction Générale de la Famille et de l'Enfant">
              @error('structure_nom_complet')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
              <label class="form-label" for="ministere_tutelle">Ministère de tutelle</label>
              <input class="form-control @error('ministere_tutelle') is-invalid @enderror" id="ministere_tutelle" name="ministere_tutelle" type="text" value="{{ old('ministere_tutelle', $setting->ministere_tutelle) }}" placeholder="Ex : Ministère de la Famille et de la Solidarité">
              <div class="form-text">Affiché dans l'en-tête et le pied de page du site public.</div>
              @error('ministere_tutelle')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <label class="form-label" for="structure_description">Description</label>
              <textarea class="form-control @error('structure_description') is-invalid @enderror" id="structure_description" name="structure_description" rows="4" placeholder="Mission et rôle de la structure...">{{ old('structure_description', $setting->structure_description) }}</textarea>
              @error('structure_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        <div class="panel mb-3">
          <div class="panel-header">
            <div>
              <h2 class="h5 mb-1 section-title"><i class="bi bi-fonts" aria-hidden="true"></i><span>Apparence</span></h2>
              <p class="text-muted mb-0">Police de caractères, réglable séparément pour l'interface admin et le site public.</p>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="police_admin">Police de l'interface admin</label>
              <select class="form-select @error('police_admin') is-invalid @enderror" id="police_admin" name="police_admin">
                @foreach (config('fonts') as $key => $font)
                  <option value="{{ $key }}" {{ old('police_admin', $setting->police_admin) === $key ? 'selected' : '' }}>{{ $font['label'] }}</option>
                @endforeach
              </select>
              <div class="form-text">Tableau de bord, signalements, OEV, plaintes...</div>
              @error('police_admin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="police_public">Police du site public</label>
              <select class="form-select @error('police_public') is-invalid @enderror" id="police_public" name="police_public">
                @foreach (config('fonts') as $key => $font)
                  <option value="{{ $key }}" {{ old('police_public', $setting->police_public) === $key ? 'selected' : '' }}>{{ $font['label'] }}</option>
                @endforeach
              </select>
              <div class="form-text">Page d'accueil et formulaires publics.</div>
              @error('police_public')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        <div class="panel mb-3">
          <div class="panel-header">
            <div>
              <h2 class="h5 mb-1 section-title"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Contact & Localisation</span></h2>
              <p class="text-muted mb-0">Coordonnées affichées dans le pied de page du site public.</p>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="contact_adresse">Adresse / Localisation</label>
              <input class="form-control @error('contact_adresse') is-invalid @enderror" id="contact_adresse" name="contact_adresse" type="text" value="{{ old('contact_adresse', $setting->contact_adresse) }}" placeholder="Ex : Ouagadougou, Burkina Faso">
              @error('contact_adresse')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label" for="contact_telephone">Téléphone</label>
              <input class="form-control @error('contact_telephone') is-invalid @enderror" id="contact_telephone" name="contact_telephone" type="text" value="{{ old('contact_telephone', $setting->contact_telephone) }}" placeholder="+226 XX XX XX XX">
              @error('contact_telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label" for="contact_email">E-mail</label>
              <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $setting->contact_email) }}" placeholder="contact@oev.gov.bf">
              @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer les paramètres</button>
        </div>
      </form>
    </div>

    <div class="col-12 col-xl-4">
      <div class="panel h-100 settings-preview">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-eye" aria-hidden="true"></i><span>Aperçu public</span></h2>
            <p class="text-muted mb-0">Ce que verront les visiteurs du site.</p>
          </div>
        </div>

        <div class="settings-preview-brand">
          <span class="settings-preview-icon"><i class="bi bi-shield-shaded" aria-hidden="true"></i></span>
          <div>
            <strong>{{ $setting->nom_site }}</strong>
            @if ($setting->slogan)
              <small>{{ $setting->slogan }}</small>
            @endif
          </div>
        </div>

        <div class="settings-preview-block">
          <p class="fw-semibold mb-1">{{ $setting->structure_nom }}@if ($setting->structure_nom_complet) — {{ $setting->structure_nom_complet }}@endif</p>
          <p class="text-muted small mb-0">{{ $setting->structure_description ?: 'Aucune description renseignée.' }}</p>
        </div>

        <div class="settings-preview-block">
          <p class="settings-preview-line"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> {{ $setting->contact_adresse ?: '—' }}</p>
          <p class="settings-preview-line"><i class="bi bi-telephone-fill" aria-hidden="true"></i> {{ $setting->contact_telephone ?: '—' }}</p>
          <p class="settings-preview-line mb-0"><i class="bi bi-envelope-fill" aria-hidden="true"></i> {{ $setting->contact_email ?: '—' }}</p>
        </div>
      </div>
    </div>
  </section>

  <section class="row g-3 mt-1">
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-link-45deg" aria-hidden="true"></i><span>Liens rapides</span></h2>
            <p class="text-muted mb-0">Affichés dans le pied de page du site public, dans cet ordre.</p>
          </div>
        </div>

        <div class="quick-links-list">
          @forelse ($quickLinks as $link)
            <div class="quick-link-row">
              <span class="quick-link-handle text-muted">{{ $loop->iteration }}</span>
              <form class="quick-link-edit" method="POST" action="{{ route('quick-links.update', $link) }}">
                @csrf
                @method('PUT')
                <input class="form-control form-control-sm" type="text" name="libelle" value="{{ $link->libelle }}" placeholder="Libellé" required>
                <input class="form-control form-control-sm" type="text" name="url" value="{{ $link->url }}" placeholder="URL ou chemin (/signaler)" required>
                <button class="btn btn-light btn-sm" type="submit" title="Enregistrer"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
              </form>
              <form method="POST" action="{{ route('quick-links.destroy', $link) }}" data-confirm="Supprimer le lien « {{ $link->libelle }} » ?" data-confirm-danger>
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
              </form>
            </div>
          @empty
            <p class="text-muted small mb-0">Aucun lien rapide pour le moment.</p>
          @endforelse
        </div>

        <hr class="my-3">

        <div class="quick-link-row">
          <span class="quick-link-handle text-muted"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
          <form class="quick-link-edit" method="POST" action="{{ route('quick-links.store') }}">
            @csrf
            <input class="form-control form-control-sm" type="text" name="libelle" placeholder="Libellé (ex : Signaler un OEV)" required>
            <input class="form-control form-control-sm" type="text" name="url" placeholder="URL ou chemin (ex : /signaler)" required>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-grid" aria-hidden="true"></i><span>Services</span></h2>
            <p class="text-muted mb-0">Affichés dans le pied de page du site public, dans cet ordre.</p>
          </div>
        </div>

        <div class="quick-links-list">
          @forelse ($services as $service)
            <div class="quick-link-row">
              <span class="quick-link-handle text-muted">{{ $loop->iteration }}</span>
              <form class="quick-link-edit" method="POST" action="{{ route('services.update', $service) }}">
                @csrf
                @method('PUT')
                <input class="form-control form-control-sm" type="text" name="libelle" value="{{ $service->libelle }}" placeholder="Libellé" required>
                <input class="form-control form-control-sm" type="text" name="url" value="{{ $service->url }}" placeholder="URL ou # si aucune page" required>
                <button class="btn btn-light btn-sm" type="submit" title="Enregistrer"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
              </form>
              <form method="POST" action="{{ route('services.destroy', $service) }}" data-confirm="Supprimer le service « {{ $service->libelle }} » ?" data-confirm-danger>
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Supprimer"><i class="bi bi-trash" aria-hidden="true"></i></button>
              </form>
            </div>
          @empty
            <p class="text-muted small mb-0">Aucun service pour le moment.</p>
          @endforelse
        </div>

        <hr class="my-3">

        <div class="quick-link-row">
          <span class="quick-link-handle text-muted"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
          <form class="quick-link-edit" method="POST" action="{{ route('services.store') }}">
            @csrf
            <input class="form-control form-control-sm" type="text" name="libelle" placeholder="Libellé (ex : Bourses d'études)" required>
            <input class="form-control form-control-sm" type="text" name="url" placeholder="URL ou # si aucune page" required>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
