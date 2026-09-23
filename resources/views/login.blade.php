@extends('layouts.guest')

@section('title', 'Login | adminHMD')

@section('content')
<section class="auth-card">
  <a class="auth-brand" href="{{ route('dashboard') }}">
    <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
    <span><strong>adminHMD</strong><small>Sign in to your admin workspace.</small></span>
  </a>
  <div class="auth-visual">
    <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="adminHMD dashboard interface">
  </div>
  <form class="needs-validation" novalidate>
    <div class="mb-4">
      <p class="eyebrow mb-1">Secure Access</p>
      <h1 class="h3 mb-1">Login</h1>
      <p class="text-muted mb-0">Sign in to your admin workspace.</p>
    </div>
    <div class="mb-3">
      <label class="form-label" for="loginEmail">Email address</label>
      <input class="form-control" id="loginEmail" type="email" required>
      <div class="invalid-feedback">Enter a valid email.</div>
    </div>
    <div class="mb-3">
      <div class="d-flex justify-content-between">
        <label class="form-label" for="loginPassword">Password</label>
        <a class="small fw-semibold" href="{{ route('forgot-password') }}">Forgot?</a>
      </div>
      <input class="form-control" id="loginPassword" type="password" minlength="6" required>
      <div class="invalid-feedback">Password must be at least 6 characters.</div>
    </div>
    <div class="form-check mb-4">
      <input class="form-check-input" type="checkbox" id="rememberMe">
      <label class="form-check-label" for="rememberMe">Remember me</label>
    </div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In</button>
  </form>

  <div class="auth-footer">New here? <a href="{{ route('register') }}">Create an account</a></div>
</section>
@endsection
