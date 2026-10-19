@extends('layouts.app')
@section('title', 'Edit ' . $receiving->receiving_no)
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_receivings.update', $receiving->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf @method('PUT')
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Edit {{ $receiving->receiving_no }}</h2>
        <span class="badge bg-warning text-dark">Pending Approval</span>
      </header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>@endif
        <div class="row mb-3">
          <div class="col-md-3"><strong>PO #:</strong> {{ $receiving->purchaseOrder->order_no ?? '' }}</div>
          <div class="col-md-3"><strong>Vendor:</strong> {{ $receiving->purchaseOrder->vendor->name ?? '' }}</div>
          <div class="col-md-3"><strong>Challan #:</strong> {{ $receiving->challan->challan_no ?? '—' }}</div>
          <div class="col-md-3"><label>GRN Date</label><input type="date" name="receiving_date" class="form-control" required value="{{ old('receiving_date', $receiving->receiving_date->format('Y-m-d')) }}"></div>
        </div>
        <table class="table table-bordered">
          <thead><tr><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Open on PO (incl. this GRN)</th><th width="16%">Received</th><th width="16%">Rejected</th><th class="text-end">Accepted</th></tr></thead>
          <tbody>
            @foreach($receiving->items as $item)
              @php
                $poi = $item->purchaseOrderItem;
                $acc = (float) $item->quantity_received - (float) $item->quantity_rejected;
                $open = $poi ? round((float) $poi->quantity - (float) $poi->quantity_received + $acc, 3) : null;
                $unit = $item->product->measurementUnit->shortcode ?? '';
              @endphp
              <tr class="grn-line">
                <td>{{ $item->product->name ?? '' }}</td>
                <td class="text-end">{{ $poi ? number_format($poi->quantity, 3) : '—' }} {{ $unit }}</td>
                <td class="text-end">{{ $open !== null ? number_format($open, 3) : '—' }} {{ $unit }}</td>
                <td><input type="number" name="items[{{ $item->id }}][quantity_received]" class="form-control text-end q-rec" step="any" min="0" required value="{{ old("items.{$item->id}.quantity_received", (float) $item->quantity_received) }}"></td>
                <td><input type="number" name="items[{{ $item->id }}][quantity_rejected]" class="form-control text-end q-rej" step="any" min="0" value="{{ old("items.{$item->id}.quantity_rejected", (float) $item->quantity_rejected) }}"></td>
                <td class="text-end q-acc" data-open="{{ $open }}">{{ number_format($acc, 3) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $receiving->remarks) }}</textarea></div>
        <p class="small text-muted">Rates come from the PO and are applied when the GRN is approved. Saving does not post anything — approve the GRN afterwards.</p>
        <div class="text-end">
          <a href="{{ route('purchase_receivings.show', $receiving->id) }}" class="btn btn-outline-secondary">Cancel</a>
          <button class="btn btn-primary">Save GRN</button>
        </div>
      </div>
    </section>
  </form>
</div></div>
<script>
  $(function () {
    $(document).on('input', '.q-rec, .q-rej', function () {
      const tr = $(this).closest('tr');
      const acc = (parseFloat(tr.find('.q-rec').val()) || 0) - (parseFloat(tr.find('.q-rej').val()) || 0);
      const cell = tr.find('.q-acc'), open = parseFloat(cell.data('open'));
      cell.text(acc.toFixed(3)).toggleClass('text-danger fw-bold', !isNaN(open) && acc > open + 0.0005);
    });
  });
</script>
@endsection
