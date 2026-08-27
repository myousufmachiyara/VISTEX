@extends('layouts.app')
@section('title', 'Forecast | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('forecasts.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Forecast / Planning</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Customer <span class="text-muted">(optional — general planning if blank)</span></label>
            <select name="customer_id" class="form-control select2-js">
              <option value="">General / Internal</option>
              @foreach($customers as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Product (Greige/Yarn) <span class="text-danger">*</span></label>
            <select name="product_id" class="form-control select2-js" required>
              <option value="">Select Product</option>
              @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Required By Date</label>
            <input type="date" name="required_by_date" class="form-control">
          </div>

          <div class="col-md-4 mb-3">
            <label>Required Quantity <span class="text-danger">*</span></label>
            <input type="number" name="required_qty" id="required_qty" class="form-control" step="any" min="0.001" required>
          </div>
          <div class="col-md-4 mb-3">
            <label>Current Stock on Hand</label>
            <input type="number" name="stock_on_hand" id="stock_on_hand" class="form-control" step="any" min="0" value="0">
          </div>
          <div class="col-md-4 mb-3">
            <label>Quantity Already on Order (open POs)</label>
            <input type="number" name="on_order_qty" id="on_order_qty" class="form-control" step="any" min="0" value="0">
          </div>

          <div class="col-md-12 mb-3">
            <div class="alert alert-info py-2">
              Shortfall to raise: <strong id="shortfallDisplay">0.000</strong>
              <small class="d-block text-muted">Required − Stock on Hand − On Order (floored at zero)</small>
            </div>
          </div>

          <div class="col-md-12 mb-3">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Submit for Approval</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); recalc(); });

  $('#required_qty, #stock_on_hand, #on_order_qty').on('input', recalc);

  function recalc() {
    const required = parseFloat($('#required_qty').val()) || 0;
    const onHand   = parseFloat($('#stock_on_hand').val()) || 0;
    const onOrder  = parseFloat($('#on_order_qty').val()) || 0;
    const shortfall = Math.max(0, required - onHand - onOrder);
    $('#shortfallDisplay').text(shortfall.toFixed(3));
  }
</script>
@endsection