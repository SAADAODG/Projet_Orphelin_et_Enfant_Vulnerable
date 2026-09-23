@extends('layouts.app')

@section('title', 'Users | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Management</p>
        <h1 class="h3 mb-1">Users</h1>
        <p class="text-muted mb-0">Review accounts, roles, account status, and team ownership.</p>
      </div>
    </div>
    <div class="heading-actions">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('tables') }}"><i class="bi bi-download" aria-hidden="true"></i> Export</a>
      <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</a>
    </div>
  </div>

  <section class="row g-3 mt-1" aria-label="User summary">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Total Users</span>
          <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value">8,742</div>
        <div class="metric-meta">
          <span class="text-success">+5.1%</span>
          <span>this month</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-success">
        <div class="metric-top">
          <span class="metric-label">Active</span>
          <span class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value">7,980</div>
        <div class="metric-meta">
          <span class="text-success">91%</span>
          <span>healthy accounts</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-warning">
        <div class="metric-top">
          <span class="metric-label">Pending</span>
          <span class="metric-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value">184</div>
        <div class="metric-meta">
          <span class="text-warning">12</span>
          <span>need approval</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-danger">
        <div class="metric-top">
          <span class="metric-label">Suspended</span>
          <span class="metric-icon"><i class="bi bi-slash-circle" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value">38</div>
        <div class="metric-meta">
          <span class="text-danger">4</span>
          <span>flagged today</span>
        </div>
      </article>
    </div>
  </section>

  <section class="panel mt-3">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>User List</span></h2>
        <p class="text-muted mb-0">Search, review, and manage team member accounts.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <input class="form-control form-control-sm table-search" type="search" placeholder="Search users" data-table-search="usersTable" aria-label="Search users">
        <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
        <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Team</th><th scope="col">Status</th><th scope="col">Joined</th><th scope="col" class="text-end">Action</th></tr></thead>
        <tbody>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar-1.jpg') }}" alt="Sarah Ahmed">
                <div>
                  <p class="fw-semibold mb-0">Sarah Ahmed</p>
                  <p class="text-muted small mb-0">sarah@example.com</p>
                </div>
              </div>
            </td>
            <td>Admin</td>
            <td>Operations</td>
            <td><span class="badge text-bg-success">Active</span></td>
            <td>Jan 12, 2026</td>
            <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('users.show', 1) }}">View</a></td>
          </tr>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar-2.jpg') }}" alt="Rafi Khan">
                <div>
                  <p class="fw-semibold mb-0">Rafi Khan</p>
                  <p class="text-muted small mb-0">rafi@example.com</p>
                </div>
              </div>
            </td>
            <td>Manager</td>
            <td>Sales</td>
            <td><span class="badge text-bg-success">Active</span></td>
            <td>Feb 03, 2026</td>
            <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('users.show', 2) }}">View</a></td>
          </tr>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar-3.jpg') }}" alt="Nadia Islam">
                <div>
                  <p class="fw-semibold mb-0">Nadia Islam</p>
                  <p class="text-muted small mb-0">nadia@example.com</p>
                </div>
              </div>
            </td>
            <td>Editor</td>
            <td>Content</td>
            <td><span class="badge text-bg-warning">Pending</span></td>
            <td>Mar 18, 2026</td>
            <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('users.show', 3) }}">View</a></td>
          </tr>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar-4.jpg') }}" alt="Mina Torres">
                <div>
                  <p class="fw-semibold mb-0">Mina Torres</p>
                  <p class="text-muted small mb-0">mina@example.com</p>
                </div>
              </div>
            </td>
            <td>Viewer</td>
            <td>Finance</td>
            <td><span class="badge text-bg-secondary">Suspended</span></td>
            <td>Apr 07, 2026</td>
            <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('users.show', 4) }}">View</a></td>
          </tr>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="avatar-img avatar-sm" src="{{ asset('assets/images/avatar/avatar-5.jpg') }}" alt="Jon Oliver">
                <div>
                  <p class="fw-semibold mb-0">Jon Oliver</p>
                  <p class="text-muted small mb-0">jon@example.com</p>
                </div>
              </div>
            </td>
            <td>Analyst</td>
            <td>Data</td>
            <td><span class="badge text-bg-success">Active</span></td>
            <td>Apr 22, 2026</td>
            <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('users.show', 5) }}">View</a></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-3">
      <p class="text-muted small mb-0">Showing 1 to 5 of 124 users</p>
      <nav aria-label="Users pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
          <li class="page-item active"><a class="page-link" href="#">1</a></li>
          <li class="page-item"><a class="page-link" href="#">2</a></li>
          <li class="page-item"><a class="page-link" href="#">Next</a></li>
        </ul>
      </nav>
    </div>
  </section>
</div>
@endsection
