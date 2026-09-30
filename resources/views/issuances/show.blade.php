@extends('layouts.app')
@section('title', $issuance->issue_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $issuance->issue_no }} <small class="text-muted">{{ $issuance->type_label }}</small></h2>
      <a href="{{ route('issuances.print', $issuance->id) }}" target="_blank" class="btn btn-sm btn-outline-success">Print</a>
    </header>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><strong>Date:</strong> {{ $issuance->issue_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Against:</strong> @if($issuance->purchaseOrder)<a href="{{ route('purchase_orders.show', $issuance->purchase_order_id) }}">{{ $issuance->purchaseOrder->order_no }}</a>@endif</div>
        <div class="col-md-3"><strong>Issued To:</strong> {{ $issuance->purchaseOrder->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>By:</strong> {{ $issuance->creator->name ?? '' }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-3"><strong>From:</strong> {{ $issuance->sourceLocation->name ?? '—' }}</div>
        <div class="col-md-3"><strong>To:</strong> {{ $issuance->destinationLocation->name ?? ($issuance->issue_type === 'yarn_weaving' ? 'Weaving mill (yarn in process)' : '—') }}</div>
      </div>
      <table class="table table-bordered">
        <thead><tr><th>Item</th><th>Lot</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Rate</th><th class="text-end">Value</th></tr></thead>
        <tbody>
          @foreach($issuance->items as $it)
          <tr>
            <td>{{ $it->product->name ?? '' }}</td><td>{{ $it->lot_no ?? '—' }}</td>
            <td class="text-end">{{ number_format($it->quantity, 3) }}</td><td>{{ $it->product->measurementUnit->shortcode ?? '' }}</td>
            <td class="text-end">{{ number_format($it->rate, 4) }}</td><td class="text-end">{{ number_format($it->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="2" class="text-end">Total</td><td class="text-end">{{ number_format($issuance->total_quantity, 3) }}</td><td></td><td></td><td class="text-end">{{ number_format($issuance->total_amount, 2) }}</td></tr></tfoot>
      </table>
      @if($issuance->remarks)<p><strong>Remarks:</strong> {{ $issuance->remarks }}</p>@endif
      @if($issuance->attachments)
        <p><strong>Attachments:</strong> @foreach($issuance->attachments as $a)<a href="{{ Storage::url($a) }}" target="_blank" class="me-2">File {{ $loop->iteration }}</a>@endforeach</p>
      @endif
    </div>
    <footer class="card-footer text-end">
      @can('issuances.edit')<a href="{{ route('issuances.edit', $issuance->id) }}" class="btn btn-outline-primary">Edit</a>@endcan
      <a href="{{ route('issuances.index') }}" class="btn btn-outline-secondary">Back</a>
    </footer>
  </section>
</div></div>
@endsection
