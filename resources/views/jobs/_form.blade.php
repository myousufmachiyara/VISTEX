{{--
  Customer Order (Job) form, shared by create and edit.
  $job is null on create. Lines come from old() after a validation error, else from the job.
--}}
@php
  $j = $job ?? null;
  $v = fn($field, $default = null) => old($field, $j?->{$field} ?? $default);
  $seed = old('items') ?? ($j ? $j->items->map(fn($i) => [
      'collection' => $i->collection, 'pattern_code' => $i->pattern_code, 'design' => $i->design,
      'color_scheme' => $i->color_scheme, 'product_id' => $i->product_id, 'quantity' => (float) $i->quantity,
      'unit_price' => (float) $i->unit_price, 'discount_pct' => (float) $i->discount_pct, 'tax_id' => $i->tax_id,
  ])->values()->all() : []);
  $packs = $packagings->map(fn($p) => ['id' => $p->id, 'label' => $p->name, 'mpp' => (float) ($p->attributes['meters_per_unit'] ?? 0)])->values();
  $termType = $v('payment_term_type', 'cash');
@endphp
<style>
  .co-sec { border:1px solid #e6e8ec; border-radius:8px; margin-bottom:16px; background:#fff; }
  .co-sec > .co-head { display:flex; justify-content:space-between; align-items:center; padding:9px 14px; border-bottom:1px solid #eef0f3; background:#fafbfc; border-radius:8px 8px 0 0; }
  .co-sec > .co-head h6 { margin:0; font-weight:600; font-size:.95rem; color:#2b3a4a; }
  .co-sec #termsSection { margin-top:0 !important; }
  .co-sec #termsSection > hr { display:none; }
  .co-strip { display:flex; flex-wrap:wrap; gap:8px; margin:6px 0 12px; }
  .co-stat { flex:1 1 120px; background:#f6f8f4; border:1px solid #e3e8dd; border-radius:6px; padding:6px 10px; }
  .co-stat span { display:block; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; }
  .co-stat strong { font-size:14px; color:#2b3a4a; }
  .co-sec > .co-head small { color:#8a94a3; }
  .co-sec > .co-body { padding:12px 14px 2px; }
  .co-num { display:inline-block; width:21px; height:21px; line-height:21px; text-align:center; border-radius:50%; background:#6b8e4e; color:#fff; font-size:11px; margin-right:7px; }
  #linesTable th { white-space:nowrap; font-size:12px; vertical-align:middle; }
  #linesTable td { vertical-align:middle; padding:4px; }
  #linesTable .form-control { height:32px; padding:3px 7px; font-size:13px; }
</style>

<div class="row">
  <div class="col-12">
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if($j && $j->status === 'Rejected')
      <div class="alert alert-warning"><strong>Rejected:</strong> {{ $j->rejection_reason }} — saving resubmits it for approval.</div>
    @endif

    {{-- 1. Customer --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">1</span>Customer</h6><small>Who placed the order</small></div>
      <div class="co-body"><div class="row">
        <div class="col-md-4 mb-3">
          <label>Customer <span class="text-danger">*</span></label>
          <select name="customer_id" id="customer_select" class="form-control select2-js" required>
            <option value="">Select Customer</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" data-currency="{{ $c->currency }}" @selected($v('customer_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label>Customer PO # <span class="text-danger">*</span></label>
          <input type="text" name="customer_po_number" class="form-control" maxlength="100" required value="{{ $v('customer_po_number') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label>Order Reference</label>
          <input type="text" name="customer_reference" class="form-control" maxlength="150" placeholder="e.g. White Owl WB 0826" value="{{ $v('customer_reference') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label>Buyer / Contact</label>
          <input type="text" name="buyer_name" class="form-control" maxlength="255" value="{{ $v('buyer_name') }}">
        </div>
      </div></div>
    </div>

    {{-- 2. Dates & commercial --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">2</span>Dates &amp; Commercial Terms</h6></div>
      <div class="co-body"><div class="row">
        <div class="col-md-3 mb-3">
          <label>Order Date <span class="text-danger">*</span></label>
          <input type="date" name="order_date" class="form-control" required value="{{ old('order_date', $j?->order_date?->format('Y-m-d') ?? date('Y-m-d')) }}">
        </div>
        <div class="col-md-3 mb-3">
          <label>Delivery Date</label>
          <input type="date" name="expected_date" class="form-control" value="{{ old('expected_date', $j?->expected_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3 mb-3">
          <label>Dispatch tolerance % <small class="text-muted">(over-shipment allowed)</small></label>
          <input type="number" name="dispatch_tolerance_pct" class="form-control" step="0.01" min="0" max="50" value="{{ $v('dispatch_tolerance_pct', 0) }}">
        </div>
        <div class="col-md-3 mb-3">
          <label>Currency</label>
          <select name="currency" id="currency" class="form-control">
            @foreach(\App\Models\Job::CURRENCIES as $cur)<option @selected($v('currency', 'PKR') === $cur)>{{ $cur }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3 mb-3" id="fxBox">
          <label>Exchange Rate <small class="text-muted">(PKR per <span class="cur-label">USD</span>)</small></label>
          <input type="number" name="exchange_rate" id="fx" class="form-control" step="0.0001" min="0.0001" value="{{ $v('exchange_rate', 1) }}">
        </div>
        <div class="col-md-3 mb-3">
          <label>Payment Term <span class="text-danger">*</span></label>
          <select name="payment_term_type" id="payment_term_type" class="form-control" required>
            @foreach(['cash' => 'Cash', 'credit' => 'Credit', 'pdc' => 'PDC', 'other' => 'Other'] as $k => $label)
              <option value="{{ $k }}" @selected($termType === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 mb-3" id="paymentDaysField">
          <label>Days</label>
          <input type="number" name="payment_term_days" class="form-control" min="1" value="{{ $v('payment_term_days', 30) }}">
        </div>
        <div class="col-md-6 mb-3" id="paymentNoteField">
          <label>Payment Note</label>
          <input type="text" name="payment_term_note" class="form-control" maxlength="255" value="{{ $v('payment_term_note') }}">
        </div>
      </div></div>
    </div>

    {{-- 3. Ship to --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">3</span>Shipping</h6><small>Consignee can differ from the customer</small></div>
      <div class="co-body"><div class="row">
        <div class="col-md-4 mb-3">
          <label>Consignee Name</label>
          <input type="text" name="consignee_name" class="form-control" maxlength="191" value="{{ $v('consignee_name') }}">
        </div>
        <div class="col-md-8 mb-3">
          <label>Shipping Address</label>
          <textarea name="shipping_address" class="form-control" rows="2" maxlength="1000">{{ $v('shipping_address') }}</textarea>
        </div>
      </div></div>
    </div>

    {{-- 4. Item details --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">4</span>Item Details</h6><small id="lineCount"></small></div>
      <div class="co-body">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn"><i class="fa fa-plus"></i> Add Line</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pasteModal"><i class="fa fa-paste"></i> Paste from Excel</button>
          <span class="ms-auto small text-muted align-self-center">Quantity in meters · Pcs = meters ÷ meters per piece of the packaging</span>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered mb-2" id="linesTable">
            <thead class="table-light">
              <tr>
                <th>#</th><th style="min-width:90px">Collection</th><th style="min-width:150px">Pattern #</th><th style="min-width:150px">Design</th>
                <th style="min-width:110px">Colour Scheme</th><th style="min-width:150px">Packaging</th>
                <th class="text-end" style="min-width:85px">Qty (m)</th><th class="text-end">Pcs</th>
                <th class="text-end" style="min-width:80px">Rate</th><th class="text-end" style="min-width:60px">Disc %</th>
                <th style="min-width:95px">Tax</th><th class="text-end" style="min-width:90px">Amount</th><th></th>
              </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
            <tfoot class="fw-bold">
              <tr><td colspan="6" class="text-end">Total</td><td class="text-end" id="tQty">0</td><td class="text-end" id="tPcs"></td><td colspan="3"></td><td class="text-end" id="tAmt">0.00</td><td></td></tr>
            </tfoot>
          </table>
        </div>
        <div class="co-strip">
          <div class="co-stat"><span>Lines</span><strong id="sLines">0</strong></div>
          <div class="co-stat"><span>Total Qty</span><strong id="sQty">0 m</strong></div>
          <div class="co-stat"><span>Gross</span><strong id="sGross">0.00</strong></div>
          <div class="co-stat"><span>Discount</span><strong id="sDisc">0.00</strong></div>
          <div class="co-stat"><span>Tax</span><strong id="sTax">0.00</strong></div>
          <div class="co-stat"><span>Order Total</span><strong id="sTotal">0.00</strong></div>
          <div class="co-stat" id="sPkrRow"><span>In PKR</span><strong id="sPkr">0</strong></div>
        </div>
      </div>
    </div>

    {{-- 5. Terms --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">5</span>Terms &amp; Conditions</h6><small>Master terms marked "Customer Orders"</small></div>
      <div class="co-body pb-2">
        @include('purchase_orders._terms', ['terms' => $terms, 'selectedIds' => old('term_ids', $selectedIds), 'orphans' => $orphans, 'fixedType' => 'customer_order'])
      </div>
    </div>

    {{-- 6. Attachments & remarks --}}
    <div class="co-sec">
      <div class="co-head"><h6><span class="co-num">6</span>Attachments &amp; Remarks</h6></div>
      <div class="co-body"><div class="row">
        <div class="col-md-6 mb-3">
          <label>Customer PO (PDF / scan)</label>
          <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,image/*,.xlsx,.xls,.doc,.docx">
          @foreach($j?->attachments ?? [] as $file)
            <div class="small mt-1"><i class="fa fa-paperclip"></i> <a href="{{ \App\Support\Media::url($file) }}" target="_blank">{{ basename($file) }}</a></div>
          @endforeach
        </div>
        <div class="col-md-6 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ $v('remarks') }}</textarea></div>
      </div></div>
    </div>
  </div>

</div>

{{-- Paste from Excel --}}
<div class="modal fade" id="pasteModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Paste lines from Excel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted mb-2">Copy rows from Excel with these columns, in this order (header row is skipped automatically):<br>
      <strong>Collection · Pattern # · Design · Colour Scheme · Qty (m) · Rate · Disc %</strong> — the last two are optional.</p>
    <textarea id="pasteBox" class="form-control font-monospace" rows="10" placeholder="WB 0826&#9;WB-W100-029-862&#9;Packed Butterfly Wings&#9;Multi&#9;82.3&#9;3.75"></textarea>
    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="pasteReplace" checked><label class="form-check-label" for="pasteReplace">Replace empty lines already in the grid</label></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="pasteApply">Add Lines</button></div>
</div></div></div>

<script>
$(function () {
  const packs = @json($packs);
  const taxes = @json($taxes->map(fn($t) => ['id' => $t->id, 'label' => $t->name, 'rate' => (float) $t->rate])->values());
  const seed  = @json(array_values($seed));
  const fmt = (n, d = 2) => Number(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  const esc = s => $('<div>').text(s ?? '').html();
  let idx = 0;

  $('.select2-js').select2({ width: '100%' });

  function packOptions(sel) {
    return '<option value="">—</option>' + packs.map(p =>
      `<option value="${p.id}" data-mpp="${p.mpp}" ${String(sel) === String(p.id) ? 'selected' : ''}>${esc(p.label)}</option>`).join('');
  }
  function taxOptions(sel) {
    return '<option value="">No Tax</option>' + taxes.map(t =>
      `<option value="${t.id}" data-rate="${t.rate}" ${String(sel) === String(t.id) ? 'selected' : ''}>${esc(t.label)}</option>`).join('');
  }

  function addRow(r = {}) {
    const i = idx++;
    const prevCollection = $('#itemsBody tr:last .f-collection').val() || '';
    $('#itemsBody').append(`
      <tr class="item-row">
        <td class="text-muted text-center row-no"></td>
        <td><input name="items[${i}][collection]" class="form-control f-collection" maxlength="60" value="${esc(r.collection ?? prevCollection)}"></td>
        <td><input name="items[${i}][pattern_code]" class="form-control font-monospace f-pattern" maxlength="120" value="${esc(r.pattern_code)}"></td>
        <td><input name="items[${i}][design]" class="form-control" maxlength="191" value="${esc(r.design)}"></td>
        <td><input name="items[${i}][color_scheme]" class="form-control" maxlength="191" value="${esc(r.color_scheme)}"></td>
        <td><select name="items[${i}][product_id]" class="form-control pack-select">${packOptions(r.product_id)}</select></td>
        <td><input type="number" name="items[${i}][quantity]" class="form-control text-end qty" step="any" min="0" value="${r.quantity ?? 0}"></td>
        <td class="text-end text-muted pcs"></td>
        <td><input type="number" name="items[${i}][unit_price]" class="form-control text-end price" step="any" min="0" value="${r.unit_price ?? 0}"></td>
        <td><input type="number" name="items[${i}][discount_pct]" class="form-control text-end disc" step="any" min="0" max="100" value="${r.discount_pct ?? 0}"></td>
        <td><select name="items[${i}][tax_id]" class="form-control tax">${taxOptions(r.tax_id)}</select></td>
        <td class="text-end amount">0.00</td>
        <td class="text-center"><a href="#" class="text-danger remove-row" title="Remove line"><i class="fa fa-times"></i></a></td>
      </tr>`);
  }

  function isBlank(tr) {
    const t = $(tr);
    return !t.find('.f-pattern').val() && !(parseFloat(t.find('.qty').val()) > 0) && !t.find('input[name$="[design]"]').val();
  }

  function recalc() {
    let lines = 0, qty = 0, pcs = 0, gross = 0, disc = 0, tax = 0, total = 0, pcsKnown = false;
    $('#itemsBody tr').each(function (n) {
      const t = $(this);
      const q = parseFloat(t.find('.qty').val()) || 0;
      const p = parseFloat(t.find('.price').val()) || 0;
      const d = Math.min(100, parseFloat(t.find('.disc').val()) || 0);
      const rate = parseFloat(t.find('.tax option:selected').data('rate')) || 0;
      const mpp = parseFloat(t.find('.pack-select option:selected').data('mpp')) || 0;
      const g = q * p, da = g * d / 100, ta = (g - da) * rate / 100, a = g - da + ta;
      const pc = mpp > 0 ? Math.floor(q / mpp + 1e-9) : null;

      t.find('.row-no').text(n + 1);
      t.find('.pcs').text(pc === null ? '' : fmt(pc, 0));
      t.find('.amount').text(fmt(a));
      if (q > 0) lines++;
      qty += q; gross += g; disc += da; tax += ta; total += a;
      if (pc !== null) { pcs += pc; pcsKnown = true; }
    });

    const cur = $('#currency').val(), fx = parseFloat($('#fx').val()) || 0;
    $('#lineCount').text(lines + (lines === 1 ? ' line' : ' lines'));
    $('#tQty').text(fmt(qty, 2)); $('#tPcs').text(pcsKnown ? fmt(pcs, 0) : ''); $('#tAmt').text(cur + ' ' + fmt(total));
    $('#sLines').text(lines); $('#sQty').text(fmt(qty, 2) + ' m');
    $('#sGross').text(cur + ' ' + fmt(gross)); $('#sDisc').text(cur + ' ' + fmt(disc)); $('#sTax').text(cur + ' ' + fmt(tax));
    $('#sTotal').text(cur + ' ' + fmt(total));
    $('#sPkrRow').toggle(cur !== 'PKR'); $('#sPkr').text('Rs ' + fmt(total * fx, 0));
  }

  function toggleCurrency() {
    const cur = $('#currency').val();
    $('#fxBox').toggle(cur !== 'PKR'); $('.cur-label').text(cur);
    if (cur === 'PKR') $('#fx').val(1);
    recalc();
  }

  function togglePayment() {
    const v = $('#payment_term_type').val();
    $('#paymentDaysField').toggle(v === 'credit' || v === 'pdc').find('input').prop('disabled', !(v === 'credit' || v === 'pdc'));
    $('#paymentNoteField').toggle(v === 'other').find('input').prop('disabled', v !== 'other');
  }

  // Customer's saved rate for a packaging SKU (only fills an empty rate)
  $(document).on('change', '.pack-select', function () {
    const t = $(this).closest('tr'), customer = $('#customer_select').val(), product = $(this).val();
    recalc();
    if (!customer || !product || parseFloat(t.find('.price').val()) > 0) return;
    fetch(`{{ route('jobs.suggest_rate') }}?customer_id=${customer}&product_id=${product}`)
      .then(r => r.ok ? r.json() : null).then(d => { if (d && d.rate !== null) { t.find('.price').val(d.rate); recalc(); } });
  });

  // Customer's default currency on a new order
  $('#customer_select').on('change', function () {
    const c = $(this).find('option:selected').data('currency');
    if (c && !@json((bool) $j) && $('#currency option').filter((_, o) => o.value === c).length) { $('#currency').val(c); toggleCurrency(); }
  });

  $('#addRowBtn').on('click', () => { addRow(); recalc(); $('#itemsBody tr:last .f-pattern').focus(); });
  $(document).on('click', '.remove-row', function (e) {
    e.preventDefault();
    if ($('#itemsBody tr').length > 1) $(this).closest('tr').remove(); else $(this).closest('tr').find('input').val('').filter('.qty,.price,.disc').val(0);
    recalc();
  });
  $(document).on('input change', '#itemsBody input, #itemsBody select, #fx', recalc);
  $('#currency').on('change', toggleCurrency);
  $('#payment_term_type').on('change', togglePayment);

  // Paste from Excel: Collection, Pattern #, Design, Colour, Qty, Rate, Disc %
  $('#pasteApply').on('click', function () {
    const num = s => parseFloat(String(s ?? '').replace(/[^0-9.\-]/g, '')) || 0;
    const rows = $('#pasteBox').val().split(/\r?\n/).map(l => l.split('\t')).filter(c => c.join('').trim() !== '');
    const data = rows.filter(c => num(c[4]) > 0 || /\d/.test(c[1] || ''));
    if (!data.length) return alert('Nothing to add — check the column order.');
    if ($('#pasteReplace').is(':checked')) $('#itemsBody tr').filter((_, tr) => isBlank(tr)).remove();
    data.forEach(c => addRow({
      collection: (c[0] || '').trim(), pattern_code: (c[1] || '').trim(), design: (c[2] || '').trim(), color_scheme: (c[3] || '').trim(),
      quantity: num(c[4]), unit_price: num(c[5]), discount_pct: num(c[6]),
    }));
    if (!$('#itemsBody tr').length) addRow();
    recalc();
    $('#pasteBox').val(''); bootstrap.Modal.getOrCreateInstance(document.getElementById('pasteModal')).hide();
  });

  (seed.length ? seed : [{}]).forEach(r => addRow(r));
  toggleCurrency(); togglePayment(); recalc();
});
</script>
