@extends('layouts.app')
@section('title', $return->return_no)
@section('content')
@php
  $q = fn($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
@endphp
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Customer Return {{ $return->return_no }}</h2>
      <a href="{{ route('customer_returns.index') }}" class="btn btn-outline-primary">Back</a>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Customer:</strong> {{ $return->customer->name ?? '' }}</div>
        <div class="col-md-3"><strong>Back into:</strong> {{ $return->location->name ?? '' }}</div>
        <div class="col-md-2"><strong>Date:</strong> {{ $return->return_date->format('d-M-Y') }}</div>
        <div class="col-md-4"><strong>Reason:</strong> {{ $return->reason ?? '—' }}</div>
      </div>
      <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th>Delivery</th><th>Item</th><th>Lot · grade</th><th class="text-end">Qty</th><th>Condition</th><th class="text-end">Cost back</th><th class="text-end">Not invoiced</th><th class="text-end">Credited</th></tr></thead>
        <tbody>
        @foreach($return->items as $i)
          <tr><td class="small">{{ $i->stockMovementItem->stockMovement->movement_no ?? '' }}</td><td>{{ $i->product->name ?? '' }}</td>
            <td class="small">{{ $i->lot_no ?? '—' }}{{ $i->grade ? ' · ' . $i->grade : '' }}</td><td class="text-end">{{ $q($i->quantity) }}</td>
            <td><span class="badge bg-{{ $i->condition === 'fresh' ? 'success' : 'danger' }}">{{ ucfirst($i->condition) }}</span></td>
            <td class="text-end">{{ number_format($i->cost_amount, 2) }}</td><td class="text-end">{{ $i->uninvoiced_qty ? $q($i->uninvoiced_qty) : '' }}</td><td class="text-end">{{ $i->credited_qty ? $q($i->credited_qty) : '' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      @if($return->creditNotes->isNotEmpty())
        <h6>Credit notes</h6>
        <ul class="small">
        @foreach($return->creditNotes as $n)<li><a href="{{ route('customer_returns.credit_note', $n->id) }}">{{ $n->credit_no }}</a> on {{ $n->invoice->invoice_no ?? '' }} — {{ $n->currency }} {{ number_format($n->total, 2) }}{{ $n->unapplied > 0 ? ' (' . number_format($n->unapplied, 2) . ' on account)' : '' }}</li>@endforeach
        </ul>
      @endif
      <p class="small text-muted mb-0">Cost of goods sold reversed: PKR {{ number_format($return->cost_amount, 2) }}. Posted by {{ $return->creator->name ?? '' }}.</p>
    </div>
  </section>
</div></div>
@endsection
