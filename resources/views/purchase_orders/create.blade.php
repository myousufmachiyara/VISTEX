@extends('layouts.app')
@section('title', 'Purchase Order | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_orders.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Purchase Order</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-3 mb-3">
            <label>Category <span class="text-danger">*</span></label>
            <select name="product_category_id" id="category_select" class="form-control select2-js" required>
              <option value="">Select Category</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>Vendor <span class="text-danger">*</span></label>
            <select name="vendor_id" id="vendor_select" class="form-control select2-js" required>
              <option value="">Select Vendor</option>
              @foreach($vendors as $vendor)
                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>From Location</label>
            <select name="from_location_id" id="from_location_select" class="form-control select2-js" disabled>
              <option value="">Select vendor first</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>Drop Off Location <span class="text-danger">*</span></label>
            <select name="drop_off_location_id" class="form-control select2-js" required>
              <option value="">Select Location</option>
              @foreach($dropOffLocations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label>Order Date <span class="text-danger">*</span></label>
            <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-md-3 mb-3">
            <label>Expected Date</label>
            <input type="date" name="expected_date" class="form-control">
          </div>
          <div class="col-md-2 mb-3">
            <label>GST Terms <span class="text-danger">*</span></label>
            <select name="gst_applicable" id="gst_applicable" class="form-control" required>
              <option value="1">With GST</option>
              <option value="0">Without GST</option>
            </select>
          </div>
          <div class="col-md-4 mb-3" id="tax_field">
            <label>Tax</label>
            <select name="tax_id" id="tax_select" class="form-control">
              <option value="">Select Tax</option>
              @foreach($taxes as $tax)
                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected($tax->is_default)>{{ $tax->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label>Attachments</label>
            <input type="file" name="attachments[]" class="form-control" multiple>
          </div>
          <div class="col-md-6 mb-3">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="1"></textarea>
          </div>
        </div>

        <div class="alert alert-info py-2" id="categoryMsg">Select a Category to enable item selection.</div>

        <div class="table-responsive mb-3" id="itemsSection" style="display:none">
          <table class="table table-bordered" id="itemsTable">
            <thead>
              <tr>
                <th width="22%">Item</th>
                <th width="22%">Link Forecast <span class="text-muted">(optional)</span></th>
                <th>Quantity</th>
                <th>Rate</th>
                <th>Amount</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
            <tfoot>
              <tr><td colspan="4" class="text-end">Subtotal:</td><td class="text-end" id="subtotalDisplay">0.00</td><td></td></tr>
              <tr><td colspan="4" class="text-end">GST:</td><td class="text-end" id="gstDisplay">0.00</td><td></td></tr>
              <tr class="fw-bold"><td colspan="4" class="text-end">Total:</td><td class="text-end" id="totalDisplay">0.00</td><td></td></tr>
            </tfoot>
          </table>
          <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Item</button>
          <p class="text-muted small mt-2">
            Linking a forecast is optional and per line — you can combine shortfalls from several
            different forecasts (even for different customers) into a single Purchase Order.
          </p>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Purchase Order</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = 0;
  let categoryProducts = [];

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); toggleGst(); });

  $('#category_select').on('change', function () {
    const catId = $(this).val();
    $('#itemsBody').empty();
    rowIndex = 0;

    if (!catId) { $('#itemsSection').hide(); $('#categoryMsg').show(); return; }

    fetch(`/purchase-orders/category-products/${catId}`).then(r => r.json()).then(products => {
      categoryProducts = products;
      $('#categoryMsg').hide();
      $('#itemsSection').show();
      addRow();
    });
  });

  $('#vendor_select').on('change', function () {
    const vendorId = $(this).val();
    const $fromLoc = $('#from_location_select');
    $fromLoc.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change');
    if (!vendorId) return;

    fetch(`/purchase-orders/vendor-locations/${vendorId}`).then(r => r.json()).then(locations => {
      let html = '<option value="">No specific location</option>';
      locations.forEach(loc => html += `<option value="${loc.id}">${loc.name}</option>`);
      $fromLoc.prop('disabled', false).html(html).trigger('change');
    });
  });

  $('#gst_applicable').on('change', toggleGst);
  function toggleGst() { $('#tax_field').toggle($('#gst_applicable').val() === '1'); recalcTotal(); }

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    categoryProducts.forEach(p => html += `<option value="${p.id}">${p.name} (${p.sku})</option>`);
    return html;
  }

  function addRow() {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>${productOptionsHtml()}</select></td>
        <td><select name="items[${idx}][forecast_id]" class="form-control forecast-select" disabled><option value="">No forecast selected</option></select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }

  $('#addRowBtn').on('click', addRow);

  // When a product is picked on a line, load that product's approved
  // forecasts (if any) into that same line's forecast dropdown.
  $(document).on('change', '.product-select', function () {
    const row = $(this).closest('tr');
    const productId = $(this).val();
    const $forecastSelect = row.find('.forecast-select');
    $forecastSelect.prop('disabled', true).html('<option value="">No forecast selected</option>');

    if (!productId) return;

    fetch(`/purchase-orders/forecasts-for-product/${productId}`).then(r => r.json()).then(forecasts => {
      let html = '<option value="">No forecast selected</option>';
      forecasts.forEach(f => {
        html += `<option value="${f.id}" data-shortfall="${f.shortfall_qty}">${f.forecast_no} — ${f.customer_name} (shortfall: ${f.shortfall_qty})</option>`;
      });
      $forecastSelect.html(html).prop('disabled', forecasts.length === 0);
    });
  });

  // Selecting a forecast auto-fills that line's quantity with the shortfall (editable after)
  $(document).on('change', '.forecast-select', function () {
    const row = $(this).closest('tr');
    const shortfall = $(this).find('option:selected').data('shortfall');
    if (shortfall !== undefined) {
      row.find('.qty-input').val(shortfall).trigger('input');
    }
  });

  $(document).on('input', '.qty-input, .rate-input', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    row.find('.amount-cell').text((qty * rate).toFixed(2));
    recalcTotal();
  });

  function recalcTotal() {
    let subtotal = 0;
    $('.item-row').each(function () { subtotal += parseFloat($(this).find('.amount-cell').text()) || 0; });
    const gstApplicable = $('#gst_applicable').val() === '1';
    const rate = gstApplicable ? (parseFloat($('#tax_select').find('option:selected').data('rate')) || 0) : 0;
    const gst = subtotal * (rate / 100);
    $('#subtotalDisplay').text(subtotal.toFixed(2));
    $('#gstDisplay').text(gst.toFixed(2));
    $('#totalDisplay').text((subtotal + gst).toFixed(2));
  }

  $(document).on('change', '#tax_select', recalcTotal);
  $(document).on('click', '.remove-row', function () { if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotal(); } });
</script>
@endsection