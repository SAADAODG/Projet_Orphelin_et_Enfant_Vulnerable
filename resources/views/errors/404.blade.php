@extends('layouts.guest')

@section('title', 'Ministère de la Famille et de la Solidarité | Page introuvable')

@section('content')
<div class="error-page">
  <section class="error-card">
    <a class="auth-brand justify-content-center" href="{{ route('dashboard') }}">
      <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
      <span><strong>adminHMD</strong><small>Error Center</small></span>
    </a>
    <img class="error-illustration" src="{{ asset('assets/images/svg/404.svg') }}" alt="Page not found illustration">
    <div class="error-code">404</div>
    <h1 class="h3 mb-2">Page Not Found</h1>
    <p class="text-muted mb-4">The page you are looking for does not exist or has been moved.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <a class="btn btn-primary" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2" aria-hidden="true"></i> Back to Dashboard</a>
      <a class="btn btn-outline-secondary" href="{{ route('login') }}">Sign In</a>
    </div>
  </section>
</div>
@endsection
