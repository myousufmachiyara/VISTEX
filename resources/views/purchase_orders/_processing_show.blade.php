{{-- Processing PO details on the PO page --}}
@php
  $spec = $order->fabric_specs ?? [];
  $rpMove = $order->isReprocess() ? \App\Models\StockMovement::where('purchase_order_id', $order->id)->where('status', '!=', 'Rejected')->first() : null;
@endphp
@if($order->isReprocess())
<div class="alert alert-info py-2">
  <strong>Reprocess order</strong> {!! $order->is_foc ? '<span class="badge bg-info">FOC</span>' : '' !!}
  · lot <strong>{{ $order->reprocess_lot_no }}</strong> (kept)
  · original PO <a href="{{ route('purchase_orders.show', $order->reprocess_of_po_id) }}">{{ $order->reprocessOf->order_no ?? '' }}</a>
  · gate pass:
  @if($rpMove)<a href="{{ route('stock_movements.show', $rpMove->id) }}">{{ $rpMove->movement_no }}</a> ({{ $rpMove->status_label }})
  @else created when the order is approved @endif
  @if(!empty($spec['notes']))<div class="small mt-1">Reason: {{ implode('; ', $spec['notes']) }}</div>@endif
</div>
@endif
<div class="row mb-2">
  <div class="col-md-3 mb-2"><strong>Customer Order:</strong>
    @if($order->job)<a href="{{ route('jobs.show', $order->job_id) }}">{{ $order->job->job_no }}</a> <small class="text-muted">{{ $order->job->customer->name ?? '' }}</small>@else — @endif
  </div>
  <div class="col-md-3 mb-2"><strong>PO Type:</strong> {{ $spec['po_kind'] ?? 'Outsource Processing' }} @if(!empty($spec['is_sample']))<span class="badge bg-danger">Sample</span>@endif</div>
  <div class="col-md-3 mb-2"><strong>Program:</strong> {{ $order->program ?? '—' }}</div>
  <div class="col-md-3 mb-2"><strong>Process:</strong> {{ $order->serviceType->name ?? '—' }}</div>
  <div class="col-md-3 mb-2"><strong>Fabric Quality:</strong> {{ $order->greigeProduct->name ?? '—' }}</div>
  <div class="col-md-9 mb-2"><strong>Widths / GSM:</strong>
    greige {{ $spec['greige_width'] ?? '—' }} · finished {{ $spec['finished_width'] ?? '—' }} · GSM {{ $spec['finished_gsm'] ?? '—' }}
    @if(!empty($spec['dyestuff'])) · {{ $spec['dyestuff'] }}@endif @if(!empty($spec['finish'])) · {{ $spec['finish'] }}@endif
  </div>
</div>
<div class="table-responsive">
<table class="table table-bordered table-sm">
  <thead class="table-light"><tr><th>#</th><th>Collection</th><th>Pattern #</th><th>Design</th><th>Colour</th><th>Packaging</th>
    <th class="text-end">Qty (m)</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
  <tbody>
    @foreach($order->items as $n => $item)
    <tr>
      <td>{{ $n + 1 }}</td><td>{{ $item->collection }}</td><td class="font-monospace">{{ $item->pattern_code }}</td>
      <td>{{ $item->design }}</td><td>{{ $item->color_scheme }}</td><td>{{ $item->product->name ?? '—' }}</td>
      <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
      <td class="text-end">{{ number_format($item->quantity_received, 2) }}</td>
      <td class="text-end">{{ number_format($item->outstanding_qty, 2) }}</td>
      <td class="text-end">{{ number_format($item->rate, 2) }}</td>
      <td class="text-end">{{ number_format($item->amount, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot class="fw-bold"><tr><td colspan="6" class="text-end">Total</td><td class="text-end">{{ number_format($order->items->sum('quantity'), 2) }}</td><td colspan="3"></td><td class="text-end">{{ number_format($order->subtotal, 2) }}</td></tr></tfoot>
</table>
</div>
<div class="row">
  @if(!empty($spec['lab_requirements']))
  <div class="col-md-6">
    <h6>Lab Requirements</h6>
    <table class="table table-sm table-bordered">@foreach($spec['lab_requirements'] as $r)<tr><td class="fw-semibold" width="45%">{{ $r['label'] }}</td><td>{{ $r['value'] }}</td></tr>@endforeach</table>
  </div>
  @endif
  @if(!empty($spec['notes']))
  <div class="col-md-6">
    <h6>Notes &amp; Special Instructions</h6>
    <ol class="small ps-3">@foreach($spec['notes'] as $note)<li>{{ $note }}</li>@endforeach</ol>
  </div>
  @endif
</div>
