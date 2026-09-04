@extends('layouts.app')
@section('title', 'Processing Issue | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('processing_issues.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Processing Issue</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-5 mb-3">
            <label>Processing PO <span class="text-danger">*</span></label>
            <select name="purchase_order_id" id="po_select" class="form-control select2-js" required>
              <option value="">Select PO</option>
              @foreach($orders as $po)<option value="{{ $po->id }}">{{ $po->order_no }} — {{ $po->vendor->name ?? '' }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Mill Location <span class="text-danger">*</span></label>
            <select name="location_id" id="location_select" class="form-control select2-js" required disabled>
              <option value="">Select PO first</option>
            </select>
          </div>
          <div class="col-md-3 mb-3"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-6 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
          <div class="col-md-6 mb-3"><label>Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>

        <div class="alert alert-secondary py-2" id="loadingMsg">Select a PO and Location to see available lots.</div>

        <div id="itemsSection" style="display:none">
          <table class="table table-bordered">
            <thead><tr><th>Lot #</th><th>Product</th><th class="text-end">Available</th><th>Link PO Item</th><th>Quantity to Issue</th></tr></thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success" id="submitBtn" style="display:none">Save Issue</button></footer>
    </section>
  </form>
</div></div>

<script>
  let poItemsCache = [];
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#po_select').on('change', function () {
    const poId = $(this).val();
    $('#location_select').prop('disabled', true).html('<option value="">Loading...</option>');
    $('#itemsBody').empty(); $('#itemsSection, #submitBtn').hide();
    if (!poId) return;

    fetch(`/processing-issues/po-details/${poId}`).then(r => r.json()).then(data => {
      poItemsCache = data.po_items;
      let html = '<option value="">Select Location</option>';
      data.locations.forEach(l => html += `<option value="${l.id}">${l.name}</option>`);
      $('#location_select').prop('disabled', false).html(html).trigger('change.select2');
    });
  });

  $('#location_select').on('change', function () {
    const locationId = $(this).val();
    $('#itemsBody').empty();
    if (!locationId) { $('#itemsSection, #submitBtn').hide(); return; }

    fetch(`/processing-issues/available-stock?location_id=${locationId}`).then(r => r.json()).then(rows => {
      if (rows.length === 0) { $('#loadingMsg').show().text('No stock available at this location.'); $('#itemsSection, #submitBtn').hide(); return; }
      $('#loadingMsg').hide(); $('#itemsSection, #submitBtn').show();

      rows.forEach((row, idx) => {
        let poItemOptions = '<option value="">No link</option>';
        poItemsCache.forEach(pi => poItemOptions += `<option value="${pi.purchase_order_item_id}">${pi.pattern_code} — ${pi.description} (outstanding: ${pi.outstanding})</option>`);

        $('#itemsBody').append(`
          <tr>
            <td><input type="hidden" name="lot_no" value="${row.lot_no}">${row.lot_no}</td>
            <td>${row.product_name}<input type="hidden" name="items[${idx}][product_id]" value="${row.product_id}"></td>
            <td class="text-end">${row.available}</td>
            <td><select name="items[${idx}][purchase_order_item_id]" class="form-control">${poItemOptions}</select></td>
            <td><input type="number" name="items[${idx}][quantity]" class="form-control" value="0" step="any" min="0" max="${row.available}"></td>
          </tr>
        `);
      });
    });
  });
</script>
@endsection