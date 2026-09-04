@extends('layouts.app')
@section('title', 'Log Challan')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('challans.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Log Received Challan</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-5 mb-3">
            <label>Purchase Order <span class="text-danger">*</span></label>
            <select name="purchase_order_id" id="po_select" class="form-control select2-js" required>
              <option value="">Select PO</option>
              @foreach($orders as $po)
                <option value="{{ $po->id }}">{{ $po->order_no }} — {{ $po->vendor->name ?? '' }} — {{ $po->category->name ?? '' }} ({{ $po->status }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-4 mb-3"><label>Vendor's Challan #</label><input type="text" name="vendor_challan_no" class="form-control"></div>
          <div class="col-md-8 mb-3"><label>Photo(s) of Challan <span class="text-danger">*</span></label><input type="file" name="challan_images[]" class="form-control" accept="image/*" multiple capture="environment" required></div>
          <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
        </div>

        <div id="expectedItemsSection" style="display:none">
          <h6>Expected Items Against This PO</h6>
          <table class="table table-sm table-bordered"><thead><tr><th>Item</th><th class="text-end">Expected Qty</th></tr></thead><tbody id="expectedItemsBody"></tbody></table>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Log Challan</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#po_select').on('change', function () {
    const poId = $(this).val();
    $('#expectedItemsBody').empty();
    if (!poId) { $('#expectedItemsSection').hide(); return; }

    fetch(`/challans/po-items/${poId}`).then(r => r.json()).then(items => {
      items.forEach(i => $('#expectedItemsBody').append(`<tr><td>${i.product_name}</td><td class="text-end">${i.quantity}</td></tr>`));
      $('#expectedItemsSection').show();
    });
  });
</script>
@endsection