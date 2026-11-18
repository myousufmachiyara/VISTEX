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
        <div class="col-md-3"><strong>Against:</strong>
          @if($issuance->isForCutting() && $issuance->job)<a href="{{ route('jobs.show', $issuance->job_id) }}">{{ $issuance->job->job_no }}</a>
          @elseif($issuance->purchaseOrder)<a href="{{ route('purchase_orders.show', $issuance->purchase_order_id) }}">{{ $issuance->purchaseOrder->order_no }}</a>@endif</div>
        <div class="col-md-3"><strong>{{ $issuance->isForCutting() ? 'Customer' : 'Issued To' }}:</strong> {{ $issuance->isForCutting() ? ($issuance->job->customer->name ?? '') : ($issuance->purchaseOrder->vendor->name ?? '') }}</div>
        <div class="col-md-3"><strong>By:</strong> {{ $issuance->creator->name ?? '' }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-3"><strong>From:</strong> {{ $issuance->sourceLocation->name ?? '—' }}</div>
        <div class="col-md-3"><strong>To:</strong> {{ $issuance->destinationLocation->name ?? ($issuance->isForCutting() ? 'Cutting department (issued for cutting)' : ($issuance->issue_type === 'yarn_weaving' ? 'Weaving mill (yarn in process)' : 'In process for ' . ($issuance->purchaseOrder->order_no ?? 'the PO'))) }}</div>
      </div>
      @if($issuance->isForCutting())
      @php $issuedHere = $issuance->items->groupBy('job_item_id')->map(fn($g) => \App\Models\Issuance::toYards((float) $g->sum('quantity'))); @endphp
      <table class="table table-bordered">
        <thead><tr><th>Collection</th><th>Design</th><th>Color</th><th>Fabric</th><th>Lot</th><th>Grade</th><th class="text-end">Issued (yd)</th><th class="text-end">Meters</th><th class="text-end">Required (yd)</th><th class="text-end">Total issued (yd)</th><th class="text-end">%</th><th class="text-end">Value</th></tr></thead>
        <tbody>
          @foreach($issuance->items as $it)
          @php
            $ji = $it->jobItem; $r = $requirement[$it->job_item_id] ?? ['required_yd' => 0, 'issued_yd' => 0];
            $pct = \App\Services\IssuanceService::cuttingPercent($r['required_yd'], $r['issued_yd']);
          @endphp
          <tr>
            <td>{{ $ji->collection ?? '—' }}</td><td>{{ $ji->design ?? '—' }}</td><td>{{ $ji->color_scheme ?? '—' }}</td>
            <td>{{ $it->product->name ?? '' }}</td><td>{{ $it->lot_no ?? '—' }}</td><td>{{ $it->grade ?? '—' }}</td>
            <td class="text-end">{{ number_format($it->quantity_yd, 2) }}</td><td class="text-end text-muted">{{ number_format($it->quantity, 2) }}</td>
            <td class="text-end">{{ number_format($r['required_yd'], 2) }}</td><td class="text-end">{{ number_format($r['issued_yd'], 2) }}</td>
            <td class="text-end">{{ $pct !== null ? number_format($pct, 1) . '%' : '—' }}</td>
            <td class="text-end">{{ number_format($it->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="6" class="text-end">Total</td><td class="text-end">{{ number_format(\App\Models\Issuance::toYards((float) $issuance->total_quantity), 2) }}</td><td class="text-end text-muted">{{ number_format($issuance->total_quantity, 2) }}</td><td colspan="3" class="small text-muted text-end">% = required ÷ total issued × 100</td><td class="text-end">{{ number_format($issuance->total_amount, 2) }}</td></tr></tfoot>
      </table>
      @else
      <table class="table table-bordered">
        <thead><tr><th>Item</th><th>Lot</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Bags</th><th class="text-end">Rate</th><th class="text-end">Value</th></tr></thead>
        <tbody>
          @foreach($issuance->items as $it)
          <tr>
            <td>{{ $it->product->name ?? '' }}</td><td>{{ $it->lot_no ?? '—' }}</td>
            <td class="text-end">{{ number_format($it->quantity, 3) }}</td><td>{{ $it->product->measurementUnit->shortcode ?? '' }}</td>
            <td class="text-end">{{ $it->bags !== null ? $it->bags + 0 : '—' }}</td>
            <td class="text-end">{{ number_format($it->rate, 4) }}</td><td class="text-end">{{ number_format($it->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="2" class="text-end">Total</td><td class="text-end">{{ number_format($issuance->total_quantity, 3) }}</td><td></td><td class="text-end">{{ $issuance->no_of_bags !== null ? $issuance->no_of_bags + 0 : '' }}</td><td></td><td class="text-end">{{ number_format($issuance->total_amount, 2) }}</td></tr></tfoot>
      </table>
      @endif
      <div class="row mb-2 small">
        <div class="col-md-3"><strong>Vehicle:</strong> {{ $issuance->vehicle_no ?: '—' }}</div>
        <div class="col-md-3"><strong>Driver:</strong> {{ $issuance->driver_name ?: '—' }} {{ $issuance->driver_contact ? '(' . $issuance->driver_contact . ')' : '' }}</div>
        <div class="col-md-2"><strong>Total Bags:</strong> {{ $issuance->no_of_bags !== null ? rtrim(rtrim(number_format((float) $issuance->no_of_bags, 2), '0'), '.') : '—' }}</div>
        <div class="col-md-2"><strong>Prepared By:</strong> {{ $issuance->prepared_by_name ?: '—' }}</div>
        <div class="col-md-2"><strong>Received By:</strong> {{ $issuance->received_by_name ?: '—' }}</div>
      </div>
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
