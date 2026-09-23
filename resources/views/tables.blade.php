@extends('layouts.app')

@section('title', 'Tables | adminHMD')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4">
  <div class="page-heading">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Data</p>
        <h1 class="h3 mb-1">Tables</h1>
        <p class="text-muted mb-0">Use responsive, searchable tables for operational records.</p>
      </div>
    </div>
  </div>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Advanced Table</span></h2>
        <p class="text-muted mb-0">Searchable responsive table for orders and customer data.</p>
      </div>
      <input class="form-control form-control-sm table-search" type="search" placeholder="Search orders" data-table-search="ordersTable" aria-label="Search orders">
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="ordersTable" data-searchable-table>
        <thead>
          <tr><th>Order</th><th>Product</th><th>Customer</th><th>Status</th><th>Amount</th><th>Date</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <tr>
            <td class="fw-semibold">#HMD-2048</td>
            <td>
              <div class="table-media">
                <img class="product-thumb" src="{{ asset('assets/images/ecommerce/product-1.jpg') }}" alt="Wireless Headset">
                <span>Wireless Headset</span>
              </div>
            </td>
            <td>Sarah Ahmed</td>
            <td><span class="badge text-bg-success">Paid</span></td>
            <td>$1,240</td>
            <td>May 6, 2026</td>
            <td class="text-end"><button class="btn btn-light btn-sm" type="button">View</button></td>
          </tr>
          <tr>
            <td class="fw-semibold">#HMD-2047</td>
            <td>
              <div class="table-media">
                <img class="product-thumb" src="{{ asset('assets/images/ecommerce/product-2.jpg') }}" alt="Smart Watch">
                <span>Smart Watch</span>
              </div>
            </td>
            <td>Rafi Khan</td>
            <td><span class="badge text-bg-warning">Pending</span></td>
            <td>$860</td>
            <td>May 5, 2026</td>
            <td class="text-end"><button class="btn btn-light btn-sm" type="button">View</button></td>
          </tr>
          <tr>
            <td class="fw-semibold">#HMD-2046</td>
            <td>
              <div class="table-media">
                <img class="product-thumb" src="{{ asset('assets/images/ecommerce/product-3.jpg') }}" alt="Desk Lamp">
                <span>Desk Lamp</span>
              </div>
            </td>
            <td>Nadia Islam</td>
            <td><span class="badge text-bg-info">Shipped</span></td>
            <td>$430</td>
            <td>May 3, 2026</td>
            <td class="text-end"><button class="btn btn-light btn-sm" type="button">View</button></td>
          </tr>
          <tr>
            <td class="fw-semibold">#HMD-2045</td>
            <td>
              <div class="table-media">
                <img class="product-thumb" src="{{ asset('assets/images/ecommerce/product-4.jpg') }}" alt="Travel Backpack">
                <span>Travel Backpack</span>
              </div>
            </td>
            <td>Mina Torres</td>
            <td><span class="badge text-bg-danger">Failed</span></td>
            <td>$220</td>
            <td>May 2, 2026</td>
            <td class="text-end"><button class="btn btn-light btn-sm" type="button">View</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
