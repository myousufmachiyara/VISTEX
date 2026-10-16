@extends('layouts.app')
@section('title', $movement->movement_no)
@section('content')
@php
  $m = $movement;
  $u = auth()->user();
  $steps = [
    ['Requested', $m->creator?->name, $m->created_at],
    ['Approved — gate pass issued', $m->approver?->name, $m->approved_at],
    [$m->needsGateExit() ? 'Left through the gate' : 'Left the mill', $m->dispatcher?->name, $m->dispatched_at],
    ['Received', $m->receiver?->name, $m->received_at],
  ];
@endphp
<div class="row"><div class="col-lg-9">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">{{ $m->movement_no }}</h2>
      <span class="badge bg-{{ $m->status_badge }}">{{ $m->status_label }}</span>
    </header>
    <div class="card-body">
      @if($m->status === 'Rejected')<div class="alert alert-danger"><strong>Rejected:</strong> {{ $m->rejection_reason }}</div>@endif

      <div class="row mb-2">
        <div class="col-md-4 mb-2"><strong>Type:</strong> {{ $m->type_label }}</div>
        <div class="col-md-4 mb-2"><strong>From:</strong> {{ $m->fromLocation->name ?? '' }}@if($m->fromLocation?->vendor) <small class="text-muted">({{ $m->fromLocation->vendor->name }})</small>@endif</div>
        <div class="col-md-4 mb-2"><strong>To:</strong> {{ $m->destination_name }}@if($m->isSale() && $m->saleOrder) <small class="text-muted">(Sale <a href="{{ route('sale_orders.show', $m->sale_order_id) }}">{{ $m->saleOrder->so_no }}</a>)</small>@endif @if($m->job) <small class="text-muted">(Order <a href="{{ route('jobs.show', $m->job_id) }}">{{ $m->job->job_no }}</a>)</small>@endif @if($m->toLocation?->vendor) <small class="text-muted">({{ $m->toLocation->vendor->name }})</small>@endif</div>
        <div class="col-md-4 mb-2"><strong>Date:</strong> {{ $m->movement_date->format('d-M-Y') }}</div>
        @if($m->purchaseOrder)<div class="col-md-4 mb-2"><strong>Processing PO:</strong> <a href="{{ route('purchase_orders.show', $m->purchase_order_id) }}">{{ $m->purchaseOrder->order_no }}</a></div>@endif
        @if($m->lot_no)<div class="col-md-4 mb-2"><strong>Mill Lot #:</strong> <span class="badge bg-dark">{{ $m->lot_no }}</span></div>@endif
        @if($m->vehicle_no || $m->driver_name || $m->driver_contact)
          <div class="col-md-8 mb-2"><strong>Transport:</strong> {{ collect([$m->vehicle_no ? 'Vehicle ' . $m->vehicle_no : null, $m->driver_name ? 'Driver ' . $m->driver_name : null, $m->driver_contact])->filter()->implode(' · ') }}</div>
        @endif
        @if($m->remarks)<div class="col-12 mb-2"><strong>Remarks:</strong> {{ $m->remarks }}</div>@endif
        @if($m->gate_remarks)<div class="col-12 mb-2"><strong>Gate remarks:</strong> {{ $m->gate_remarks }}</div>@endif
        @if($m->receive_remarks)<div class="col-12 mb-2"><strong>Receiving remarks:</strong> {{ $m->receive_remarks }}</div>@endif
      </div>

      <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th>#</th><th>Item</th><th>Lot (source)</th><th class="text-end">Quantity</th><th class="text-end">Value</th></tr></thead>
        <tbody>
          @foreach($m->items as $n => $item)
            @php $taken = $lots[$item->product_id] ?? collect(); @endphp
            <tr>
              <td>{{ $n + 1 }}</td>
              <td>{{ $item->product->name ?? '' }}
                @if($item->source_status === 'reprocess' || $item->source_grade)<div class="small text-muted">{{ $item->source_label }}</div>@endif
              </td>
              <td class="small">
                @if($taken->isNotEmpty())
                  {{ $taken->map(fn($r) => ($r->lot_no ?? 'no lot') . ': ' . rtrim(rtrim(number_format(-$r->quantity, 3), '0'), '.'))->implode(' · ') }}
                @else
                  {{ $item->source_lot_no ?? 'Any (oldest first)' }}
                @endif
              </td>
              <td class="text-end">{{ number_format($item->quantity, 3) }} {{ $item->product->measurementUnit->shortcode ?? '' }}</td>
              <td class="text-end">{{ (float) $item->amount ? number_format($item->amount, 2) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      @if(!empty($m->gate_images))
        <h6>Gate Photos</h6>
        <div class="d-flex flex-wrap gap-2 mb-3">@foreach($m->gate_images as $img)<a href="{{ \App\Support\Media::url($img) }}" target="_blank"><img src="{{ \App\Support\Media::url($img) }}" style="height:90px" class="border rounded"></a>@endforeach</div>
      @endif

      {{-- ── Actions ── --}}
      @if($m->status === 'Pending' && $m->canBeApprovedBy($u))
        <div class="border rounded p-3 mb-3 bg-light d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <span>Approve this movement? {{ $m->needsGateExit() ? 'A gate pass will be issued for the gate.' : 'The goods will be marked as on their way.' }}</span>
          <div>
            <form action="{{ route('stock_movements.approve', $m->id) }}" method="POST" class="d-inline">@csrf<button class="btn btn-success">Approve</button></form>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
          </div>
        </div>
      @endif

      @if($m->status === 'Approved')
        @can('stock_movements.gate')
          <form action="{{ route('stock_movements.gate_out', $m->id) }}" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3" id="gateOut">
            @csrf
            <h6 class="mb-1 text-dark" style="font-size:.95rem;font-weight:600">Gate Check — Challan OUT</h6>
            <p class="small text-muted">Count the goods against the list above before letting them out. Stock leaves {{ $m->fromLocation->name ?? '' }} when you confirm.</p>
            @include('challans._transport', ['challan' => null])
            <div class="row">
              <div class="col-md-6 mb-3"><label>Photos</label><input type="file" name="gate_images[]" class="form-control" accept="image/*" multiple capture="environment"></div>
              <div class="col-md-6 mb-3"><label>Remarks</label><input type="text" name="gate_remarks" class="form-control" maxlength="1000" value="{{ old('gate_remarks') }}"></div>
            </div>
            <button class="btn btn-primary" onclick="return confirm('Goods counted and correct? Confirm they are leaving now.')">Verified — Let Out</button>
          </form>
        @endcan
        @if($m->canBeApprovedBy($u))
          <p class="small"><a href="#" data-bs-toggle="modal" data-bs-target="#rejectModal" class="text-danger">Cancel this gate pass</a></p>
        @endif
      @endif

      @if($m->status === 'Dispatched' && $m->canBeReceivedBy($u))
        <form action="{{ route('stock_movements.receive', $m->id) }}" method="POST" class="border rounded p-3 mb-3 bg-light">
          @csrf
          <h6 class="mb-2">Receive at {{ $m->toLocation->name ?? '' }}</h6>
          <div class="row">
            <div class="col-md-4 mb-3">
              @if($m->isReprocess())
              <label>Lot #</label>
              <div class="form-control-plaintext"><span class="badge bg-dark">{{ $m->purchaseOrder->stock_lot_no }}</span> <small class="text-muted">kept for reprocessing; goes straight into process</small></div>
              @else
              <label>Mill Lot # @if($m->needsMillLot())<span class="text-danger">*</span>@else<small class="text-muted">(optional)</small>@endif</label>
              <input type="text" name="lot_no" class="form-control" maxlength="100" value="{{ old('lot_no') }}" @if($m->needsMillLot()) required placeholder="Lot # given by the mill" @endif>
              @endif
            </div>
            <div class="col-md-8 mb-3"><label>Remarks</label><input type="text" name="remarks" class="form-control" maxlength="1000" value="{{ old('remarks') }}"></div>
          </div>
          <button class="btn btn-success">Confirm Received</button>
        </form>
      @elseif($m->status === 'Dispatched')
        <div class="alert alert-info py-2">In transit — waiting for {{ $m->toLocation->name ?? 'the destination' }}'s in-charge to receive it.</div>
      @endif
    </div>
    <footer class="card-footer d-flex justify-content-between">
      <a href="{{ route('stock_movements.index') }}" class="btn btn-outline-secondary">Back</a>
      @if(in_array($m->status, ['Approved', 'Dispatched', 'Received']))
        <a href="{{ route('stock_movements.gate_pass', $m->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="fa fa-print"></i> Gate Pass</a>
      @endif
    </footer>
  </section>
</div>
<div class="col-lg-3">
  <section class="card">
    <header class="card-header"><h2 class="card-title" style="font-size:15px">Progress</h2></header>
    <div class="card-body small">
      @foreach($steps as [$label, $who, $when])
        <div class="mb-2 {{ $when ? '' : 'text-muted' }}">
          <i class="fa {{ $when ? 'fa-check-circle text-success' : 'fa-circle-o fa-circle' }}"></i> {{ $label }}
          @if($when)<div class="ms-3 text-muted">{{ $who }} · {{ $when->format('d-M H:i') }}</div>@endif
        </div>
      @endforeach
    </div>
  </section>
</div></div>

@if(in_array($m->status, ['Pending', 'Approved']) && $m->canBeApprovedBy($u))
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form action="{{ route('stock_movements.reject', $m->id) }}" method="POST">@csrf
    <div class="modal-header"><h5 class="modal-title">Reject {{ $m->movement_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
  </form>
</div></div></div>
@endif
@endsection
