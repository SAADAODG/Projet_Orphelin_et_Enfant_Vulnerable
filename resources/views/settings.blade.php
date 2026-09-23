@extends('layouts.app')

@section('title', 'Settings | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Workspace</p>
        <h1 class="h3 mb-1">Settings</h1>
        <p class="text-muted mb-0">Customize workspace defaults, security options, and notification preferences.</p>
      </div>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-6">
      <form class="panel needs-validation" novalidate>
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-sliders" aria-hidden="true"></i><span>General Settings</span></h2>
            <p class="text-muted mb-0">Configure workspace identity and defaults.</p>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="workspaceName">Workspace name</label>
          <input class="form-control" id="workspaceName" type="text" value="adminHMD Workspace" required>
          <div class="invalid-feedback">Workspace name is required.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="defaultLanguage">Default language</label>
          <select class="form-select" id="defaultLanguage">
            <option selected>English</option>
            <option>Bangla</option>
            <option>Spanish</option>
          </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Save Settings</button>
      </form>
    </div>
    <div class="col-12 col-xl-6">
      <div class="panel h-100">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-toggles2" aria-hidden="true"></i><span>Preferences</span></h2>
            <p class="text-muted mb-0">Control notifications and security options.</p>
          </div>
        </div>
        <div class="settings-list">
          <label class="settings-row">
            <span><strong>Email alerts</strong><small>Receive important account updates.</small></span>
            <input class="form-check-input" type="checkbox" checked>
          </label>
          <label class="settings-row">
            <span><strong>Weekly reports</strong><small>Send summary reports every Monday.</small></span>
            <input class="form-check-input" type="checkbox" checked>
          </label>
          <label class="settings-row">
            <span><strong>Two-factor authentication</strong><small>Require extra verification for admins.</small></span>
            <input class="form-check-input" type="checkbox">
          </label>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
