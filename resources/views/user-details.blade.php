@extends('layouts.app')

@section('title', 'User Details | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Management</p>
        <h1 class="h3 mb-1">User Details</h1>
        <p class="text-muted mb-0">Inspect account status, profile data, permissions, and recent activity.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('users.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Users</a>
      <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</a>
    </div>
  </div>

  <section class="row g-3">
    <div class="col-12 col-xl-4">
      <div class="panel h-100 text-center profile-card">
        <div class="profile-cover">
          <img src="{{ asset('assets/images/png/dasher-ui-bootstrap-5.jpg') }}" alt="User workspace preview">
        </div>
        <div class="profile-hero">
          <img class="avatar-img avatar-xl profile-photo" src="{{ asset('assets/images/avatar/avatar-1.jpg') }}" alt="Sarah Ahmed">
          <h2 class="h5 mb-1">Sarah Ahmed</h2>
          <p class="text-muted mb-3">Senior Administrator</p>
          <span class="badge text-bg-success">Active Account</span>
        </div>
        <div class="info-list mt-4 text-start">
          <div><span>Email</span><strong>sarah@example.com</strong></div>
          <div><span>Phone</span><strong>+1 555 0184</strong></div>
          <div><span>Team</span><strong>Operations</strong></div>
          <div><span>Location</span><strong>New York, USA</strong></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-8">
      <div class="panel mb-3">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><span>Account Overview</span></h2>
            <p class="text-muted mb-0">Permissions, plan, and current access details.</p>
          </div>
          <button class="btn btn-primary btn-sm" type="button">Edit User</button>
        </div>
        <div class="row g-3">
          <div class="col-md-4"><div class="mini-card"><span>Role</span><strong>Admin</strong></div></div>
          <div class="col-md-4"><div class="mini-card"><span>Last Login</span><strong>Today</strong></div></div>
          <div class="col-md-4"><div class="mini-card"><span>Projects</span><strong>14 Active</strong></div></div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-header">
          <div>
            <h2 class="h5 mb-1 section-title"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Recent Activity</span></h2>
            <p class="text-muted mb-0">Latest security and workflow events.</p>
          </div>
        </div>
        <div class="activity-list">
          <div class="activity-item"><span class="activity-dot bg-primary"></span><div><p class="mb-1 fw-semibold">Updated billing permissions</p><p class="text-muted small mb-0">2 hours ago</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-success"></span><div><p class="mb-1 fw-semibold">Approved new teammate</p><p class="text-muted small mb-0">Yesterday</p></div></div>
          <div class="activity-item"><span class="activity-dot bg-warning"></span><div><p class="mb-1 fw-semibold">Changed password</p><p class="text-muted small mb-0">Apr 30, 2026</p></div></div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
