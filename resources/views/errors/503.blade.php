@extends('layouts.guest')

@section('title', 'Ministère de la Famille et de la Solidarité | Maintenance')

{{-- Affichée pendant la maintenance (php artisan down). Le middleware de sécurité et la session ne sont pas
     encore passés : pas de $cspNonce ni de script propre à la page. --}}
@section('content')
<div class="error-page">
  <section class="error-card">
    <span class="auth-brand justify-content-center">
      <span class="brand-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
      <span><strong>OEV</strong><small>Programme OEV</small></span>
    </span>
    <img class="error-illustration" src="{{ asset('assets/images/svg/maintenance.svg') }}" alt="">
    <h1 class="h3 mb-2">Plateforme en maintenance</h1>
    <p class="text-muted mb-4">La plateforme est momentanément indisponible pour une opération de maintenance. Merci de revenir dans quelques instants.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <a class="btn btn-primary" href="{{ url()->current() }}"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Réessayer</a>
    </div>
  </section>
</div>
@endsection
