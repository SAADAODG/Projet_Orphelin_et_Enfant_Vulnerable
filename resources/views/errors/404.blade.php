@extends('layouts.guest')

@section('title', 'Ministère de la Famille et de la Solidarité | Page introuvable')

{{-- Rarement affichée : une page introuvable ramène normalement à la page précédente avec une alerte (App\Exceptions\RetourApresErreur) --}}
@section('content')
<div class="error-page">
  <section class="error-card">
    <a class="auth-brand justify-content-center" href="{{ auth()->check() ? route('dashboard') : route('public.home') }}">
      <span class="brand-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
      <span><strong>OEV</strong><small>Programme OEV</small></span>
    </a>
    <img class="error-illustration" src="{{ asset('assets/images/svg/404.svg') }}" alt="">
    <h1 class="h3 mb-2">Page introuvable</h1>
    <p class="text-muted mb-4">La page demandée n’existe pas ou a été déplacée.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      @auth
        <a class="btn btn-primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i> Retour au tableau de bord</a>
      @else
        <a class="btn btn-primary" href="{{ route('public.home') }}"><i class="bi bi-house" aria-hidden="true"></i> Retour à l’accueil</a>
      @endauth
    </div>
  </section>
</div>
@endsection
