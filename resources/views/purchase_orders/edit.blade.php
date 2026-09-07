@extends('layouts.app')
@section('title', 'Purchase Order | Edit')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_orders.update', $order->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Edit Purchase Order — {{ $order->order_no }} <small class="text-muted">(Rev {{ $order->revision_no }})</small></h2>
      </header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if($order->openObjections->count() > 0)
          <div class="alert alert-danger">
            <strong>Open Objections:</strong>
            <ul class="mb-0">
              @foreach($order->openObjections as $obj)
                <li>{{ $obj->raisedBy->name ?? '' }}: {{ $obj->remarks }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="row">
          <div class="col-md-3 mb-3">
            <label>Category <span class="text-danger">*</span></label>
            <select name="product_category_id" id="category_select" class="form-control select2-js" required>
              <option value="">Select Category</option>
              @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" data-code="{{ $cat->code }}" @selected($cat->id == $order->product_category_id)>{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label>Vendor <span class="text-danger">*</span></label>
            <select name="vendor_id" id="vendor_select" class="form-control select2-js" required>
              <option value="">Select Vendor</option>
              @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected($vendor->id == $order->vendor_id)>{{ $vendor->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label>From Location</label>
            <select name="from_location_id" id="from_location_select" class="form-control select2-js">
              <option value="">Select Vendor First</option>
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label>Drop Off Location <span class="text-danger">*</span></label>
            <select name="drop_off_location_id" class="form-control select2-js" required>
              <option value="">Select Location</option>
              @foreach ($dropOffLocations as $loc)
                <option value="{{ $loc->id }}" @selected($loc->id == $order->drop_off_location_id)>{{ $loc->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label>Order Date <span class="text-danger">*</span></label>
            <input type="date" name="order_date" class="form-control" value="{{ $order->order_date->format('Y-m-d') }}" required>
          </div>

          <div class="col-md-3 mb-3">
            <label>Expected Date</label>
            <input type="date" name="expected_date" class="form-control" value="{{ $order->expected_date?->format('Y-m-d') }}">
          </div>
        </div>

        {{-- Broker --}}
        <div class="row" id="brokerSection">
          <div class="col-md-4 mb-3">
            <label>Broker <span class="text-muted">(optional)</span></label>
            <select name="broker_id" id="broker_select" class="form-control select2-js">
              <option value="">No Broker</option>
              @foreach ($brokers as $b)
                <option value="{{ $b->id }}" @selected($b->id == $order->broker_id)>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3" id="brokerAmountField" style="{{ $order->broker_id ? '' : 'display:none' }}">
            <label>Broker Commission Amount</label>
            <input type="number" name="broker_commission_amount" id="broker_commission_amount" class="form-control comma-input" step="any" min="0" value="{{ $order->broker_commission_amount ?? 0 }}">
          </div>
        </div>

        {{-- Payment Terms --}}
        <div class="row">
          <div class="col-md-3 mb-3">
            <label>Payment Term <span class="text-danger">*</span></label>
            <select name="payment_term_type" id="payment_term_type" class="form-control" required>
              <option value="cash" @selected($order->payment_term_type == 'cash')>Cash</option>
              <option value="credit" @selected($order->payment_term_type == 'credit')>Credit</option>
              <option value="pdc" @selected($order->payment_term_type == 'pdc')>PDC</option>
              <option value="other" @selected($order->payment_term_type == 'other')>Other</option>
            </select>
          </div>
          <div class="col-md-3 mb-3" id="paymentDaysField" style="{{ in_array($order->payment_term_type, ['credit','pdc']) ? '' : 'display:none' }}">
            <label>Days</label>
            <select name="payment_term_days" class="form-control">
              <option value="30" @selected($order->payment_term_days == 30)>30 days</option>
              <option value="60" @selected($order->payment_term_days == 60)>60 days</option>
              <option value="90" @selected($order->payment_term_days == 90)>90 days</option>
              <option value="0" @selected(!in_array($order->payment_term_days, [30,60,90]))>Custom (enter below)</option>
            </select>
            <input type="number" name="payment_term_days_custom" class="form-control mt-1 comma-input" placeholder="Custom days" value="{{ !in_array($order->payment_term_days, [30,60,90]) ? $order->payment_term_days : '' }}" style="{{ !in_array($order->payment_term_days, [30,60,90]) ? '' : 'display:none' }}">
          </div>
          <div class="col-md-4 mb-3" id="paymentNoteField" style="{{ $order->payment_term_type == 'other' ? '' : 'display:none' }}">
            <label>Note</label>
            <input type="text" name="payment_term_note" class="form-control" value="{{ $order->payment_term_note }}">
          </div>

          <div class="col-md-2 mb-3">
            <label>GST Terms <span class="text-danger">*</span></label>
            <select name="gst_applicable" id="gst_applicable" class="form-control" required>
              <option value="1" @selected($order->gst_applicable)>With GST</option>
              <option value="0" @selected(!$order->gst_applicable)>Without GST</option>
            </select>
          </div>

          <div class="col-md-3 mb-3" id="tax_field">
            <label>Tax</label>
            <select name="tax_id" id="tax_select" class="form-control">
              <option value="">Select Tax</option>
              @foreach ($taxes as $tax)
                <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected($tax->id == $order->tax_id)>{{ $tax->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label>Add More Attachments</label>
            <input type="file" name="attachments[]" class="form-control" multiple>
          </div>

          <div class="col-md-12 mb-3">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="1">{{ $order->remarks }}</textarea>
          </div>
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-bordered" id="itemsTable">
            <thead><tr><th>Item</th><th>Quantity</th><th>Rate</th><th>Amount</th><th></th></tr></thead>
            <tbody id="itemsBody">
              @foreach($order->items as $i => $item)
              <tr class="item-row">
                <td>
                  <select name="items[{{ $i }}][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Product</option>
                    @foreach ($products as $p)
                      <option value="{{ $p->id }}" @selected($p->id == $item->product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control qty-input comma-input" step="any" min="0.001" value="{{ $item->quantity }}"></td>
                <td><input type="number" name="items[{{ $i }}][rate]" class="form-control price-input comma-input" step="any" min="0" value="{{ $item->rate }}"></td>
                <td class="amount-cell text-end">{{ number_format($item->amount, 2) }}</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end">Subtotal:</td>
                <td class="text-end" id="subtotalDisplay">{{ number_format($order->subtotal, 2) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="text-end">GST:</td>
                <td class="text-end" id="gstDisplay">{{ number_format($order->gst_amount, 2) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="text-end">Broker Commission:</td>
                <td class="text-end" id="brokerDisplay">{{ number_format($order->broker_commission_amount ?? 0, 2) }}</td>
                <td></td>
              </tr>
              <tr class="fw-bold">
                <td colspan="3" class="text-end">Total:</td>
                <td class="text-end" id="totalDisplay">{{ number_format($order->total_amount, 2) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Item</button>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Update Purchase Order</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = {{ $order->items->count() }};
  let categoryProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]));

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    toggleGst();

    const currentVendorId = {{ $order->vendor_id }};
    const currentFromLocationId = {{ $order->from_location_id ?? 'null' }};

    if (currentVendorId) {
      fetch(`/purchase-orders/vendor-locations/${currentVendorId}`)
        .then(res => res.json())
        .then(locations => {
          let html = '<option value="">Select Location</option>';
          locations.forEach(loc => {
            const selected = loc.id === currentFromLocationId ? 'selected' : '';
            html += `<option value="${loc.id}" ${selected}>${loc.name}</option>`;
          });
          $('#from_location_select').html(html).trigger('change');
        });
    }
  });

  $('#category_select').on('change', function () {
    const catId = $(this).val();
    if (!catId) return;
    fetch(`/purchase-orders/category-products/${catId}`)
      .then(res => res.json())
      .then(products => { categoryProducts = products; });
  });

  $('#vendor_select').on('change', function () {
    const vendorId = $(this).val();
    const $fromLoc = $('#from_location_select');
    $fromLoc.html('<option value="">Loading...</option>').trigger('change');
    if (!vendorId) return;

    fetch(`/purchase-orders/vendor-locations/${vendorId}`)
      .then(res => res.json())
      .then(locations => {
        let html = '<option value="">Select Location</option>';
        locations.forEach(loc => html += `<option value="${loc.id}">${loc.name}</option>`);
        $fromLoc.html(html).trigger('change');
      });
  });

  $('#broker_select').on('change', function () {
    $('#brokerAmountField').toggle(!!$(this).val());
    recalcTotal();
  });

  $(document).on('input', '#broker_commission_amount', recalcTotal);

  $('#payment_term_type').on('change', function () {
    const val = $(this).val();
    $('#paymentDaysField').toggle(val === 'credit' || val === 'pdc');
    $('#paymentNoteField').toggle(val === 'other');
  });

  $('#gst_applicable').on('change', toggleGst);
  function toggleGst() { $('#tax_field').toggle($('#gst_applicable').val() === '1'); recalcTotal(); }

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    categoryProducts.forEach(p => html += `<option value="${p.id}">${p.name} (${p.sku})</option>`);
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>${productOptionsHtml()}</select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input comma-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control price-input comma-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.qty-input, .price-input', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const price = parseFloat(row.find('.price-input').val()) || 0;
    row.find('.amount-cell').text((qty * price).toFixed(2));
    recalcTotal();
  });

  function recalcTotal() {
    let subtotal = 0;
    $('.item-row').each(function () { subtotal += parseFloat($(this).find('.amount-cell').text()) || 0; });
    const gstApplicable = $('#gst_applicable').val() === '1';
    const rate = gstApplicable ? (parseFloat($('#tax_select').find('option:selected').data('rate')) || 0) : 0;
    const gst = subtotal * (rate / 100);
    const brokerAmt = $('#broker_select').val() ? (parseFloat($('#broker_commission_amount').val()) || 0) : 0;

    $('#subtotalDisplay').text(subtotal.toFixed(2));
    $('#gstDisplay').text(gst.toFixed(2));
    $('#brokerDisplay').text(brokerAmt.toFixed(2));
    $('#totalDisplay').text((subtotal + gst + brokerAmt).toFixed(2));
  }

  $(document).on('change', '#tax_select', recalcTotal);

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotal(); }
  });
</script>
@endsection