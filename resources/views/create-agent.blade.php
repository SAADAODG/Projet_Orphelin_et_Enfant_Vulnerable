@extends('layouts.app')

@section('title', 'Create Agent | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-robot" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Agent Management</p>
        <h1 class="h3 mb-1">Create Agent</h1>
        <p class="text-muted mb-0">Set up a new intelligent agent with capabilities and configurations.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-8">
      <form class="panel needs-validation" novalidate>
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-robot" aria-hidden="true"></i><span>Agent Information</span></h2>
            <p class="text-muted mb-0">Define the agent's core properties and settings.</p>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="agentName">Agent Name</label>
            <input class="form-control" id="agentName" type="text" placeholder="e.g., Customer Support Bot" required>
            <div class="invalid-feedback">Agent name is required.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="agentType">Agent Type</label>
            <select class="form-select" id="agentType" required>
              <option value="">Choose type</option>
              <option>Chatbot</option>
              <option>Task Automation</option>
              <option>Data Analysis</option>
              <option>Content Generation</option>
              <option>Custom</option>
            </select>
            <div class="invalid-feedback">Choose an agent type.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" rows="3" placeholder="Describe what this agent does and its primary responsibilities" required></textarea>
            <div class="invalid-feedback">Description is required.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="team">Assigned Team</label>
            <select class="form-select" id="team" required>
              <option value="">Choose team</option>
              <option>Operations</option>
              <option>Sales</option>
              <option>Content</option>
              <option>Finance</option>
              <option>Support</option>
            </select>
            <div class="invalid-feedback">Choose a team.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="owner">Owner/Manager</label>
            <select class="form-select" id="owner" required>
              <option value="">Choose owner</option>
              <option>Admin Hasan</option>
              <option>Team Lead</option>
              <option>Manager</option>
              <option>Supervisor</option>
            </select>
            <div class="invalid-feedback">Choose an owner.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Capabilities</label>
            <div class="capability-checkboxes">
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap1" value="nlp"><label class="form-check-label" for="cap1">Natural Language Processing</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap2" value="automation"><label class="form-check-label" for="cap2">Task Automation</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap3" value="analytics"><label class="form-check-label" for="cap3">Analytics & Reporting</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap4" value="integration"><label class="form-check-label" for="cap4">API Integration</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap5" value="scheduling"><label class="form-check-label" for="cap5">Scheduling</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="cap6" value="alerts"><label class="form-check-label" for="cap6">Alert Management</label></div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" required>
              <option value="">Choose status</option>
              <option selected>Draft</option>
              <option>Active</option>
              <option>Inactive</option>
            </select>
            <div class="invalid-feedback">Choose a status.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="priority">Priority Level</label>
            <select class="form-select" id="priority" required>
              <option value="">Choose priority</option>
              <option>Low</option>
              <option>Medium</option>
              <option selected>High</option>
              <option>Critical</option>
            </select>
            <div class="invalid-feedback">Choose a priority level.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="notes">Additional Notes</label>
            <textarea class="form-control" id="notes" rows="3" placeholder="Any additional configuration details or special requirements"></textarea>
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Cancel</a>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Create Agent</button>
        </div>
      </form>
    </div>
    <div class="col-12 col-xl-4">
      <div class="panel h-100">
        <h2 class="h5 mb-3 section-title"><i class="bi bi-checklist" aria-hidden="true"></i><span>Setup Checklist</span></h2>
        <div class="activity-list">
          <div class="activity-item"><span class="activity-dot bg-success"></span><div><p class="mb-1 fw-semibold">Define Purpose</p><p class="text-muted small mb-0">Clearly specify agent objectives and use cases.</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-primary"></span><div><p class="mb-1 fw-semibold">Configure Capabilities</p><p class="text-muted small mb-0">Enable features relevant to agent responsibilities.</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-warning"></span><div><p class="mb-1 fw-semibold">Assign Team</p><p class="text-muted small mb-0">Link agent to responsible team for management.</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-info"></span><div><p class="mb-1 fw-semibold">Set Permissions</p><p class="text-muted small mb-0">Configure data access and action rights.</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-secondary"></span><div><p class="mb-1 fw-semibold">Test & Deploy</p><p class="text-muted small mb-0">Validate behavior before production activation.</p></div></div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
