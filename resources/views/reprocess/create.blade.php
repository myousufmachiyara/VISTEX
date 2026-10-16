@extends('layouts.app')
@section('title', 'Send for Reprocess')
@section('content')
@php
  $millOptions = $mills->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'vendor_id' => $m->vendor_id, 'vendor' => $m->vendor->name ?? ''])->values();
@endphp
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('reprocess.store') }}" id="rpForm">
  @csrf
  <section class="card">
    <header class="card-header"><h2 class="card-title">Send Finished Fabric for Reprocess</h2></header>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label>From Warehouse</label>
          <select name="from_location_id" id="fromLoc" class="form-control" required>
            <option value="">Select…</option>
            @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('from_location_id', $preset['location_id'] ?? null) == $w->id)>{{ $w->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label>Lot #</label>
          <select name="lot_no" id="lotNo" class="form-control" required><option value="">Select a warehouse first</option></select>
          <div class="small text-muted">The fabric keeps this lot # through reprocessing.</div>
        </div>
        <div class="col-md-3">
          <label>Mill</label>
          <select name="mill_location_id" id="millLoc" class="form-control" required>
            <option value="">Select…</option>
            @foreach($millOptions as $m)<option value="{{ $m['id'] }}" data-vendor="{{ $m['vendor_id'] }}" @selected(old('mill_location_id') == $m['id'])>{{ $m['vendor'] }} — {{ $m['name'] }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label>Charges</label>
          <div class="form-check mt-1">
            <input type="hidden" name="is_foc" value="0">
            <input class="form-check-input" type="checkbox" name="is_foc" value="1" id="isFoc" @checked(old('is_foc', '1') == '1')>
            <label class="form-check-label" for="isFoc">Free of charge (mill's fault — no PO needed)</label>
          </div>
          <div class="small text-muted" id="focHint"></div>
        </div>
      </div>

      <div class="table-responsive mt-3">
      <table class="table table-bordered table-sm" id="lineTable">
        <thead><tr><th>Item</th><th>Take from</th><th class="text-end">Available (m)</th><th style="width:150px">Send (m)</th><th class="rate-col" style="width:150px">Rate / m</th></tr></thead>
        <tbody><tr><td colspan="5" class="text-muted">Choose a warehouse and lot.</td></tr></tbody>
      </table>
      </div>

      <div class="row g-3">
        <div class="col-md-2"><label>Date</label><input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-2"><label>Expected back</label><input type="date" name="expected_date" class="form-control" value="{{ old('expected_date') }}"></div>
        <div class="col-md-4"><label>Reason / defects</label><input name="reason" class="form-control" value="{{ old('reason') }}" placeholder="e.g. shade variation, re-dye to standard"></div>
        <div class="col-md-4"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}"></div>
      </div>
    </div>
    <footer class="card-footer text-end">
      <a href="{{ route('reprocess.index') }}" class="btn btn-outline-secondary">Cancel</a>
      <button class="btn btn-primary">Create Reprocess Order</button>
    </footer>
  </section>
  </form>
</div></div>

<script>
(function () {
  const presetLot = @json(old('lot_no', $preset['lot_no'] ?? ''));
  const oldLines = @json(old('lines', []));
  const oldRates = @json(old('rates', []));
  let data = { rows: [], lot_vendors: {} };
  const label = s => s === 'reprocess' ? 'Reprocess hold (from QC)' : 'Grade ' + s;
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function focUi() {
    const foc = document.getElementById('isFoc').checked;
    document.querySelectorAll('.rate-col').forEach(e => e.style.display = foc ? 'none' : '');
    document.getElementById('focHint').textContent = foc
      ? 'Approved at once; the gate pass goes to the warehouse in-charge.'
      : 'A reprocess PO at your rate goes for approval first.';
  }

  function renderLines() {
    const lot = document.getElementById('lotNo').value;
    const body = document.querySelector('#lineTable tbody');
    const rows = data.rows.filter(r => r.lot_no === lot);
    if (!rows.length) { body.innerHTML = '<tr><td colspan="5" class="text-muted">Nothing to send in this lot.</td></tr>'; return; }
    const seenRate = {};
    body.innerHTML = rows.map((r, i) => {
      const prev = oldLines.find(l => l.product_id == r.product_id && l.source === r.source);
      const def = prev ? prev.quantity : (r.source === 'reprocess' ? r.free : '');
      const rateCell = seenRate[r.product_id] ? '' :
        `<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="rates[${r.product_id}]" value="${esc(oldRates[r.product_id] ?? '')}">`;
      seenRate[r.product_id] = true;
      return `<tr>
        <td>${esc(r.product)}<input type="hidden" name="lines[${i}][product_id]" value="${r.product_id}"></td>
        <td>${label(r.source)}<input type="hidden" name="lines[${i}][source]" value="${r.source}"></td>
        <td class="text-end">${r.free}${r.free < r.qty ? ` <span class="small text-muted">(${r.qty} in stock)</span>` : ''}</td>
        <td><input type="number" step="any" min="0" max="${r.free}" class="form-control form-control-sm" name="lines[${i}][quantity]" value="${def}"></td>
        <td class="rate-col">${rateCell}</td></tr>`;
    }).join('');
    focUi();
    // Default mill: the one that processed the lot
    const vendor = data.lot_vendors[lot], mill = document.getElementById('millLoc');
    if (vendor && !mill.value) {
      const opt = [...mill.options].find(o => o.dataset.vendor == vendor);
      if (opt) mill.value = opt.value;
    }
  }

  function loadLots() {
    const loc = document.getElementById('fromLoc').value, lotSel = document.getElementById('lotNo');
    if (!loc) { lotSel.innerHTML = '<option value="">Select a warehouse first</option>'; return; }
    fetch(@json(route('reprocess.available')) + '?location_id=' + loc, { headers: { Accept: 'application/json' } })
      .then(r => r.json()).then(j => {
        data = j;
        const lots = [...new Set(j.rows.map(r => r.lot_no))];
        lotSel.innerHTML = lots.length
          ? '<option value="">Select…</option>' + lots.map(l => `<option ${l === presetLot ? 'selected' : ''}>${esc(l)}</option>`).join('')
          : '<option value="">No finished fabric here</option>';
        renderLines();
      });
  }

  document.getElementById('fromLoc').addEventListener('change', loadLots);
  document.getElementById('lotNo').addEventListener('change', renderLines);
  document.getElementById('isFoc').addEventListener('change', focUi);
  focUi();
  if (document.getElementById('fromLoc').value) loadLots();
})();
</script>
@endsection
