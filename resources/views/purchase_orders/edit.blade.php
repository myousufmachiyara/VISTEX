@extends('layouts.app')
@section('title', 'Purchase Order | Edit')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_orders.update', $order->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="type" value="{{ $order->type }}">
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
            <select name="payment_term_days" id="payment_term_days_select" class="form-control">
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

        {{-- ═══ PURCHASE TYPE: item grid ═══ --}}
        @if($order->type === 'purchase')
        <div id="purchaseItemsSection">
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
        @endif

        {{-- ═══ WEAVING TYPE: CPO formula fields ═══ --}}
        @if($order->type === 'weaving')
        <div id="weavingSection">
          <hr><h6>Weaving / CPO Details</h6>
          <div class="row">
            <div class="col-md-4 mb-3"><label>Warp Yarn <span class="text-danger">*</span></label>
              <select name="warp_product_id" class="form-control select2-js">
                <option value="">Select Yarn</option>
                @foreach($yarnProducts as $p)<option value="{{ $p->id }}" @selected($p->id == $order->warp_product_id)>{{ $p->name }} ({{ $p->sku }})</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3"><label>Weft Yarn <span class="text-danger">*</span></label>
              <select name="weft_product_id" class="form-control select2-js">
                <option value="">Select Yarn</option>
                @foreach($yarnProducts as $p)<option value="{{ $p->id }}" @selected($p->id == $order->weft_product_id)>{{ $p->name }} ({{ $p->sku }})</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3"><label>Output Greige Product</label>
              <select name="greige_product_id" class="form-control select2-js">
                <option value="">Not specified yet</option>
                @foreach($greigeProducts as $p)<option value="{{ $p->id }}" @selected($p->id == $order->greige_product_id)>{{ $p->name }} ({{ $p->sku }})</option>@endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3"><label>Warp Count</label><input type="number" name="warp_count" id="warp_count" class="form-control comma-input calc-input" step="any" min="0.01" value="{{ $order->warp_count }}"></div>
            <div class="col-md-2 mb-3"><label>Weft Count</label><input type="number" name="weft_count" id="weft_count" class="form-control comma-input calc-input" step="any" min="0.01" value="{{ $order->weft_count }}"></div>
            <div class="col-md-2 mb-3"><label>Reed</label><input type="number" name="reed_input" id="reed_input" class="form-control comma-input calc-input" step="any" min="0.01" value="{{ $order->reed ?? '' }}"></div>
            <div class="col-md-2 mb-3"><label>Pick</label><input type="number" name="pick" id="pick" class="form-control calc-input comma-input" step="any" min="0.01" value="{{ $order->pick }}"></div>
            <div class="col-md-2 mb-3"><label>Width</label><input type="number" name="width" id="width" class="form-control calc-input comma-input" step="any" min="0.01" value="{{ $order->width }}"></div>
            <div class="col-md-2 mb-3"><label>Reed Count</label><input type="number" name="reed_count" id="reed_count" class="form-control comma-input calc-input" step="any" min="0.01" value="{{ $order->reed_count }}"></div>

            <div class="col-md-2 mb-3"><label>Reed Space <small class="text-muted">(auto/editable)</small></label><input type="number" name="reed_space" id="reed_space" class="form-control comma-input calc-input" step="any" min="0.01" value="{{ $order->reed_space ?? '' }}"></div>
            <div class="col-md-2 mb-3"><label>Total Meters</label><input type="number" name="total_meters_required" id="total_meters_required" class="form-control comma-input calc-input" step="any" min="0.001" value="{{ $order->total_meters_required }}"></div>
            <div class="col-md-2 mb-3"><label>Rate per Pick</label><input type="number" name="rate_per_pick" id="rate_per_pick" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->rate_per_pick }}"></div>
            <div class="col-md-2 mb-3"><label>Sizing (lbs)</label><input type="number" name="sizing_lbs" id="sizing_lbs" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->sizing_lbs ?? 0 }}"></div>
            <div class="col-md-2 mb-3"><label>Warping</label><input type="number" name="warping" id="warping" class="form-control calc-input comma-input" step="any" min="0.01" value="{{ $order->warping ?? 0 }}"></div>

            <div class="col-md-3 mb-3"><label>Warp Shrinkage %</label><input type="number" name="warp_conversion_pct" id="warp_conversion_pct" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->warp_conversion_pct ?? 0 }}"></div>
            <div class="col-md-3 mb-3"><label>Weft Shrinkage %</label><input type="number" name="weft_conversion_pct" id="weft_conversion_pct" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->weft_conversion_pct ?? 0 }}"></div>
            <div class="col-md-3 mb-3"><label>Warp Yarn Cost Price</label><input type="number" name="warp_yarn_cost_price" id="warp_yarn_cost_price" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->warp_yarn_cost_price ?? 0 }}"></div>
            <div class="col-md-3 mb-3"><label>Weft Yarn Cost Price</label><input type="number" name="weft_yarn_cost_price" id="weft_yarn_cost_price" class="form-control calc-input comma-input" step="any" min="0" value="{{ $order->weft_yarn_cost_price ?? 0 }}"></div>
          </div>

        <h6>Calculated Preview</h6>
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>Item Name</td><td id="p_item_name">{{ $order->item_name ?? '—' }}</td></tr>
            <tr><td>Warp GSM</td><td id="p_warp_gsm">{{ $order->warp_gsm ?? '—' }}</td></tr>
            <tr><td>Weft GSM</td><td id="p_weft_gsm">{{ $order->weft_gsm ?? '—' }}</td></tr>
            <tr><td><strong>Total GSM</strong></td><td id="p_gsm">{{ $order->gsm ?? '—' }}</td></tr>
            <tr><td>GSM (Kg)</td><td id="p_gsm_kg">{{ $order->gsm_kg ?? '—' }}</td></tr>
            <tr><td>Reed Space</td><td id="p_reed_space">{{ $order->reed_space ?? '—' }}</td></tr>
            <tr><td>Warp Consumption (lbs/m)</td><td id="p_warp_consumption">{{ $order->warp_consumption ?? '—' }}</td></tr>
            <tr><td>Weft Consumption (lbs/m)</td><td id="p_weft_consumption">{{ $order->weft_consumption ?? '—' }}</td></tr>
            <tr><td><strong>Total Yarn Weight Consumed (lbs)</strong></td><td id="p_total_yarn_weight_consumed">{{ $order->total_yarn_weight_consumed ?? '—' }}</td></tr>
            <tr><td>Warp Yarn Rate (Rs/m)</td><td id="p_warp_yarn_rate">{{ $order->warp_yarn_rate ?? '—' }}</td></tr>
            <tr><td>Weft Yarn Rate (Rs/m)</td><td id="p_weft_yarn_rate">{{ $order->weft_yarn_rate ?? '—' }}</td></tr>
            <tr><td>Weaving Cost (Rs/m)</td><td id="p_weaving_cost_per_meter">{{ $order->weaving_cost_per_meter ?? '—' }}</td></tr>
            <tr><td>Sizing Rate per Meter</td><td id="p_sizing_rate_per_meter">{{ $order->sizing_rate_per_meter ?? '—' }}</td></tr>
            <tr><td><strong>Weaving Per Meter</strong></td><td id="p_weaving_per_meter">{{ $order->weaving_per_meter ?? '—' }}</td></tr>
            <tr><td><strong>Fabric Cost (per meter)</strong></td><td id="p_fabric_cost">{{ $order->fabric_cost ?? '—' }}</td></tr>
            <tr><td><strong>Weaving Cost (Total)</strong></td><td id="p_weaving_cost">{{ $order->weaving_cost ?? '—' }}</td></tr>
            <tr><td>GST</td><td id="p_gst_amount">{{ $order->gst_amount ?? '—' }}</td></tr>
            <tr class="fw-bold"><td>Net Amount</td><td id="p_net_amount">{{ $order->total_amount ?? '—' }}</td></tr>
          </tbody>
        </table>
        </div>
        @endif

      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Update Purchase Order</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = {{ $order->type === 'purchase' ? $order->items->count() : 0 }};
  let categoryProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]));

  function unformatNumber(value) {
    return parseFloat((value || '').toString().replace(/,/g, '')) || 0;
  }

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

    @if($order->type === 'weaving')
      recalcCpo();
    @endif
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

  $(document).on('change', '#payment_term_days_select', function () {
    const isCustom = $(this).val() === '0';
    $('#paymentDaysField input[name="payment_term_days_custom"]').toggle(isCustom);
  });

  $('#gst_applicable').on('change', function () { toggleGst(); recalcCpo(); });
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
    const qty = unformatNumber(row.find('.qty-input').val());
    const price = unformatNumber(row.find('.price-input').val());
    row.find('.amount-cell').text((qty * price).toFixed(2));
    recalcTotal();
  });

  function recalcCpo() {
    if ($('#reed_input').length === 0) return;
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
      $('#p_weaving_cost_per_meter').text(data.weaving_cost_per_meter);
      $('#p_sizing_rate_per_meter').text(data.sizing_rate_per_meter);
      $('#p_weaving_per_meter').text(data.weaving_per_meter);
      $('#p_fabric_cost').text(data.fabric_cost);
      $('#p_weaving_cost').text(data.weaving_cost);
      $('#p_gst_amount').text(data.gst_amount);
      $('#p_net_amount').text(data.net_amount);
    });
  }

  $(document).on('change', '#tax_select', function () { recalcTotal(); recalcCpo(); });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotal(); }
  });

  // ── Reed Space live calc ──
  let reedSpaceManuallyEdited = {{ $order->reed_space ? 'true' : 'false' }};

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

  // ── CPO live calc (weaving type only) ──
  let calcTimer = null;
  $(document).on('input change', '.calc-input', function () { clearTimeout(calcTimer); calcTimer = setTimeout(recalcCpo, 300); });

  function recalcCpo() {
    if ($('#reed_input').length === 0) return;
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
      $('#p_weaving_cost_per_meter').text(data.weaving_cost_per_meter);
      $('#p_sizing_rate_per_meter').text(data.sizing_rate_per_meter);
      $('#p_weaving_per_meter').text(data.weaving_per_meter);
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