@extends('layouts.app')
@section('title', 'Customer Orders')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Customer Orders (Jobs)</h2>
      @can('jobs.create')<a href="{{ route('jobs.create') }}" class="btn btn-primary">New Customer Order</a>@endcan
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['Pending', 'Approved', 'Rejected'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach
          </select>
        </div>
      </form>
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="jobTable">
        <thead><tr><th>Date</th><th>Order #</th><th>Customer</th><th>Customer PO #</th><th>Collection</th><th class="text-end">Lines</th><th class="text-end">Qty (m)</th><th class="text-end">Total</th><th>Delivery</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($jobs as $job)
          <tr>
            <td data-order="{{ $job->order_date->format('Y-m-d') }}">{{ $job->order_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('jobs.show', $job->id) }}" class="text-primary">{{ $job->job_no }}</a></td>
            <td>{{ $job->customer->name ?? '' }}</td>
            <td>{{ $job->customer_po_number ?? '—' }}</td>
            <td class="small">{{ $job->items->pluck('collection')->filter()->unique()->implode(', ') }}</td>
            <td class="text-end">{{ $job->items->count() }}</td>
            <td class="text-end">{{ number_format($job->total_meters, 2) }}</td>
            <td class="text-end text-nowrap">{{ $job->currency ?? 'PKR' }} {{ number_format($job->total_amount, 2) }}</td>
            <td data-order="{{ $job->expected_date?->format('Y-m-d') }}">{{ $job->expected_date?->format('d-M-Y') ?? '—' }}</td>
            <td><span class="badge bg-{{ match($job->status) { 'Approved' => 'success', 'Rejected' => 'danger', default => 'warning text-dark' } }}">{{ $job->status }}</span></td>
            <td class="text-nowrap">
              <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-sm btn-outline-primary">View</a>
              @can('jobs.edit')@if($job->isEditable())<a href="{{ route('jobs.edit', $job->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endif @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#jobTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
