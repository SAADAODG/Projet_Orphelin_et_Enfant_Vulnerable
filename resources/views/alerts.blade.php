@extends('layouts.app')

@section('title', 'Alerts | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Feedback</p>
        <h1 class="h3 mb-1">Alerts</h1>
        <p class="text-muted mb-0">System feedback states for success, warning, info, and error messages.</p>
      </div>
    </div>
  </div>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>Alert Styles</span></h2>
        <p class="text-muted mb-0">Common notification patterns for admin workflows.</p>
      </div>
    </div>
    <div class="alert alert-primary" role="alert"><strong>Info:</strong> New dashboard reports are ready to review.</div>
    <div class="alert alert-success" role="alert"><strong>Success:</strong> User permissions were updated successfully.</div>
    <div class="alert alert-warning" role="alert"><strong>Warning:</strong> Billing information needs attention.</div>
    <div class="alert alert-danger mb-0" role="alert"><strong>Error:</strong> Some integrations could not sync.</div>
  </section>
</div>
@endsection
