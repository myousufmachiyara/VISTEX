@extends('layouts.app')
@section('title', 'Cutting & Packing')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Cutting &amp; Packing</h2>
      <div class="d-flex gap-2">
        <form method="GET">
          <select name="type" class="form-control" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach(\App\Models\ProductionOrder::TYPES as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
          </select>
        </form>
        @can('production_orders.create')
          <a href="{{ route('production.create', 'cutting') }}" class="btn btn-primary">New Cutting</a>
          <a href="{{ route('production.create', 'packing') }}" class="btn btn-outline-primary">New Packing</a>
        @endcan
      </div>
    </header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="prTable">
        <thead><tr><th>Date</th><th>No.</th><th>Type</th><th>Warehouse</th><th>Customer Order</th><th>Output</th><th class="text-end">Pieces</th><th class="text-end">Value</th></tr></thead>
        <tbody>
        @foreach($orders as $o)
          <tr>
            <td data-order="{{ $o->order_date->format('Y-m-d') }}">{{ $o->order_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('production.show', $o->id) }}" class="text-primary">{{ $o->order_no }}</a></td>
            <td><span class="badge bg-{{ $o->type === 'cutting' ? 'warning text-dark' : 'success' }}">{{ $o->type_label }}</span></td>
            <td>{{ $o->location->name ?? '' }}</td>
            <td>{{ $o->job->job_no ?? '—' }}</td>
            <td class="small">{{ $o->lines->map(fn($l) => $l->outputProduct->name ?? '')->unique()->implode(', ') }}</td>
            <td class="text-end">{{ number_format($o->lines->sum('output_qty')) }}</td>
            <td class="text-end">{{ number_format($o->lines->sum('output_amount'), 2) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#prTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
