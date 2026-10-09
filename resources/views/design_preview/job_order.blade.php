@extends('layouts.app')
@section('title', 'Customer Order (Job Order) | Design Preview')
@section('content')
@php
  // Sample data from customer PO P02835 (Lumin Fabrics / White Owl WB 0826)
  $lines = [
    ['WB-W100-029-862', 'Packed Butterfly Wings', 'Multi', 90],
    ['WB-W105-022-032', 'Packed Flowers', 'Multi', 72],
    ['WB-W110-015-519', 'Packed Floral', 'Navy', 108],
    ['WB-W110-027-167', 'Floral Toss', 'White', 360],
    ['WB-W115-017-007', 'Floral Journal', 'Cream', 72],
    ['WB-W115-029-958', 'Large Floral', 'Light Gray', 270],
    ['WB-W130-005-003', 'Butterfly Wishes', 'Cream', 90],
    ['WB-W130-005-403', 'Butterfly Wishes', 'Indigo', 72],
    ['WB-W130-005-800', 'Butterfly Wishes', 'Multi', 90],
    ['WB-W130-032-410', 'Make a Wish', 'Purple', 72],
    ['WB-W130-032-940', 'Make a Wish', 'Teal', 108],
    ['WB-W140-003-817', 'Sampler', 'Multi', 72],
    ['WB-W175-013-775', 'Floral', 'Navy', 216],
  ];
@endphp
@include('design_preview._kit')

<div class="dp-banner"><i class="fa fa-drafting-compass"></i>
  <div><strong>Design preview — for client feedback.</strong> Layout and fields only; nothing on this page is saved. Orange notes are open questions for the client.</div>
</div>

<div class="row">
  {{-- ═══════════════ MAIN FORM ═══════════════ --}}
  <div class="col-lg-9">
    <section class="card">
      <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h2 class="card-title">New Customer Order (Job Order)</h2>
        <div><span class="text-muted small me-2">JO-0001/26</span><span class="dp-chip draft">Draft</span></div>
      </header>
      <div class="card-body">

        {{-- 1. Customer --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">1</span>Customer</h5><small>Who placed the order</small></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label>Customer <span class="text-danger">*</span></label>
                <div class="input-group">
                  <select class="form-control"><option>Lumin Fabrics — Oceanside, CA</option><option>Island Batik</option></select>
                  <button class="btn btn-outline-secondary" type="button" data-preview="Opens the New Customer form.">+</button>
                </div>
              </div>
              <div class="col-md-4 mb-3"><label>Customer PO # <span class="text-danger">*</span></label><input class="form-control" value="P02835"></div>
              <div class="col-md-4 mb-3"><label>Buyer / Contact</label><input class="form-control" value="Garnet Chua"></div>
              <div class="col-md-4 mb-3">
                <label>Brand / Label</label>
                <select class="form-control"><option>White Owl</option><option>Island Batik</option><option>Tide + Loom Studio</option><option>Ecco Cotton</option></select>
                <span class="dp-hint">Customer's brand this collection is sold under</span>
              </div>
              <div class="col-md-4 mb-3"><label>Collection <span class="text-danger">*</span></label><input class="form-control" value="WB 0826"><span class="dp-hint">Carried into the Processing PO line items</span></div>
              <div class="col-md-4 mb-3"><label>Customer Order Reference</label><input class="form-control dp-auto" value="White Owl WB 0826" readonly><span class="dp-hint">Auto: Brand + Collection</span></div>
            </div>
          </div>
        </div>

        {{-- 2. Dates & Commercial --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">2</span>Dates &amp; Commercial Terms</h5></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-3 mb-3"><label>Order Date <span class="text-danger">*</span></label><input type="date" class="form-control" value="2026-07-04"></div>
              <div class="col-md-3 mb-3"><label>Expected Arrival (Customer)</label><input type="date" class="form-control" id="arrival" value="2026-09-29"></div>
              <div class="col-md-3 mb-3"><label>Transit Days</label><input type="number" class="form-control" id="transit" value="30"></div>
              <div class="col-md-3 mb-3"><label>Ex-Factory Date (Target)</label><input type="date" class="form-control dp-auto" id="exFactory" readonly><span class="dp-hint">Auto: arrival − transit</span></div>

              <div class="col-md-2 mb-3"><label>Currency</label><select class="form-control" id="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>PKR</option></select></div>
              <div class="col-md-2 mb-3"><label>Exchange Rate</label><input type="number" class="form-control" id="fx" value="280.50" step="0.01"></div>
              <div class="col-md-3 mb-3">
                <label>Payment Terms</label>
                <select class="form-control"><option>Credit — 30 days</option><option>Advance</option><option>LC at sight</option><option>Credit — 60 days</option></select>
              </div>
              <div class="col-md-2 mb-3"><label>Incoterm</label><select class="form-control"><option>FOB</option><option>CIF</option><option>CFR</option><option>EXW</option><option>DDP</option></select></div>
              <div class="col-md-3 mb-3"><label>Ship Mode</label><select class="form-control"><option>Sea</option><option>Air</option><option>Courier</option></select></div>
            </div>
            <p class="dp-feedback"><b>Client to confirm:</b> do you need Incoterm, Ship Mode and Ex-Factory date on the order, or only Expected Arrival?</p>
          </div>
        </div>

        {{-- 3. Ship To --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">3</span>Ship To</h5><small>Consignee can differ from the customer</small></div>
          <div class="dp-body">
            <div class="mb-3">
              <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="shipto" id="st1"><label class="form-check-label" for="st1">Same as customer</label></div>
              <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="shipto" id="st2" checked><label class="form-check-label" for="st2">Other consignee</label></div>
            </div>
            <div class="row" id="shipToFields">
              <div class="col-md-4 mb-3"><label>Consignee Name</label><input class="form-control" value="White Fabric - Haryan"></div>
              <div class="col-md-5 mb-3"><label>Address</label><input class="form-control" value="Jl. Jawa 1 No. 12 Timuran RT. 05 RW. 04"></div>
              <div class="col-md-3 mb-3"><label>City / Postal Code</label><input class="form-control" value="Solo 57131"></div>
              <div class="col-md-4 mb-3"><label>Country</label><select class="form-control"><option>Indonesia</option><option>United States</option><option>Pakistan</option></select></div>
              <div class="col-md-4 mb-3"><label>Contact Phone</label><input class="form-control" placeholder="Optional"></div>
            </div>
          </div>
        </div>

        {{-- 4. Fabric Specification --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">4</span>Fabric Specification</h5><small>Applies to all lines · copied into the Processing PO</small></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-4 mb-3"><label>Base Fabric (Greige Quality)</label><select class="form-control"><option>20x20/60x60 Combed 100% CTN — 120"</option><option>40x40/133x72 Cotton — 63"</option></select></div>
              <div class="col-md-2 mb-3"><label>Finished Width</label><input class="form-control" value='108"'></div>
              <div class="col-md-2 mb-3"><label>Finished GSM</label><input class="form-control" value="155"></div>
              <div class="col-md-2 mb-3"><label>Process</label><select class="form-control"><option>Digital Print</option><option>Rotary Print</option><option>Reactive Dyed</option><option>Pigment Print</option></select></div>
              <div class="col-md-2 mb-3"><label>Finish</label><select class="form-control"><option>Sanforized</option><option>Soft Finish</option><option>Peach</option><option>None</option></select></div>
              <div class="col-md-3 mb-3"><label>Packing</label><select class="form-control"><option>Rolls on tube</option><option>Folded (bolts)</option></select></div>
              <div class="col-md-3 mb-3"><label>Roll / Bolt Length</label><input class="form-control" placeholder="e.g. 15 yd per bolt"></div>
            </div>
          </div>
        </div>

        {{-- 5. Order Lines --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">5</span>Order Lines</h5><small id="lineCount"></small></div>
          <div class="dp-body">
            <div class="dp-toolbar">
              <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="fa fa-plus"></i> Add Line</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-preview="Paste rows copied from Excel: Pattern #, Description, Qty, Price."><i class="fa fa-paste"></i> Paste from Excel</button>
              <span class="ms-auto small text-muted">Order unit
                <select class="form-control form-control-sm d-inline-block w-auto ms-1" id="orderUnit"><option value="yd">Yards</option><option value="m">Meters</option></select>
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered dp-table mb-2" id="linesTable">
                <thead>
                  <tr>
                    <th width="3%">#</th><th width="17%">Pattern # (SKU)</th><th>Design</th><th width="11%">Colour</th>
                    <th width="9%" class="text-end">Qty (<span class="u">yd</span>)</th><th width="8%" class="text-end">= Meters</th>
                    <th width="9%" class="text-end">Unit Price</th><th width="7%" class="text-end">Disc %</th>
                    <th width="10%" class="text-end">Amount</th><th width="3%"></th>
                  </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                  <tr><td colspan="4" class="text-end">Total</td><td class="text-end" id="tQty"></td><td class="text-end" id="tM"></td><td></td><td></td><td class="text-end" id="tAmt"></td><td></td></tr>
                </tfoot>
              </table>
            </div>
            <p class="dp-feedback"><b>Client to confirm:</b> should Pattern # pick from a saved pattern list per customer (with last price), or be typed freely? Is tax ever charged on export orders?</p>
          </div>
        </div>

        {{-- 6. Attachments & Remarks --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">6</span>Attachments &amp; Remarks</h5></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-6 mb-3"><label>Customer PO (PDF / scan)</label><input type="file" class="form-control" multiple>
                <span class="dp-hint"><i class="fa fa-paperclip"></i> Purchase_Order_-_P02835.pdf</span></div>
              <div class="col-md-6 mb-3"><label>Remarks</label><textarea class="form-control" rows="2" placeholder="Internal notes for this order"></textarea></div>
            </div>
          </div>
        </div>
      </div>
      <footer class="card-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-preview="Back to the order list.">Cancel</button>
        <div>
          <button type="button" class="btn btn-outline-primary" data-preview="Saves as Draft — only you can see it.">Save as Draft</button>
          <button type="button" class="btn btn-success" data-preview="Confirms the order. It then becomes available when creating a Processing PO.">Confirm Order</button>
        </div>
      </footer>
    </section>
  </div>

  {{-- ═══════════════ SUMMARY ═══════════════ --}}
  <div class="col-lg-3">
    <div class="dp-summary">
      <section class="card mb-3">
        <header class="card-header"><h2 class="card-title" style="font-size:15px">Order Summary</h2></header>
        <div class="card-body">
          <div class="dp-kv"><span>Customer</span><strong>Lumin Fabrics</strong></div>
          <div class="dp-kv"><span>Collection</span><strong>WB 0826</strong></div>
          <div class="dp-kv"><span>Lines</span><strong id="sLines"></strong></div>
          <div class="dp-kv"><span>Total Qty</span><strong id="sQty"></strong></div>
          <div class="dp-kv"><span>In Meters</span><strong id="sM"></strong></div>
          <div class="dp-kv"><span>Order Value</span><strong id="sAmt"></strong></div>
          <div class="dp-kv"><span>In PKR</span><strong id="sPkr"></strong></div>
          <div class="dp-kv"><span>Ex-Factory</span><strong id="sExf"></strong></div>
        </div>
      </section>
      <section class="card">
        <header class="card-header"><h2 class="card-title" style="font-size:15px">Order Progress</h2></header>
        <div class="card-body">
          <ul class="dp-steps">
            <li class="done">Draft</li>
            <li>Confirmed</li>
            <li>Processing PO issued <div class="dp-hint">0 of <span class="sLines2"></span> lines planned</div></li>
            <li>Received from mill</li>
            <li>Packed &amp; dispatched</li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
  $(function () {
    const YD = 0.9144;
    const seed = @json($lines);
    const fmt = (n, d = 2) => Number(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    let n = 0;

    function addLine(r = ['', '', '', 0], price = 3.75) {
      n++;
      $('#linesTable tbody').append(`
        <tr>
          <td class="text-center text-muted idx"></td>
          <td><input class="form-control font-monospace" value="${r[0]}"></td>
          <td><input class="form-control" value="${r[1]}"></td>
          <td><input class="form-control" value="${r[2]}"></td>
          <td><input type="number" class="form-control text-end qty" value="${r[3]}" step="any"></td>
          <td class="text-end text-muted meters"></td>
          <td><input type="number" class="form-control text-end price" value="${price}" step="any"></td>
          <td><input type="number" class="form-control text-end disc" value="0" step="any"></td>
          <td class="text-end amount"></td>
          <td class="text-center"><a href="#" class="text-danger del"><i class="fa fa-times"></i></a></td>
        </tr>`);
    }
    seed.forEach(r => addLine(r));

    function recalc() {
      const unit = $('#orderUnit').val();
      let tq = 0, tm = 0, ta = 0, lines = 0;
      $('#linesTable tbody tr').each(function (i) {
        const q = parseFloat($(this).find('.qty').val()) || 0;
        const p = parseFloat($(this).find('.price').val()) || 0;
        const d = parseFloat($(this).find('.disc').val()) || 0;
        const m = unit === 'yd' ? q * YD : q;
        const a = q * p * (1 - d / 100);
        $(this).find('.idx').text(i + 1);
        $(this).find('.meters').text(fmt(m, 1));
        $(this).find('.amount').text(fmt(a));
        tq += q; tm += m; ta += a; if (q > 0) lines++;
      });
      const cur = $('#currency').val(), fx = parseFloat($('#fx').val()) || 0;
      $('.u').text(unit); $('#tQty').text(fmt(tq, 0)); $('#tM').text(fmt(tm, 1)); $('#tAmt').text(cur + ' ' + fmt(ta));
      $('#lineCount').text(lines + ' lines'); $('#sLines, .sLines2').text(lines);
      $('#sQty').text(fmt(tq, 0) + ' ' + unit); $('#sM').text(fmt(tm, 1) + ' m');
      $('#sAmt').text(cur + ' ' + fmt(ta)); $('#sPkr').text('Rs ' + fmt(cur === 'PKR' ? ta : ta * fx, 0));
    }

    function exFactory() {
      const a = new Date($('#arrival').val()), t = parseInt($('#transit').val()) || 0;
      if (isNaN(a)) return;
      a.setDate(a.getDate() - t);
      const iso = a.toISOString().slice(0, 10);
      $('#exFactory').val(iso);
      $('#sExf').text(a.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }));
    }

    $('#addLine').on('click', () => { addLine(); recalc(); });
    $(document).on('click', '.del', function (e) { e.preventDefault(); $(this).closest('tr').remove(); recalc(); });
    $(document).on('input change', '#linesTable input, #orderUnit, #currency, #fx', recalc);
    $('#arrival, #transit').on('input change', exFactory);
    $('input[name=shipto]').on('change', () => $('#shipToFields').toggle($('#st2').is(':checked')));
    recalc(); exFactory();
  });
</script>
@endsection
