@extends('layouts.app')

@section('title', 'Forms | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Inputs</p>
        <h1 class="h3 mb-1">Forms</h1>
        <p class="text-muted mb-0">Reusable form controls, validation states, and field layouts.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-7">
      <form class="panel needs-validation" novalidate>
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i><span>Validation Form</span></h2>
            <p class="text-muted mb-0">Bootstrap-ready fields with custom validation feedback.</p>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="formName">Full name</label>
            <input class="form-control" id="formName" required>
            <div class="invalid-feedback">Full name is required.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="formEmail">Email</label>
            <input class="form-control" id="formEmail" type="email" required>
            <div class="invalid-feedback">Valid email is required.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="formPlan">Plan</label>
            <select class="form-select" id="formPlan" required>
              <option value="">Choose plan</option>
              <option>Starter</option>
              <option>Business</option>
              <option>Enterprise</option>
            </select>
            <div class="invalid-feedback">Choose a plan.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="formBudget">Budget</label>
            <input class="form-control" id="formBudget" type="number" min="1" required>
            <div class="invalid-feedback">Enter a valid budget.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="formMessage">Message</label>
            <textarea class="form-control" id="formMessage" rows="5" required></textarea>
            <div class="invalid-feedback">Message is required.</div>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-primary" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Submit Form</button>
        </div>
      </form>
    </div>
    <div class="col-12 col-xl-5">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-input-cursor-text" aria-hidden="true"></i><span>Input States</span></h2>
        <input class="form-control mb-3" value="Default input">
        <input class="form-control is-valid mb-3" value="Valid input">
        <input class="form-control is-invalid mb-3" value="Invalid input">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="sampleCheck" checked>
          <label class="form-check-label" for="sampleCheck">Sample checkbox</label>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
