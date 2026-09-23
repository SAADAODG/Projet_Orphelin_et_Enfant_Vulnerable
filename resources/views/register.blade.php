@extends('layouts.guest')

@section('title', 'Register | adminHMD')

@section('content')
<section class="auth-card">
  <a class="auth-brand" href="{{ route('dashboard') }}">
    <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
    <span><strong>adminHMD</strong><small>Create your adminHMD account.</small></span>
  </a>
  <div class="auth-visual">
    <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="adminHMD dashboard interface">
  </div>
  <form class="needs-validation" novalidate>
    <div class="mb-4">
      <p class="eyebrow mb-1">Secure Access</p>
      <h1 class="h3 mb-1">Register</h1>
      <p class="text-muted mb-0">Create your adminHMD account.</p>
    </div>
    <div class="mb-3">
      <label class="form-label" for="registerName">Full name</label>
      <input class="form-control" id="registerName" type="text" required>
      <div class="invalid-feedback">Full name is required.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="registerEmail">Email address</label>
      <input class="form-control" id="registerEmail" type="email" required>
      <div class="invalid-feedback">Enter a valid email.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="registerPassword">Password</label>
      <input class="form-control" id="registerPassword" type="password" minlength="6" required>
      <div class="invalid-feedback">Password must be at least 6 characters.</div>
    </div>
    <div class="form-check mb-4">
      <input class="form-check-input" type="checkbox" id="terms" required>
      <label class="form-check-label" for="terms">I agree to the terms</label>
      <div class="invalid-feedback">You must agree before continuing.</div>
    </div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i> Create Account</button>
  </form>

  <div class="auth-footer">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
</section>
@endsection
