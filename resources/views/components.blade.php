@extends('layouts.app')

@section('title', 'Components | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">UI Kit</p>
        <h1 class="h3 mb-1">Components</h1>
        <p class="text-muted mb-0">Buttons, badges, progress, accordions, and reusable interface blocks.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-cursor" aria-hidden="true"></i><span>Buttons</span></h2>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="button">Primary</button>
          <button class="btn btn-outline-secondary" type="button">Secondary</button>
          <button class="btn btn-success" type="button">Success</button>
          <button class="btn btn-danger" type="button">Danger</button>
          <button class="btn btn-warning" type="button">Warning</button>
        </div>
        <h2 class="h5 mt-4 mb-3">Badges</h2>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge text-bg-primary">Primary</span>
          <span class="badge text-bg-success">Success</span>
          <span class="badge text-bg-warning">Warning</span>
          <span class="badge text-bg-danger">Danger</span>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-reception-4" aria-hidden="true"></i><span>Progress</span></h2>
        <div class="progress mb-3"><div class="progress-bar progress-72" role="progressbar" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">72%</div></div>
        <div class="progress mb-3"><div class="progress-bar bg-success progress-58" role="progressbar" aria-valuenow="58" aria-valuemin="0" aria-valuemax="100">58%</div></div>
        <div class="progress"><div class="progress-bar bg-warning progress-42" role="progressbar" aria-valuenow="42" aria-valuemin="0" aria-valuemax="100">42%</div></div>
      </div>
    </div>
    <div class="col-12">
      <div class="panel">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-menu-button-wide" aria-hidden="true"></i><span>Accordion</span></h2>
        <div class="accordion" id="componentAccordion">
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">Reusable layout</button></h3>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#componentAccordion">
              <div class="accordion-body">Use the admin shell, panel, metric-card, and table styles for consistent pages.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">Responsive behavior</button></h3>
            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#componentAccordion">
              <div class="accordion-body">The sidebar becomes a mobile overlay and a compact mini sidebar on desktop.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
