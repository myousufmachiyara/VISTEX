@extends('layouts.app')
@section('title', 'Reprocess')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">On Hold for Reprocess</h2>
      @can('purchase_orders.create')<a href="{{ route('reprocess.create') }}" class="btn btn-primary">Send for Reprocess</a>@endcan
    </header>
    <div class="card-body">
      @if($hold->isEmpty())
        <p class="text-muted mb-0">Nothing on hold. QC puts defective meters here; graded stock can also be sent from "Send for Reprocess".</p>
      @else
      <table class="table table-bordered table-sm mb-0">
        <thead><tr><th>Lot #</th><th>Item</th><th class="text-end">On hold (m)</th><th class="text-end">Not yet on a gate pass</th><th></th></tr></thead>
        <tbody>
        @foreach($hold as $h)
          <tr>
            <td>{{ $h['lot_no'] }}</td><td>{{ $h['product'] }}</td>
            <td class="text-end">{{ number_format($h['qty'], 2) }}</td><td class="text-end">{{ number_format($h['free'], 2) }}</td>
            <td>@can('purchase_orders.create')@if($h['free'] > 0)<a class="btn btn-sm btn-outline-primary" href="{{ route('reprocess.create', ['location_id' => $h['location_id'], 'lot_no' => $h['lot_no']]) }}">Send</a>@endif @endcan</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </section>

  <section class="card">
    <header class="card-header"><h2 class="card-title">Reprocess Orders</h2></header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="rpTable">
        <thead><tr><th>Date</th><th>Order #</th><th>Lot #</th><th>Original PO</th><th>Mill</th><th>Type</th><th class="text-end">Sent (m)</th><th class="text-end">Back (m)</th><th>Gate pass</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($orders as $o)
          @php
            $mv = $movements->get($o->id);
          @endphp
          <tr>
            <td data-order="{{ $o->order_date->format('Y-m-d') }}">{{ $o->order_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('purchase_orders.show', $o->id) }}" class="text-primary">{{ $o->order_no }}</a></td>
            <td>{{ $o->reprocess_lot_no }}</td>
            <td>{{ $o->reprocessOf->order_no ?? '' }}</td>
            <td>{{ $o->vendor->name ?? '' }}</td>
            <td>{!! $o->is_foc ? '<span class="badge bg-info">FOC</span>' : '<span class="badge bg-secondary">Charged</span>' !!}</td>
            <td class="text-end">{{ number_format($o->items->sum('quantity'), 2) }}</td>
            <td class="text-end">{{ number_format($o->items->sum('quantity_received'), 2) }}</td>
            <td>
              @if($mv)<a href="{{ route('stock_movements.show', $mv->id) }}">{{ $mv->movement_no }}</a> <span class="badge bg-{{ $mv->status_badge }}">{{ $mv->status_label }}</span>
              @else <span class="text-muted small">after approval</span>@endif
            </td>
            <td>{{ \App\Models\PurchaseOrder::STATUSES[$o->status] ?? $o->status }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#rpTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
