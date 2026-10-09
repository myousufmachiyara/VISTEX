@extends('layouts.app')
@section('title', 'Processing PO | Design Preview')
@section('content')
@php
  // Sample data from mill PO SLGP0001/26 (Gul Ahmed) against job order WB 0826.
  // [patterns, description, mill qty (m), customer qty (yd) or null, link: full|partial|none]
  $lines = [
    [['WB-T130-008-756-ROT'], 'Large Floral - Navy', 70, null, 'none'],
    [['WB-W100-029-862'], 'Packed Butterfly Wings - Multi', 100, 90, 'full'],
    [['WB-W105-022-032'], 'Packed Flowers - Multi', 80, 72, 'full'],
    [['WB-W105-051-158-ROT', 'WB-W175-022-745-ROT'], 'Packed Floral - Multi', 260, null, 'none'],
    [['WB-W110-015-519', 'WB-W170-004-775-ROT'], 'Packed Floral - Navy', 240, 108, 'partial'],
    [['WB-W110-027-167'], 'Floral Toss - White', 380, 360, 'full'],
    [['WB-W115-017-007'], 'Floral Journal - Cream', 80, 72, 'full'],
    [['WB-W115-029-958'], 'Large Floral - Light Gray', 290, 270, 'full'],
    [['WB-W130-005-003'], 'Butterfly Wishes - Cream', 100, 90, 'full'],
    [['WB-W130-005-403'], 'Butterfly Wishes - Indigo', 80, 72, 'full'],
    [['WB-W130-005-800'], 'Butterfly Wishes - Multi', 100, 90, 'full'],
    [['WB-W130-032-410'], 'Make a Wish - Purple', 80, 72, 'full'],
    [['WB-W130-032-940'], 'Make a Wish - Teal', 120, 108, 'full'],
    [['WB-W140-003-817'], 'Sampler - Multi', 80, 72, 'full'],
    [['WB-W150-026-168-ROT'], 'Large Lilacs - Cream', 140, null, 'none'],
    [['WB-W150-032-112-ROT'], 'Medallion - Cream', 140, null, 'none'],
    [['WB-W175-013-775'], 'Floral - Navy', 230, 216, 'full'],
  ];
  $notes = [
    'Fabric must be Sanforized.',
    'This is a SAMPLE PURCHASE ORDER. Quantities are for sample / development production only.',
    'Finished width must not fall below 108". Any width shortfall must be reported before bulk processing.',
    'Strike-offs and lab dips to be submitted and approved prior to bulk print.',
    'Delivery date of 18/08/2026 to be strictly adhered to.',
    'Shade continuity to be maintained head-to-head and selvedge-to-selvedge across the full lot.',
  ];
  $lab = [
    ['Strike-Off', 'Required before bulk'], ['Lab Dips', 'Required before bulk'],
    ['Shrinkage', 'Max 3% warp / 3% weft'], ['Colour Fastness (Wash)', 'Grade 4 minimum'],
    ['Colour Fastness (Rubbing)', 'Dry 4 / Wet 3-4'], ['Finish', 'Sanforized'],
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
        <h2 class="card-title">New Processing PO (Mill)</h2>
        <div class="d-flex align-items-center gap-2">
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-danger po-kind" data-kind="sample">Sample PO</button>
            <button type="button" class="btn btn-outline-success po-kind" data-kind="bulk">Bulk PO</button>
          </div>
          <span class="text-muted small" id="poNo">SLGP0001/26</span><span class="dp-chip draft">Draft</span>
        </div>
      </header>
      <div class="card-body">

        {{-- 1. Order --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">1</span>Order</h5><small>Mill, dates and the customer order(s) being processed</small></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-4 mb-3"><label>Processing Mill <span class="text-danger">*</span></label><select class="form-control"><option>Gul Ahmed Textile Mills Limited</option><option>Nishat Mills</option><option>Kohinoor Mills</option></select></div>
              <div class="col-md-4 mb-3"><label>PO Type</label><select class="form-control"><option>Outsource Processing</option><option>In-house Processing</option></select></div>
              <div class="col-md-4 mb-3"><label>Program</label><input class="form-control" value="100% Cotton"></div>
              <div class="col-md-3 mb-3"><label>PO Date <span class="text-danger">*</span></label><input type="date" class="form-control" value="2026-07-29"></div>
              <div class="col-md-3 mb-3"><label>Delivery Date <span class="text-danger">*</span></label><input type="date" class="form-control" value="2026-08-18"><span class="dp-hint">Job order ex-factory: 30 Aug 2026</span></div>
              <div class="col-md-6 mb-3">
                <label>Customer Order(s) <span class="text-danger">*</span></label>
                <select class="form-control dp-select2" multiple>
                  <option selected>JO-0001/26 — Lumin Fabrics — White Owl WB 0826 (13 lines)</option>
                  <option>JO-0002/26 — Island Batik — IB 0926 (8 lines)</option>
                </select>
                <span class="dp-hint">One mill PO can cover several confirmed job orders</span>
              </div>
            </div>
          </div>
        </div>

        {{-- 2. Fabric Details --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">2</span>Fabric Details</h5><small>Pre-filled from the job order — editable</small></div>
          <div class="dp-body">
            <div class="row">
              <div class="col-md-5 mb-3"><label>Greige Fabric (Fabric Quality) <span class="text-danger">*</span></label><select class="form-control"><option>20x20/60x60 Combed 100% CTN</option><option>40x40/133x72 Cotton</option></select></div>
              <div class="col-md-2 mb-3"><label>Greige Width</label><input class="form-control dp-auto" value='120"' readonly></div>
              <div class="col-md-3 mb-3"><label>Process <span class="text-danger">*</span></label><select class="form-control"><option>Digital Print</option><option>Rotary Print</option><option>Reactive Dyed</option><option>Pigment Print</option><option>Bleach / PFP</option></select></div>
              <div class="col-md-2 mb-3"><label>Finished Width</label><input class="form-control" value='108"'></div>
              <div class="col-md-2 mb-3"><label>Finished GSM</label><input class="form-control" value="155"></div>
              <div class="col-md-3 mb-3"><label>Dyestuff</label><select class="form-control"><option>Reactive</option><option>Pigment</option><option>Disperse</option><option>Vat</option></select></div>
              <div class="col-md-3 mb-3"><label>Finish</label><select class="form-control"><option>Sanforized</option><option>Soft Finish</option><option>Peach</option></select></div>
            </div>
          </div>
        </div>

        {{-- 3. Line Items --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">3</span>Line Items</h5><small id="lineCount"></small></div>
          <div class="dp-body">
            <div class="dp-toolbar">
              <button type="button" class="btn btn-sm btn-primary" data-preview="Loads every open line of the selected job order(s) with the customer quantity."><i class="fa fa-download"></i> Load from Job Order</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="mergeBtn"><i class="fa fa-object-group"></i> Merge Selected</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="addRow"><i class="fa fa-plus"></i> Add Row</button>
              <span class="ms-auto d-flex align-items-center gap-2 small">
                Allowance <input type="number" class="form-control form-control-sm" id="allow" value="15" style="width:65px"> %
                <label class="mb-0 fw-normal"><input type="checkbox" id="round10" checked> round up to 10 m</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="applyAllow">Apply</button>
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered dp-table mb-2" id="procTable">
                <thead>
                  <tr>
                    <th width="2%"><input type="checkbox" id="chkAll"></th><th width="3%">#</th><th width="7%">Collection</th>
                    <th>Pattern # / Description</th>
                    <th width="8%" class="text-end" title="Quantity on the customer order">Ordered (yd)</th>
                    <th width="7%" class="text-end">= m</th><th width="6%" class="text-end">Allow.</th>
                    <th width="9%" class="text-end">Mill Qty (m)</th><th width="7%" class="text-end">Rate</th><th width="9%" class="text-end">Amount</th><th width="2%"></th>
                  </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                  <tr><td colspan="4" class="text-end">TOTAL</td><td class="text-end" id="tYd"></td><td class="text-end" id="tReq"></td><td></td><td class="text-end" id="tQty"></td><td></td><td class="text-end" id="tAmt"></td><td></td></tr>
                </tfoot>
              </table>
            </div>
            <div class="small text-muted mb-2">
              <span class="dp-pattern">WB-W100-029-862</span> on the job order ·
              <span class="dp-pattern" style="background:#f4f4f4;border-color:#ddd;color:#777">WB-T130-008-756-ROT</span> not on any selected job order ·
              Tick rows and <b>Merge Selected</b> to print several patterns on one line (as on SLGP0001 lines 4 and 5).
            </div>
            <p class="dp-feedback"><b>Client to confirm:</b> 4 lines on SLGP0001 (e.g. WB-T130-008-756-ROT, WB-W150-026-168-ROT) are not on Lumin P02835 — which order do they belong to, or are they made for stock? Is the extra over the ordered qty (≈ 10–20%) a fixed % per mill or decided per line?</p>
          </div>
        </div>

        {{-- 4. Greige Requirement --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">4</span>Greige Requirement</h5><small>What must be issued to the mill</small></div>
          <div class="dp-body">
            <div class="row align-items-end">
              <div class="col-md-3 mb-3"><label>Mill Qty (finished)</label><input class="form-control dp-auto text-end" id="gFin" readonly></div>
              <div class="col-md-2 mb-3"><label>Process Loss %</label><input type="number" class="form-control text-end" id="gLoss" value="6"></div>
              <div class="col-md-3 mb-3"><label>Greige to Issue (m)</label><input class="form-control dp-auto text-end fw-bold" id="gNeed" readonly></div>
              <div class="col-md-4 mb-3"><label>Issue From</label><select class="form-control" id="gLoc"><option data-stock="3150">Main Warehouse — 3,150 m in stock</option><option data-stock="1200">Second Warehouse — 1,200 m in stock</option></select></div>
            </div>
            <div class="progress mb-1" style="height:8px"><div class="progress-bar" id="gBar"></div></div>
            <div class="small mb-3" id="gMsg"></div>
            <span class="dp-hint">The greige itself is sent on the existing <b>Production → Issuance</b> screen ("Greige for Processing") once this PO is approved.</span>
          </div>
        </div>

        {{-- 5. Notes & Special Instructions --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">5</span>Notes &amp; Special Instructions</h5><small>Printed on the PO</small></div>
          <div class="dp-body">
            <table class="table table-sm table-borderless mb-2" id="notesTable"><tbody>
              @foreach($notes as $i => $note)
                <tr><td width="3%" class="text-muted pt-2 n-idx">{{ $i + 1 }}.</td><td><input class="form-control form-control-sm" value="{{ $note }}"></td><td width="3%"><a href="#" class="text-danger rm-row"><i class="fa fa-times"></i></a></td></tr>
              @endforeach
            </tbody></table>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addNote"><i class="fa fa-plus"></i> Add Note</button>
          </div>
        </div>

        {{-- 6. Lab Requirements --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">6</span>Lab Requirements</h5><small>Default set per mill — add / remove as needed</small></div>
          <div class="dp-body">
            <div class="row" id="labGrid">
              @foreach($lab as [$k, $v])
                <div class="col-md-6 mb-2 lab-item"><div class="input-group input-group-sm">
                  <input class="form-control fw-semibold" value="{{ $k }}" style="max-width:45%"><input class="form-control" value="{{ $v }}">
                  <button class="btn btn-outline-danger rm-lab" type="button"><i class="fa fa-times"></i></button>
                </div></div>
              @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addLab"><i class="fa fa-plus"></i> Add Requirement</button>
          </div>
        </div>

        {{-- 7. Terms --}}
        <div class="dp-section">
          <div class="dp-head"><h5><span class="dp-num">7</span>Terms &amp; Conditions</h5><small>From the Terms &amp; Conditions master</small></div>
          <div class="dp-body">
            <div class="row small">
              @foreach(['Payment 30 days after delivery against bill', 'Rejected fabric to be replaced at mill cost', 'Packing list must accompany each delivery', 'Mill to bear cost of re-processing for shade issues'] as $i => $t)
                <div class="col-md-6 mb-2"><label class="fw-normal"><input type="checkbox" {{ $i < 3 ? 'checked' : '' }}> {{ $t }}</label></div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      <footer class="card-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-preview="Back to the PO list.">Cancel</button>
        <div>
          <button type="button" class="btn btn-outline-secondary" data-preview="Opens the PO print (same layout as SLGP0001)."><i class="fa fa-print"></i> Print Preview</button>
          <button type="button" class="btn btn-outline-primary" data-preview="Saves as Draft — only you can see it.">Save as Draft</button>
          <button type="button" class="btn btn-success" data-preview="Sends to the Director for approval; their name prints under APPROVED BY.">Submit for Approval</button>
        </div>
      </footer>
    </section>
  </div>

  {{-- ═══════════════ SUMMARY ═══════════════ --}}
  <div class="col-lg-3">
    <div class="dp-summary">
      <section class="card mb-3">
        <header class="card-header"><h2 class="card-title" style="font-size:15px">PO Summary</h2></header>
        <div class="card-body">
          <div class="dp-kv"><span>Mill</span><strong>Gul Ahmed</strong></div>
          <div class="dp-kv"><span>Type</span><strong id="sKind"></strong></div>
          <div class="dp-kv"><span>Lines</span><strong id="sLines"></strong></div>
          <div class="dp-kv"><span>Customer ordered</span><strong id="sReq"></strong></div>
          <div class="dp-kv"><span>Mill Qty</span><strong id="sQty"></strong></div>
          <div class="dp-kv"><span>Extra over order</span><strong id="sExtra"></strong></div>
          <div class="dp-kv"><span>Amount</span><strong id="sAmt"></strong></div>
          <div class="dp-kv"><span>Greige to issue</span><strong id="sGreige"></strong></div>
        </div>
      </section>
      <section class="card">
        <header class="card-header"><h2 class="card-title" style="font-size:15px">Workflow</h2></header>
        <div class="card-body">
          <ul class="dp-steps">
            <li class="done">Draft</li>
            <li>Pending approval <div class="dp-hint">Director</div></li>
            <li>Approved</li>
            <li>Greige issued to mill</li>
            <li>Strike-off / lab dip approved</li>
            <li>Received (partial / full)</li>
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
    const fmt = (n, d = 0) => Number(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    $('.dp-select2').select2({ width: '100%' });

    const chip = (p, linked) => `<span class="dp-pattern"${linked ? '' : ' style="background:#f4f4f4;border-color:#ddd;color:#777"'}>${p}</span>`;

    function addRow(r = [[], '', 0, null, 'none'], rate = 320) {
      const linkedSet = r[4] === 'full' ? r[0] : (r[4] === 'partial' ? [r[0][0]] : []);
      const chips = r[0].map(p => chip(p, linkedSet.includes(p))).join('') || '<input class="form-control form-control-sm font-monospace" placeholder="Pattern #">';
      $('#procTable tbody').append(`
        <tr data-yd="${r[3] ?? ''}" data-link="${r[4]}">
          <td><input type="checkbox" class="sel"></td>
          <td class="text-center text-muted idx"></td>
          <td>WB 0826</td>
          <td><div class="patterns mb-1">${chips}</div><input class="form-control desc" value="${r[1]}" placeholder="Description"></td>
          <td class="text-end yd">${r[3] ? fmt(r[3]) : '<span class="text-muted">—</span>'}</td>
          <td class="text-end text-muted req"></td>
          <td class="text-end allow"></td>
          <td><input type="number" class="form-control text-end qty" value="${r[2]}" step="any"></td>
          <td><input type="number" class="form-control text-end rate" value="${rate}" step="any"></td>
          <td class="text-end amount"></td>
          <td class="text-center"><a href="#" class="text-danger del"><i class="fa fa-times"></i></a></td>
        </tr>`);
    }
    seed.forEach(r => addRow(r));

    function recalc() {
      let tyd = 0, treq = 0, tq = 0, ta = 0, qLinked = 0, n = 0;
      $('#procTable tbody tr').each(function (i) {
        const yd = parseFloat($(this).data('yd')) || 0;
        const req = yd * YD;
        const q = parseFloat($(this).find('.qty').val()) || 0;
        const a = q * (parseFloat($(this).find('.rate').val()) || 0);
        $(this).find('.idx').text(i + 1);
        $(this).find('.req').text(req ? fmt(req, 1) : '');
        const pct = req ? (q / req - 1) * 100 : null;
        $(this).find('.allow').html(pct === null ? '' : `<span class="${pct < 0 ? 'text-danger' : (pct > 25 ? 'text-warning' : 'text-success')}">${pct >= 0 ? '+' : ''}${fmt(pct)}%</span>`);
        $(this).find('.amount').text(fmt(a));
        tyd += yd; treq += req; tq += q; ta += a; n++;
        if (req) qLinked += q;
      });
      $('#tYd').text(fmt(tyd)); $('#tReq').text(fmt(treq, 1)); $('#tQty').text(fmt(tq)); $('#tAmt').text('Rs ' + fmt(ta));
      $('#lineCount').text(n + ' lines'); $('#sLines').text(n);
      $('#sReq').text(fmt(tyd) + ' yd (' + fmt(treq) + ' m)');
      $('#sQty').text(fmt(tq) + ' m'); $('#sAmt').text('Rs ' + fmt(ta));
      $('#sExtra').text(treq ? '+' + fmt((qLinked / treq - 1) * 100, 1) + '% on ordered lines' : '—');

      const loss = parseFloat($('#gLoss').val()) || 0, need = Math.ceil(tq * (1 + loss / 100));
      const stock = parseFloat($('#gLoc option:selected').data('stock')) || 0;
      $('#gFin').val(fmt(tq) + ' m'); $('#gNeed').val(fmt(need) + ' m'); $('#sGreige').text(fmt(need) + ' m');
      const pct = Math.min(100, need / stock * 100);
      $('#gBar').css('width', pct + '%').toggleClass('bg-danger', need > stock).toggleClass('bg-success', need <= stock);
      $('#gMsg').html(need > stock
        ? `<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Short by ${fmt(need - stock)} m at this location.</span>`
        : `<span class="text-success"><i class="fa fa-check"></i> Enough stock — ${fmt(stock - need)} m left after issue.</span>`);
    }

    $('#applyAllow').on('click', function () {
      const a = parseFloat($('#allow').val()) || 0, r10 = $('#round10').is(':checked');
      $('#procTable tbody tr[data-link=full]').each(function () {
        let q = (parseFloat($(this).data('yd')) || 0) * YD * (1 + a / 100);
        q = r10 ? Math.ceil(q / 10) * 10 : Math.round(q);
        $(this).find('.qty').val(q);
      });
      recalc(); dpToast('Mill qty recalculated for lines that are fully on the job order.');
    });

    $('#mergeBtn').on('click', function () {
      const rows = $('#procTable tbody tr').filter((_, tr) => $(tr).find('.sel').is(':checked'));
      if (rows.length < 2) return dpToast('Tick two or more rows to merge.');
      const first = rows.first();
      let qty = 0, yd = 0;
      rows.each(function () { qty += parseFloat($(this).find('.qty').val()) || 0; yd += parseFloat($(this).data('yd')) || 0; });
      rows.not(first).each(function () { first.find('.patterns').append($(this).find('.patterns').html()); }).remove();
      first.find('.qty').val(qty); first.data('yd', yd || '').attr('data-link', 'partial');
      first.find('.yd').html(yd ? fmt(yd) : '<span class="text-muted">—</span>'); first.find('.sel').prop('checked', false);
      recalc();
    });

    $('.po-kind').on('click', function () {
      const sample = $(this).data('kind') === 'sample';
      $('.po-kind[data-kind=sample]').toggleClass('btn-danger', sample).toggleClass('btn-outline-danger', !sample);
      $('.po-kind[data-kind=bulk]').toggleClass('btn-success', !sample).toggleClass('btn-outline-success', sample);
      $('#sKind').html(sample ? '<span class="dp-chip sample">Sample</span>' : '<span class="dp-chip bulk">Bulk</span>');
      $('#poNo').text(sample ? 'SLGP0001/26' : 'LGP0001/26');
    });
    $('#sKind').html('<span class="dp-chip sample">Sample</span>');

    $('#addRow').on('click', () => { addRow(); recalc(); });
    $('#chkAll').on('change', function () { $('#procTable .sel').prop('checked', this.checked); });
    $(document).on('click', '.del', function (e) { e.preventDefault(); $(this).closest('tr').remove(); recalc(); });
    $(document).on('input change', '#procTable input, #gLoss, #gLoc', recalc);

    function renumberNotes() { $('#notesTable .n-idx').each((i, td) => $(td).text((i + 1) + '.')); }
    $('#addNote').on('click', () => { $('#notesTable tbody').append('<tr><td width="3%" class="text-muted pt-2 n-idx"></td><td><input class="form-control form-control-sm"></td><td width="3%"><a href="#" class="text-danger rm-row"><i class="fa fa-times"></i></a></td></tr>'); renumberNotes(); });
    $(document).on('click', '.rm-row', function (e) { e.preventDefault(); $(this).closest('tr').remove(); renumberNotes(); });
    $('#addLab').on('click', () => $('#labGrid').append('<div class="col-md-6 mb-2 lab-item"><div class="input-group input-group-sm"><input class="form-control fw-semibold" placeholder="Requirement" style="max-width:45%"><input class="form-control" placeholder="Value"><button class="btn btn-outline-danger rm-lab" type="button"><i class="fa fa-times"></i></button></div></div>'));
    $(document).on('click', '.rm-lab', function () { $(this).closest('.lab-item').remove(); });

    recalc();
  });
</script>
@endsection
