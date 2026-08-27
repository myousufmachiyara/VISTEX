@extends('layouts.app')
@section('title', 'Conversion PO | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('cpo.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Conversion PO (Weaving)</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Weaving Mill (Vendor) <span class="text-danger">*</span></label>
            <select name="vendor_id" class="form-control select2-js" required>
              <option value="">Select Vendor</option>
              @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Warp Yarn <span class="text-danger">*</span></label>
            <select name="warp_product_id" class="form-control select2-js" required>
              <option value="">Select Yarn</option>
              @foreach($yarnProducts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Weft Yarn <span class="text-danger">*</span></label>
            <select name="weft_product_id" class="form-control select2-js" required>
              <option value="">Select Yarn</option>
              @foreach($yarnProducts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>

          <div class="col-md-4 mb-3">
            <label>Output Greige Product <span class="text-muted">(optional)</span></label>
            <select name="greige_product_id" class="form-control select2-js">
              <option value="">Not specified yet</option>
              @foreach($greigeProducts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Link Forecast <span class="text-muted">(optional)</span></label>
            <select name="forecast_id" class="form-control select2-js">
              <option value="">No forecast linked</option>
              @foreach($forecasts as $f)
                <option value="{{ $f->id }}">{{ $f->forecast_no }} — {{ $f->customer->name ?? 'General' }} — {{ $f->product->name ?? '' }} (shortfall: {{ $f->shortfall_qty }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>PO Date <span class="text-danger">*</span></label>
            <input type="date" name="po_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
        </div>

        <hr>
        <h6>Formula Inputs</h6>
        <div class="row">
          <div class="col-md-2 mb-3"><label>Warp Count <span class="text-danger">*</span></label><input type="number" name="warp_count" id="warp_count" class="form-control calc-input" step="any" min="0.01" required></div>
          <div class="col-md-2 mb-3"><label>Weft Count <span class="text-danger">*</span></label><input type="number" name="weft_count" id="weft_count" class="form-control calc-input" step="any" min="0.01" required></div>
          <div class="col-md-2 mb-3"><label>Reed Count <span class="text-danger">*</span></label><input type="number" name="reed_count" id="reed_count" class="form-control calc-input" step="any" min="0.01" required></div>
          <div class="col-md-2 mb-3"><label>Pick <span class="text-danger">*</span></label><input type="number" name="pick" id="pick" class="form-control calc-input" step="any" min="0.01" required></div>
          <div class="col-md-2 mb-3"><label>Width <span class="text-danger">*</span></label><input type="number" name="width" id="width" class="form-control calc-input" step="any" min="0.01" required></div>
          <div class="col-md-2 mb-3"><label>Total Meters Req. <span class="text-danger">*</span></label><input type="number" name="total_meters_required" id="total_meters_required" class="form-control calc-input" step="any" min="0.001" required></div>

          <div class="col-md-3 mb-3"><label>Rate per Pick <span class="text-danger">*</span></label><input type="number" name="rate_per_pick" id="rate_per_pick" class="form-control calc-input" step="any" min="0" required></div>
          <div class="col-md-3 mb-3"><label>Sizing (lbs)</label><input type="number" name="sizing_lbs" id="sizing_lbs" class="form-control calc-input" step="any" min="0" value="0"></div>
          <div class="col-md-3 mb-3"><label>Warp Conversion %</label><input type="number" name="warp_conversion_pct" id="warp_conversion_pct" class="form-control calc-input" step="any" min="0" value="0"></div>

          <div class="col-md-3 mb-3">
            <label>GST Terms <span class="text-danger">*</span></label>
            <select name="gst_applicable" id="gst_applicable" class="form-control calc-input" required>
              <option value="1">With GST</option>
              <option value="0">Without GST</option>
            </select>
          </div>
          <div class="col-md-4 mb-3" id="tax_field">
            <label>Tax</label>
            <select name="tax_id" id="tax_select" class="form-control calc-input">
              <option value="">Select Tax</option>
              @foreach($taxes as $tax)<option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}" @selected($tax->is_default)>{{ $tax->name }}</option>@endforeach
            </select>
          </div>
        </div>

        <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>

        <hr>
        <h6>Calculated Preview</h6>
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>Item Name</td><td id="p_item_name">—</td></tr>
            <tr><td>GSM</td><td id="p_gsm">—</td></tr>
            <tr><td>Warp Consumption (lbs/m)</td><td id="p_warp_consumption">—</td></tr>
            <tr><td>Weft Consumption (lbs/m)</td><td id="p_weft_consumption">—</td></tr>
            <tr><td>Total Greige Qty Required (lbs/m)</td><td id="p_total_greige_qty_required">—</td></tr>
            <tr><td><strong>Total Yarn Weight Consumed (lbs)</strong></td><td id="p_total_yarn_weight_consumed">—</td></tr>
            <tr><td>Rate per Meter</td><td id="p_rate_per_meter">—</td></tr>
            <tr><td>Sizing per Meter</td><td id="p_sizing_per_meter">—</td></tr>
            <tr><td>Weaving Rate (Rs/m)</td><td id="p_weaving_rate">—</td></tr>
            <tr><td><strong>Weaving Cost</strong></td><td id="p_weaving_cost">—</td></tr>
            <tr><td>GST Amount</td><td id="p_gst_amount">—</td></tr>
            <tr class="fw-bold"><td>Net Amount</td><td id="p_net_amount">—</td></tr>
          </tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save CPO</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); toggleGst(); });

  $('#gst_applicable').on('change', toggleGst);
  function toggleGst() { $('#tax_field').toggle($('#gst_applicable').val() === '1'); recalc(); }

  let calcTimer = null;
  $(document).on('input change', '.calc-input', function () {
    clearTimeout(calcTimer);
    calcTimer = setTimeout(recalc, 300);
  });

  function recalc() {
    const required = ['warp_count','weft_count','reed_count','pick','width','total_meters_required','rate_per_pick'];
    for (const f of required) {
      if (!$('#' + f).val()) return;
    }

    const gstApplicable = $('#gst_applicable').val() === '1';
    const gstRate = gstApplicable ? (parseFloat($('#tax_select').find('option:selected').data('rate')) || 0) : 0;

    const payload = {
      warp_count: $('#warp_count').val(),
      weft_count: $('#weft_count').val(),
      reed_count: $('#reed_count').val(),
      pick: $('#pick').val(),
      width: $('#width').val(),
      total_meters_required: $('#total_meters_required').val(),
      rate_per_pick: $('#rate_per_pick').val(),
      sizing_lbs: $('#sizing_lbs').val() || 0,
      warp_conversion_pct: $('#warp_conversion_pct').val() || 0,
      gst_applicable: gstApplicable ? 1 : 0,
      gst_rate: gstRate,
      _token: '{{ csrf_token() }}',
    };

    fetch('{{ route("cpo.calculate") }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: new URLSearchParams(payload),
    })
    .then(r => r.json())
    .then(data => {
      $('#p_item_name').text(data.item_name);
      $('#p_gsm').text(data.gsm);
      $('#p_warp_consumption').text(data.warp_consumption);
      $('#p_weft_consumption').text(data.weft_consumption);
      $('#p_total_greige_qty_required').text(data.total_greige_qty_required);
      $('#p_total_yarn_weight_consumed').text(data.total_yarn_weight_consumed);
      $('#p_rate_per_meter').text(data.rate_per_meter);
      $('#p_sizing_per_meter').text(data.sizing_per_meter);
      $('#p_weaving_rate').text(data.weaving_rate);
      $('#p_weaving_cost').text(data.weaving_cost);
      $('#p_gst_amount').text(data.gst_amount);
      $('#p_net_amount').text(data.net_amount);
    })
    .catch(() => {});
  }
</script>
@endsection