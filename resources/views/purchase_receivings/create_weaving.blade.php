@extends('layouts.app')
@section('title', 'Weaving Receiving | ' . $challan->challan_no)
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_receivings.store_weaving') }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <input type="hidden" name="challan_id" value="{{ $challan->id }}">
    <input type="hidden" name="purchase_order_id" value="{{ $po->id }}">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Weaving Receiving — {{ $po->order_no }} ({{ $challan->challan_no }})</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row mb-3">
          <div class="col-md-3"><strong>Vendor:</strong> {{ $po->vendor->name ?? '' }}</div>
          <div class="col-md-3"><strong>Greige Product:</strong> {{ $po->greigeProduct->name ?? $po->item_name }}</div>
          <div class="col-md-3"><strong>Total Ordered:</strong> {{ number_format($po->total_meters_required, 3) }} m</div>
          <div class="col-md-3"><strong>Outstanding:</strong> {{ number_format($outstandingMeters, 3) }} m</div>
        </div>

        <div class="alert alert-info py-2">
          Yarn currently at this mill:
          @foreach($yarnBalance as $b)
            {{ $b['product_name'] }}: <strong>{{ $b['quantity'] }}</strong> (Rs. {{ $b['amount'] }}) &nbsp;
          @endforeach
        </div>

        <div class="row">
          <div class="col-md-3 mb-3"><label>Receiving Date</label><input type="date" name="receiving_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-3 mb-3">
            <label>Quantity Received (meters) <span class="text-danger">*</span></label>
            <input type="number" name="quantity_received" class="form-control" step="any" min="0.001" max="{{ $outstandingMeters }}" required>
          </div>
          <div class="col-md-3 mb-3 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" name="is_final_receiving" value="1" class="form-check-input" id="finalCheck">
              <label class="form-check-label" for="finalCheck">Final Receiving — clear remaining yarn balance</label>
            </div>
          </div>
          <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Receiving</button></footer>
    </section>
  </form>
</div></div>
@endsection