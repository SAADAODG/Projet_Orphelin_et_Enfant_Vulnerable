@extends('layouts.guest')

@section('title', 'Ministère de la Famille et de la Solidarité | Erreur')

@section('content')
<div class="error-page">
  <section class="error-card">
    <a class="auth-brand justify-content-center" href="{{ auth()->check() ? route('dashboard') : route('public.home') }}">
      <span class="brand-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
      <span><strong>OEV</strong><small>Programme OEV</small></span>
    </a>
    <img class="error-illustration" src="{{ asset('assets/images/svg/maintenance.svg') }}" alt="">
    <h1 class="h3 mb-2">Un problème est survenu</h1>
    <p class="text-muted mb-4">La page n’a pas pu s’afficher. Réessayez dans un instant ; si le problème persiste, contactez l’administrateur.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      @auth
        <a class="btn btn-primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i> Retour au tableau de bord</a>
      @else
        <a class="btn btn-primary" href="{{ route('public.home') }}"><i class="bi bi-house" aria-hidden="true"></i> Retour à l’accueil</a>
      @endauth
      <a class="btn btn-outline-secondary" href="javascript:history.back()">Page précédente</a>
    </div>
  </section>
</div>
@endsection
