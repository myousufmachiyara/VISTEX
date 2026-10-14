@extends('layouts.app')
@section('title', 'Stock Movements')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Stock Movements &amp; Gate Passes</h2>
      <div>
        @can('stock_movements.gate')<a href="{{ route('stock_movements.gate') }}" class="btn btn-outline-warning">Gate Out</a>@endcan
        @can('stock_movements.create')<a href="{{ route('stock_movements.create') }}" class="btn btn-primary">New Movement</a>@endcan
      </div>
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(\App\Models\StockMovement::STATUS_LABELS as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select name="type" class="form-control" onchange="this.form.submit()">
            <option value="">All Types</option>
            @foreach(\App\Models\StockMovement::TYPES as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
          </select>
        </div>
      </form>
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="smTable">
        <thead><tr><th>Date</th><th>Movement #</th><th>From</th><th>To</th><th>Items</th><th class="text-end">Qty</th><th>Mill Lot #</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($movements as $m)
          <tr>
            <td data-order="{{ $m->movement_date->format('Y-m-d') }}">{{ $m->movement_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('stock_movements.show', $m->id) }}" class="text-primary">{{ $m->movement_no }}</a>
              @if($m->purchaseOrder)<div class="small text-muted">{{ $m->purchaseOrder->order_no }}</div>@endif</td>
            <td>{{ $m->fromLocation->name ?? '' }}</td>
            <td>{{ $m->toLocation->name ?? '' }}</td>
            <td class="small">{{ $m->items->map(fn($i) => $i->product->name ?? '')->unique()->implode(', ') }}</td>
            <td class="text-end">{{ number_format($m->items->sum('quantity'), 2) }}</td>
            <td>{{ $m->lot_no ?? '—' }}</td>
            <td><span class="badge bg-{{ $m->status_badge }}">{{ $m->status_label }}</span></td>
            <td class="text-nowrap">
              <a href="{{ route('stock_movements.show', $m->id) }}" class="btn btn-sm btn-outline-primary">View</a>
              @if(in_array($m->status, ['Approved', 'Dispatched', 'Received']))<a href="{{ route('stock_movements.gate_pass', $m->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Gate pass"><i class="fa fa-print"></i></a>@endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#smTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
