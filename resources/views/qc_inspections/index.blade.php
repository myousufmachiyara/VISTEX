@extends('layouts.app')
@section('title', 'QC — Processed Fabric')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <section class="card mb-3">
    <header class="card-header"><h2 class="card-title">Awaiting QC <span class="badge bg-warning text-dark">{{ $awaiting->count() }}</span></h2></header>
    <div class="card-body">
      @if($awaiting->isEmpty())
        <p class="text-muted mb-0">No processed fabric is waiting for QC.</p>
      @else
      <div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead><tr><th>Received</th><th>GRN #</th><th>Processing PO / Lot</th><th>Mill</th><th class="text-end">A</th><th class="text-end">B</th><th class="text-end">C</th><th class="text-end">Total m</th><th></th></tr></thead>
        <tbody>
        @foreach($awaiting as $r)
          @php
            $a = $r->items->sum('qty_a'); $b = $r->items->sum('qty_b'); $c = $r->items->sum('qty_c');
          @endphp
          <tr>
            <td>{{ $r->receiving_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('purchase_receivings.show', $r->id) }}">{{ $r->receiving_no }}</a></td>
            <td>{{ $r->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $r->purchaseOrder->vendor->name ?? '' }}</td>
            <td class="text-end">{{ number_format($a, 2) }}</td>
            <td class="text-end">{{ number_format($b, 2) }}</td>
            <td class="text-end">{{ number_format($c, 2) }}</td>
            <td class="text-end fw-bold">{{ number_format($a + $b + $c, 2) }}</td>
            <td>
              @can('qc_inspections.create')
              <form method="POST" action="{{ route('qc_inspections.start', $r->id) }}">@csrf<button class="btn btn-sm btn-primary">Start QC</button></form>
              @endcan
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
      @endif
    </div>
  </section>

  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">QC Inspections</h2>
      <form method="GET">
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="Open" @selected(request('status') === 'Open')>Open</option>
          <option value="Closed" @selected(request('status') === 'Closed')>Closed</option>
        </select>
      </form>
    </header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="qcTable">
        <thead><tr><th>Date</th><th>QC #</th><th>GRN #</th><th>Lot (PO #)</th><th>Mill</th><th class="text-end">A</th><th class="text-end">B</th><th class="text-end">C</th><th class="text-end">Reprocess</th><th class="text-end">Short</th><th class="text-end">Claim</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($inspections as $q)
          <tr>
            <td data-order="{{ $q->inspection_date->format('Y-m-d') }}">{{ $q->inspection_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('qc_inspections.show', $q->id) }}" class="text-primary">{{ $q->qc_no }}</a></td>
            <td>{{ $q->receiving->receiving_no ?? '' }}</td>
            <td>{{ $q->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $q->vendor->name ?? '' }}</td>
            <td class="text-end">{{ number_format($q->items->sum('final_a'), 2) }}</td>
            <td class="text-end">{{ number_format($q->items->sum('final_b'), 2) }}</td>
            <td class="text-end">{{ number_format($q->items->sum('final_c'), 2) }}</td>
            <td class="text-end">{{ number_format($q->items->sum('reprocess_qty'), 2) }}</td>
            <td class="text-end">{{ number_format($q->items->sum('shortage_qty'), 2) }}</td>
            <td class="text-end">{{ number_format($q->claim_amount + $q->shortage_amount, 2) }}</td>
            <td><span class="badge bg-{{ $q->status_badge }}">{{ $q->status }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#qcTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
