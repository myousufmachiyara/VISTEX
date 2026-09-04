@extends('layouts.app')
@section('title', 'Receiving | ' . $challan->challan_no)
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_receivings.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <input type="hidden" name="challan_id" value="{{ $challan->id }}">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Receiving — {{ $challan->purchaseOrder->order_no }} (Challan {{ $challan->challan_no }})</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="alert alert-info py-2">
          Physically check each item against the challan before entering quantities. Rates are locked to the PO
          and are not shown here.
        </div>

        <div class="row">
          <div class="col-md-4 mb-3"><label>Vendor</label><input type="text" class="form-control" value="{{ $challan->purchaseOrder->vendor->name ?? '' }}" disabled></div>
          <div class="col-md-3 mb-3"><label>Receiving Date</label><input type="date" name="receiving_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-5 mb-3"><label>Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>

        <table class="table table-bordered">
          <thead><tr><th>Product</th><th class="text-end">Ordered</th><th class="text-end">Already Received</th><th class="text-end">Outstanding</th><th width="16%">Received Qty</th><th width="16%">Rejected Qty</th></tr></thead>
          <tbody>
            @foreach($outstanding as $idx => $item)
            <tr>
              <td>{{ $item['product_name'] }}
                <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $item['purchase_order_item_id'] }}">
                <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item['product_id'] }}">
              </td>
              <td class="text-end">{{ $item['ordered'] }}</td>
              <td class="text-end">{{ $item['already_received'] }}</td>
              <td class="text-end"><strong>{{ $item['outstanding'] }}</strong></td>
              <td><input type="number" name="items[{{ $idx }}][quantity_received]" class="form-control qty-input" value="0" step="any" min="0" max="{{ $item['outstanding'] }}"></td>
              <td><input type="number" name="items[{{ $idx }}][quantity_rejected]" class="form-control" value="0" step="any" min="0"></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Receiving</button></footer>
    </section>
  </form>
</div></div>
@endsection