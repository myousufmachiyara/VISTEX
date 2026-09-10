@extends('layouts.app')
@section('title', 'Purchase Order | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_orders.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="type" id="po_type" value="">
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Purchase Order</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- ═══ STEP 1: Category ═══ --}}
        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Product Category <span class="text-danger">*</span></label>
            <select name="product_category_id" id="category_select" class="form-control select2-js" required>
              <option value="">Select Category</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" data-code="{{ $cat->code }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>

          {{-- ═══ STEP 2: Type (only shown for Greige) ═══ --}}
          <div class="col-md-4 mb-3" id="type_field" style="display:none">
            <label>Type <span class="text-danger">*</span></label>
            <select id="type_select" class="form-control">
              <option value="">Select Type</option>
              <option value="purchase">Purchasing</option>
              <option value="weaving">Weaving</option>
              <option value="processing">Processing</option>
            </select>
          </div>

          <div class="col-md-4 mb-3" id="service_type_field" style="display:none">
            <label>Service Type <span class="text-danger">*</span></label>
            <select name="service_type_id" class="form-control select2-js">
              <option value="">Select Service Type</option>
              @foreach($serviceTypes as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
            </select>
          </div>
        </div>

        <div class="alert alert-info py-2" id="categoryMsg">Select a Category to continue.</div>
        <div class="alert alert-warning py-2" id="processingMsg" style="display:none">
          Processing-type Purchase Orders are not yet enabled in this build.
        </div>

        {{-- ═══ COMMON HEADER FIELDS (shown once category/type resolved) ═══ --}}
        <div id="commonFields" style="display:none">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Supplier <span class="text-danger">*</span></label>
              <select name="vendor_id" id="vendor_select" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label>From Location</label>
              <select name="from_location_id" id="from_location_select" class="form-control select2-js" disabled>
                <option value="">Select Supplier first</option>
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label>Drop Off Location <span class="text-danger">*</span></label>
              <select name="drop_off_location_id" class="form-control select2-js" required>
                <option value="">Select Location</option>
                @foreach($dropOffLocations as $loc)<option value="{{ $loc->id }}">{{ $loc->name }}</option>@endforeach
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
          </div>

          {{-- Broker (yarn/greige only) --}}
          <div class="row" id="brokerSection" style="display:none">
            <div class="col-md-4 mb-3">
              <label>Broker <span class="text-muted">(optional)</span></label>
              <select name="broker_id" id="broker_select" class="form-control select2-js">
                <option value="">No Broker</option>
                @foreach($brokers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3" id="brokerAmountField" style="display:none">
              <label>Broker Commission Amount</label>
              <input type="number" name="broker_commission_amount" id="broker_commission_amount" class="form-control comma-input" step="any" min="0" value="0">
            </div>
          </div>

          {{-- Payment Terms --}}
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Payment Term <span class="text-danger">*</span></label>
              <select name="payment_term_type" id="payment_term_type" class="form-control" required>
                <option value="cash" selected>Cash</option>
                <option value="credit">Credit</option>
                <option value="pdc">PDC</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-3 mb-3" id="paymentDaysField" style="display:none">
              <label>Days</label>
              <select name="payment_term_days" id="payment_term_days_select" class="form-control">
                <option value="30">30 days</option>
                <option value="60">60 days</option>
                <option value="90">90 days</option>
                <option value="0">Custom (enter below)</option>
              </select>
              <input type="number" name="payment_term_days_custom" class="form-control mt-1 comma-input" placeholder="Custom days" style="display:none">
            </div>
            <div class="col-md-4 mb-3" id="paymentNoteField" style="display:none">
              <label>Note</label>
              <input type="text" name="payment_term_note" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>GST Terms <span class="text-danger">*</span></label>
              <select name="gst_applicable" id="gst_applicable" class="form-control" required>
                <option value="1">With GST</option>
                <option value="0">Without GST</option>
              </select>
            </div>
            <div class="col-md-3 mb-3" id="tax_field">
              <label>Tax</label>
              <select name="tax_id" id="tax_select" class="form-control">
                <option value="">Select Tax</option>
                @foreach($taxes as $tax)<option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected($tax->is_default)>{{ $tax->name }}</option>@endforeach
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>
            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          {{-- ═══ PURCHASE TYPE: item grid ═══ --}}
          <div id="purchaseItemsSection" style="display:none">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th width="24%">Item (search by name or code)</th>
                  <th>Unit</th><th>Link Forecast</th><th>Quantity</th><th>Rate</th><th>Amount</th><th></th>
                </tr>
              </thead>
              <tbody id="itemsBody"></tbody>
              <tfoot>
                <tr><td colspan="4" class="text-end">Subtotal:</td><td colspan="2" class="text-end" id="subtotalDisplay">0.00</td><td></td></tr>
                <tr><td colspan="4" class="text-end">GST:</td><td colspan="2" class="text-end" id="gstDisplay">0.00</td><td></td></tr>
                <tr><td colspan="4" class="text-end">Broker Commission:</td><td colspan="2" class="text-end" id="brokerDisplay">0.00</td><td></td></tr>
                <tr class="fw-bold"><td colspan="4" class="text-end">Total:</td><td colspan="2" class="text-end" id="totalDisplay">0.00</td><td></td></tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Item</button>
          </div>

          {{-- ═══ WEAVING TYPE: CPO formula fields ═══ --}}
          <div id="weavingSection" style="display:none">
            <hr><h6>Weaving / CPO Details</h6>
            <div class="row">
              <div class="col-md-4 mb-3"><label>Warp Yarn <span class="text-danger">*</span></label>
                <select name="warp_product_id" class="form-control select2-js">
                  <option value="">Select Yarn</option>
                  @foreach($yarnProducts as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                </select>
              </div>
              <div class="col-md-4 mb-3"><label>Weft Yarn <span class="text-danger">*</span></label>
                <select name="weft_product_id" class="form-control select2-js">
                  <option value="">Select Yarn</option>
                  @foreach($yarnProducts as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                </select>
              </div>
              <div class="col-md-4 mb-3"><label>Output Greige Product</label>
                <select name="greige_product_id" class="form-control select2-js">
                  <option value="">Not specified yet</option>
                  @foreach($greigeProducts as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                </select>
              </div>

              <div class="col-md-2 mb-3"><label>Warp Count</label><input type="number" name="warp_count" id="warp_count" class="form-control comma-input calc-input" step="any" min="0.01"></div>
              <div class="col-md-2 mb-3"><label>Weft Count</label><input type="number" name="weft_count" id="weft_count" class="form-control comma-input calc-input" step="any" min="0.01"></div>
              <div class="col-md-2 mb-3"><label>Reed</label><input type="number" name="reed_input" id="reed_input" class="form-control comma-input calc-input" step="any" min="0.01"></div>
              <div class="col-md-2 mb-3"><label>Pick</label><input type="number" name="pick" id="pick" class="form-control calc-input comma-input" step="any" min="0.01"></div>
              <div class="col-md-2 mb-3"><label>Width</label><input type="number" name="width" id="width" class="form-control calc-input comma-input" step="any" min="0.01"></div>
              <div class="col-md-2 mb-3"><label>Reed Count</label><input type="number" name="reed_count" id="reed_count" class="form-control comma-input calc-input" step="any" min="0.01"></div>

              <div class="col-md-2 mb-3"><label>Reed Space <small class="text-muted">(auto/editable)</small></label><input type="number" name="reed_space" id="reed_space" class="form-control comma-input calc-input" step="any" min="0.01" placeholder="Auto-calculated"></div>
              <div class="col-md-2 mb-3"><label>Total Meters</label><input type="number" name="total_meters_required" id="total_meters_required" class="form-control comma-input calc-input" step="any" min="0.001"></div>
              <div class="col-md-2 mb-3"><label>Rate per Pick</label><input type="number" name="rate_per_pick" id="rate_per_pick" class="form-control calc-input comma-input" step="any" min="0"></div>
              <div class="col-md-2 mb-3"><label>Sizing (lbs)</label><input type="number" name="sizing_lbs" id="sizing_lbs" class="form-control calc-input comma-input" step="any" min="0" value="0"></div>
              <div class="col-md-2 mb-3"><label>Warping</label><input type="number" name="warping" id="warping" class="form-control calc-input comma-input" step="any" min="0.01" value="0"></div>

              <div class="col-md-3 mb-3"><label>Warp Shrinkage %</label><input type="number" name="warp_conversion_pct" id="warp_conversion_pct" class="form-control calc-input comma-input" step="any" min="0" value="0"></div>
              <div class="col-md-3 mb-3"><label>Weft Shrinkage %</label><input type="number" name="weft_conversion_pct" id="weft_conversion_pct" class="form-control calc-input comma-input" step="any" min="0" value="0"></div>
              <div class="col-md-3 mb-3"><label>Warp Yarn Cost Price</label><input type="number" name="warp_yarn_cost_price" id="warp_yarn_cost_price" class="form-control calc-input comma-input" step="any" min="0" value="0"></div>
              <div class="col-md-3 mb-3"><label>Weft Yarn Cost Price</label><input type="number" name="weft_yarn_cost_price" id="weft_yarn_cost_price" class="form-control calc-input comma-input" step="any" min="0" value="0"></div>
            </div>

            <h6>Calculated Preview</h6>
            <table class="table table-bordered table-sm">
              <tbody>
                <tr><td>Item Name</td><td id="p_item_name">—</td></tr>
                <tr><td>Warp GSM</td><td id="p_warp_gsm">—</td></tr>
                <tr><td>Weft GSM</td><td id="p_weft_gsm">—</td></tr>
                <tr><td><strong>Total GSM</strong></td><td id="p_gsm">—</td></tr>
                <tr><td><strong>Per Meter Kg</strong></td><td id="p_gsm_kg">—</td></tr>
                <tr><td>Reed Space</td><td id="p_reed_space">—</td></tr>
                <tr><td>Warp Consumption (lbs/m)</td><td id="p_warp_consumption">—</td></tr>
                <tr><td>Weft Consumption (lbs/m)</td><td id="p_weft_consumption">—</td></tr>
                <tr><td><strong>Total Yarn Weight Consumed (lbs)</strong></td><td id="p_total_yarn_weight_consumed">—</td></tr>
                <tr><td>Warp Yarn Rate (Rs/m)</td><td id="p_warp_yarn_rate">—</td></tr>
                <tr><td>Weft Yarn Rate (Rs/m)</td><td id="p_weft_yarn_rate">—</td></tr>
                <tr><td><strong>Total Yarn Cost per Meter</strong></td><td id="p_total_yarn_cost_per_meter">—</td></tr>
                <tr><td>Weaving Cost (Rs/m)</td><td id="p_weaving_cost_per_meter">—</td></tr>
                <tr><td>Sizing Rate per Meter</td><td id="p_sizing_rate_per_meter">—</td></tr>
                <tr><td><strong>Weaving Per Meter</strong></td><td id="p_weaving_per_meter">—</td></tr>
                <tr><td><strong>Fabric Cost (per meter)</strong></td><td id="p_fabric_cost">—</td></tr>
                <tr><td><strong>Weaving Cost (Total)</strong></td><td id="p_weaving_cost">—</td></tr>
                <tr><td>GST</td><td id="p_gst_amount">—</td></tr>
                <tr class="fw-bold"><td>Net Amount</td><td id="p_net_amount">—</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success" id="submitBtn" style="display:none">Save Purchase Order</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = 0;
  let categoryProducts = [];
  const units = @json($units->map(fn($u) => ['id' => $u->id, 'label' => $u->name . ' (' . $u->shortcode . ')']));

  function unformatNumber(value) {
    return parseFloat((value || '').toString().replace(/,/g, '')) || 0;
  }

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#category_select').on('change', function () {
    const catId = $(this).val();
    const code = $(this).find('option:selected').data('code');
    resetAll();

    if (!catId) { $('#categoryMsg').show().text('Select a Category to continue.'); return; }

    if (code === 'greige') {
      $('#type_field').show();
      $('#categoryMsg').show().text('Select a Type to continue.');
    } else {
      $('#po_type').val('purchase');
      loadCategoryProducts(catId).then(() => showPurchaseFlow(code));
    }
  });

  function loadCategoryProducts(catId) {
    return fetch(`/purchase-orders/category-products/${catId}`)
      .then(r => r.json())
      .then(products => { categoryProducts = products; });
  }

  $('#type_select').on('change', function () {
    const type = $(this).val();
    $('#po_type').val(type);
    $('#weavingSection, #purchaseItemsSection, #service_type_field, #processingSection').hide();
    $('#submitBtn').hide();

    if (type === 'purchase') {
      $('#categoryMsg').hide();
      $('#commonFields, #purchaseItemsSection, #brokerSection, #submitBtn').show();
      loadCategoryProducts($('#category_select').val()).then(() => {
        if ($('#itemsBody').children().length === 0) addRow();
      });
    } else if (type === 'weaving') {
      $('#categoryMsg').hide();
      $('#commonFields, #weavingSection, #brokerSection, #submitBtn').show();
    } else if (type === 'processing') {
      $('#categoryMsg').hide();
      $('#commonFields, #service_type_field, #processingSection, #submitBtn').show();
      addProcRow();
    }
  });

  function showPurchaseFlow(code) {
    $('#categoryMsg').hide();
    $('#commonFields, #purchaseItemsSection, #submitBtn').show();
    $('#brokerSection').toggle(code === 'yarn');
    if ($('#itemsBody').children().length === 0) addRow();
  }

  function resetAll() {
    $('#type_field, #service_type_field, #commonFields, #purchaseItemsSection, #weavingSection, #brokerSection, #processingMsg, #submitBtn').hide();
    $('#itemsBody').empty();
    rowIndex = 0;
    $('#po_type').val('');
  }

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

  $('#broker_select').on('change', function () {
    $('#brokerAmountField').toggle(!!$(this).val());
    recalcPurchaseTotal();
  });

  $('#payment_term_type').on('change', function () {
    const val = $(this).val();
    $('#paymentDaysField').toggle(val === 'credit' || val === 'pdc');
    $('#paymentNoteField').toggle(val === 'other');
  });

  $(document).on('change', '#payment_term_days_select', function () {
    const isCustom = $(this).val() === '0';
    $('#paymentDaysField input[name="payment_term_days_custom"]').toggle(isCustom);
  });

  $('#gst_applicable').on('change', function () { $('#tax_field').toggle($(this).val() === '1'); recalcPurchaseTotal(); recalcCpo(); });

  function unitOptionsHtml(selectedId) {
    let html = '';
    units.forEach(u => html += `<option value="${u.id}" ${u.id == selectedId ? 'selected' : ''}>${u.label}</option>`);
    return html;
  }

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    categoryProducts.forEach(p => html += `<option value="${p.id}" data-unit="${p.measurement_unit}">${p.name} — ${p.sku}</option>`);
    return html;
  }

  function addRow() {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>${productOptionsHtml()}</select></td>
        <td><select name="items[${idx}][measurement_unit]" class="form-control unit-select">${unitOptionsHtml(null)}</select></td>
        <td><select name="items[${idx}][forecast_id]" class="form-control forecast-select" disabled><option value="">—</option></select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input comma-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control rate-input comma-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }
  $('#addRowBtn').on('click', addRow);

  $(document).on('change', '.product-select', function () {
    const row = $(this).closest('tr');
    const opt = $(this).find('option:selected');
    row.find('.unit-select').html(unitOptionsHtml(opt.data('unit')));

    const productId = $(this).val();
    const $fc = row.find('.forecast-select');
    $fc.prop('disabled', true).html('<option value="">—</option>');
    if (!productId) return;
    fetch(`/purchase-orders/forecasts-for-product/${productId}`).then(r => r.json()).then(forecasts => {
      let html = '<option value="">No forecast</option>';
      forecasts.forEach(f => html += `<option value="${f.id}" data-shortfall="${f.shortfall_qty}">${f.forecast_no} (${f.shortfall_qty})</option>`);
      $fc.html(html).prop('disabled', forecasts.length === 0);
    });
  });

  $(document).on('change', '.forecast-select', function () {
    const shortfall = $(this).find('option:selected').data('shortfall');
    if (shortfall !== undefined) $(this).closest('tr').find('.qty-input').val(shortfall).trigger('input');
  });

  $(document).on('input', '.qty-input, .rate-input', function () {
    const row = $(this).closest('tr');
    const qty = unformatNumber(row.find('.qty-input').val());
    const rate = unformatNumber(row.find('.rate-input').val());
    row.find('.amount-cell').text((qty * rate).toFixed(2));
    recalcPurchaseTotal();
  });

  $(document).on('input change', '#broker_commission_amount', recalcPurchaseTotal);
  $(document).on('change', '#tax_select', function () { recalcPurchaseTotal(); recalcCpo(); });

  function recalcPurchaseTotal() {
    let subtotal = 0;
    $('.item-row').each(function () { subtotal += unformatNumber($(this).find('.amount-cell').text()); });
    const gstApplicable = $('#gst_applicable').val() === '1';
    const rate = gstApplicable ? (parseFloat($('#tax_select').find('option:selected').data('rate')) || 0) : 0;
    const gst = subtotal * (rate / 100);
    const brokerAmt = $('#broker_select').val() ? unformatNumber($('#broker_commission_amount').val()) : 0;

    $('#subtotalDisplay').text(subtotal.toFixed(2));
    $('#gstDisplay').text(gst.toFixed(2));
    $('#brokerDisplay').text(brokerAmt.toFixed(2));
    $('#totalDisplay').text((subtotal + gst + brokerAmt).toFixed(2));
  }

  $(document).on('click', '.remove-row', function () { if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcPurchaseTotal(); } });

  // ── Reed Space live calc ──
  let reedSpaceManuallyEdited = false;

  $(document).on('input', '#reed_space', function () {
    reedSpaceManuallyEdited = $(this).val().trim() !== '';
    if (!reedSpaceManuallyEdited) recalcReedSpace();
  });

  function recalcReedSpace() {
    if (reedSpaceManuallyEdited) return;
    const reed = unformatNumber($('#reed_input').val());
    const width = unformatNumber($('#width').val());
    const reedCount = unformatNumber($('#reed_count').val());
    if (reed > 0 && width > 0 && reedCount > 0) {
      const reedSpace = (reed * width) / reedCount;
      $('#reed_space').val(reedSpace.toFixed(4));
    }
  }

  $(document).on('keyup', '#reed_count', recalcReedSpace);
  $(document).on('input', '#reed_input, #width', recalcReedSpace);

  // ── CPO live calc ──
  let calcTimer = null;
  $(document).on('input change', '.calc-input', function () { clearTimeout(calcTimer); calcTimer = setTimeout(recalcCpo, 300); });

  function recalcCpo() {
    const required = ['reed_input','reed_count','warp_count','weft_count','pick','width','total_meters_required','rate_per_pick'];
    for (const f of required) if (!$('#' + f).val()) return;

    const gstApplicable = $('#gst_applicable').val() === '1';
    const gstRate = gstApplicable ? (parseFloat($('#tax_select').find('option:selected').data('rate')) || 0) : 0;

    const payload = {
      reed: unformatNumber($('#reed_input').val()),
      reed_count: unformatNumber($('#reed_count').val()),
      reed_space: $('#reed_space').val() ? unformatNumber($('#reed_space').val()) : '',
      warp_count: unformatNumber($('#warp_count').val()),
      weft_count: unformatNumber($('#weft_count').val()),
      pick: unformatNumber($('#pick').val()),
      width: unformatNumber($('#width').val()),
      total_meters_required: unformatNumber($('#total_meters_required').val()),
      rate_per_pick: unformatNumber($('#rate_per_pick').val()),
      sizing_lbs: unformatNumber($('#sizing_lbs').val()) || 0,
      warping: unformatNumber($('#warping').val()) || 1,
      warp_shrinkage_pct: unformatNumber($('#warp_conversion_pct').val()) || 0,
      weft_shrinkage_pct: unformatNumber($('#weft_conversion_pct').val()) || 0,
      warp_yarn_cost_price: unformatNumber($('#warp_yarn_cost_price').val()) || 0,
      weft_yarn_cost_price: unformatNumber($('#weft_yarn_cost_price').val()) || 0,
      gst_applicable: gstApplicable ? 1 : 0, gst_rate: gstRate, _token: '{{ csrf_token() }}',
    };

    fetch('{{ route("purchase_orders.calculate") }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: new URLSearchParams(payload),
    }).then(r => r.json()).then(data => {
      $('#p_item_name').text(data.item_name);
      $('#p_warp_gsm').text(data.warp_gsm);
      $('#p_weft_gsm').text(data.weft_gsm);
      $('#p_gsm').text(data.gsm);
      $('#p_gsm_kg').text(data.gsm_kg);
      $('#p_reed_space').text(data.reed_space);
      if (!$('#reed_space').val()) $('#reed_space').attr('placeholder', data.reed_space);
      $('#p_warp_consumption').text(data.warp_consumption);
      $('#p_weft_consumption').text(data.weft_consumption);
      $('#p_total_yarn_weight_consumed').text(data.total_yarn_weight_consumed);
      $('#p_warp_yarn_rate').text(data.warp_yarn_rate);
      $('#p_weft_yarn_rate').text(data.weft_yarn_rate);
      $('#p_total_yarn_cost_per_meter').text(data.total_yarn_cost_per_meter);
      $('#p_weaving_cost_per_meter').text(data.weaving_cost_per_meter);
      $('#p_sizing_rate_per_meter').text(data.sizing_rate_per_meter);
      $('#p_weaving_per_meter').text(data.weaving_per_meter);
      $('#p_fabric_cost').text(data.fabric_cost);
      $('#p_weaving_cost').text(data.weaving_cost);
      $('#p_gst_amount').text(data.gst_amount);
      $('#p_net_amount').text(data.net_amount);
    });
  }

  $('form').on('submit', function () {
    const $customInput = $('input[name="payment_term_days_custom"]:visible');
    if ($customInput.length && $customInput.val()) {
      const customVal = unformatNumber($customInput.val());
      const $select = $('#payment_term_days_select');
      if ($select.find(`option[value="${customVal}"]`).length === 0) {
        $select.append(`<option value="${customVal}"></option>`);
      }
      $select.val(customVal);
    }
  });
</script>
@endsection