@extends('layouts.app')

@section('title', 'Modals | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Overlay</p>
        <h1 class="h3 mb-1">Modals</h1>
        <p class="text-muted mb-0">Modal dialogs for confirmations and compact workflows.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-window-stack" aria-hidden="true"></i><span>Modal Examples</span></h2>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#confirmModal">Open Confirm Modal</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#formModal">Open Form Modal</button>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Usage</span></h2>
        <p class="text-muted mb-0">Use modals for focused confirmations, compact forms, and high-priority decisions without leaving the page.</p>
      </div>
    </div>
  </section>

  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="confirmModalLabel">Confirm Action</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">This action will update the selected record.</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="formModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form class="needs-validation" novalidate>
          <div class="modal-header">
            <h2 class="modal-title h5" id="formModalLabel">Quick Note</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label" for="modalNote">Note</label>
            <textarea class="form-control" id="modalNote" rows="4" required></textarea>
            <div class="invalid-feedback">A note is required.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Note</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
