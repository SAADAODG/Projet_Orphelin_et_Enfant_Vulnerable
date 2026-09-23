@extends('layouts.app')

@section('title', 'Blank | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Starter</p>
        <h1 class="h3 mb-1">Blank</h1>
        <p class="text-muted mb-0">A clean starter page for new admin screens.</p>
      </div>
    </div>
  </div>

  <section class="panel blank-panel">
    <div class="blank-state">
      <img class="blank-visual" src="{{ asset('assets/images/png/dasher-ai.png') }}" alt="adminHMD assistant visual">
      <h2 class="h4 mb-2">Blank Page</h2>
      <p class="text-muted mb-4">Start a new admin screen from this clean, responsive page shell.</p>
      <a class="btn btn-primary" href="{{ route('components') }}"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Browse Components</a>
    </div>
  </section>
</div>
@endsection
