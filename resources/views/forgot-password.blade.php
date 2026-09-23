@extends('layouts.guest')

@section('title', 'Forgot Password | adminHMD')

@section('content')
<section class="auth-card">
  <a class="auth-brand" href="{{ route('dashboard') }}">
    <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
    <span><strong>adminHMD</strong><small>Get a reset link for your account.</small></span>
  </a>
  <div class="auth-visual">
    <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="adminHMD dashboard interface">
  </div>
  <form class="needs-validation" novalidate>
    <div class="mb-4">
      <p class="eyebrow mb-1">Secure Access</p>
      <h1 class="h3 mb-1">Forgot Password</h1>
      <p class="text-muted mb-0">Get a reset link for your account.</p>
    </div>
    <div class="mb-4">
      <label class="form-label" for="forgotEmail">Email address</label>
      <input class="form-control" id="forgotEmail" type="email" required>
      <div class="invalid-feedback">Enter a valid email.</div>
    </div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-envelope-arrow-up" aria-hidden="true"></i> Send Reset Link</button>
  </form>
  <p class="text-muted small mt-3 mb-0">Check your inbox and spam folder after submitting.</p>
  <div class="auth-footer">Remembered it? <a href="{{ route('login') }}">Back to login</a></div>
</section>
@endsection
