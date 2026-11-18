@extends('layouts.app')
@section('title', 'New ' . \App\Models\ProductionOrder::TYPES[$type])
@section('content')
@php
  $isCut = $type === 'cutting';
  $putUpData = $putUps->map(function ($p) {
      $a = $p->getAttribute('attributes');
      $mpu = (float) (is_array($a) ? ($a['meters_per_unit'] ?? 0) : 0);
      // Cutting works in yards: yards per piece of the put-up
      return ['id' => $p->id, 'name' => $p->name, 'mpu' => $mpu, 'ypu' => round($mpu / \App\Models\Issuance::METERS_PER_YARD, 4)];
  })->values();
@endphp
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('production.store', $type) }}" id="prForm">
  @csrf
  <section class="card">
    <header class="card-header"><h2 class="card-title">{{ $isCut ? 'Cutting — fabric to cut pieces' : 'Packing — cut pieces to packed SKU' }}</h2></header>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-3"><label>Warehouse</label>
          <select name="location_id" id="loc" class="form-control" required>
            <option value="">Select…</option>
            @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('location_id') == $w->id)>{{ $w->name }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label>Date</label><input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-4"><label>Customer Order <small class="text-muted">(optional)</small></label>
          <select name="job_id" id="job" class="form-control">
            <option value="">— stock / no order —</option>
            @foreach($jobs as $j)<option value="{{ $j->id }}" @selected(old('job_id') == $j->id)>{{ $j->job_no }} — {{ $j->customer->name ?? '' }}</option>@endforeach
          </select></div>
        @if($isCut)
        <div class="col-md-3"><label>&nbsp;</label>
          <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="pack_now" value="1" id="packNow" @checked(old('pack_now'))>
          <label class="form-check-label" for="packNow">Pack straight away (uses the packaging list)</label></div></div>
        @endif
      </div>

      <div class="table-responsive">
      <table class="table table-bordered table-sm" id="lines">
        <thead class="table-light"><tr>
          <th>{{ $isCut ? 'Fabric' : 'Cut pieces' }}</th><th>Lot #</th><th>Grade</th><th class="text-end">{{ $isCut ? 'Issued for cutting (yd)' : 'In stock (pcs)' }}</th>
          @if($isCut)<th style="width:190px">Put-up</th>@endif
          <th style="width:110px">Pieces</th>
          @if($isCut)<th style="width:110px">Leftover yd</th><th style="width:110px">Rejected yd</th><th class="text-end">Fabric used (yd)</th>@else<th class="text-end">Yards</th><th>Packaging used</th>@endif
          <th style="width:200px">Customer order line</th><th></th>
        </tr></thead>
        <tbody><tr><td colspan="11" class="text-muted">Choose a warehouse.</td></tr></tbody>
      </table>
      </div>
      @if($isCut)<div class="small text-muted mb-3">Only fabric issued for cutting (Issuance → Processed Fabric for Cutting) can be cut. All quantities are in yards; stock is kept in meters (1 yd = 0.9144 m).</div>@endif
      <div class="mb-3"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}"></div>
    </div>
    <footer class="card-footer text-end">
      <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Cancel</a>
      <button class="btn btn-primary" onclick="return confirm('Post this {{ strtolower(\App\Models\ProductionOrder::TYPES[$type]) }}? Stock will move.')">Post</button>
    </footer>
  </section>
  </form>
</div></div>

<script>
(function () {
  const isCut = @json($isCut), putUps = @json($putUpData);
  const stockUrl = @json(route('production.stock', $type)), jobUrl = @json(url('production/job-items'));
  let stock = [], jobItems = [], n = 0;
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const num = v => parseFloat(v) || 0, r3 = v => Math.round(v * 1000) / 1000;

  function jobOptions(row) {
    const list = jobItems.filter(j => !j.pattern_code || !row.pattern || j.pattern_code.toLowerCase() === String(row.pattern).toLowerCase())
      .filter(j => isCut || !j.put_up_product_id || j.put_up_product_id == row.put_up_product_id);
    return '<option value="">—</option>' + list.map(j => `<option value="${j.id}" data-putup="${j.put_up_product_id ?? ''}">${esc(j.label)}${j.pieces ? ` (${j.packed}/${j.pieces} pcs packed)` : ''}</option>`).join('');
  }

  function rowHtml(row) {
    const i = n++;
    const hidden = `<input type="hidden" name="lines[${i}][product_id]" value="${row.product_id}"><input type="hidden" name="lines[${i}][lot_no]" value="${esc(row.lot_no)}"><input type="hidden" name="lines[${i}][grade]" value="${esc(row.grade)}">`;
    const qc = row.qc_pending ? '<div class="small text-danger">QC open — cannot cut</div>' : '';
    const dis = row.qc_pending ? 'disabled' : '';
    const stockCell = isCut ? `${(+row.qty_yd).toFixed(2)}<div class="small text-muted">${(+row.qty).toFixed(2)} m</div>` : row.qty;
    let cells = `<td>${esc(row.product)}${hidden}${qc}</td><td>${esc(row.lot_no ?? '—')}</td><td>${esc(row.grade ?? '—')}</td><td class="text-end">${stockCell}</td>`;
    if (isCut) cells += `<td><select name="lines[${i}][put_up_product_id]" class="form-control form-control-sm pu" ${dis}><option value="">—</option>${putUps.map(p => `<option value="${p.id}" data-ypu="${p.ypu}">${esc(p.name)}</option>`).join('')}</select></td>`;
    cells += `<td><input type="number" step="1" min="0" name="lines[${i}][pieces]" class="form-control form-control-sm pcs" ${dis}></td>`;
    if (isCut) cells += `<td><input type="number" step="any" min="0" name="lines[${i}][leftover_yd]" class="form-control form-control-sm lo" ${dis}></td>
                         <td><input type="number" step="any" min="0" name="lines[${i}][rejected_yd]" class="form-control form-control-sm rj" ${dis}></td>
                         <td class="text-end used"></td>`;
    else cells += `<td class="text-end yds"></td><td class="small used">${row.bom && row.bom.length ? '' : '<span class="text-warning">No packaging list for ' + esc(row.put_up) + '</span>'}</td>`;
    cells += `<td><select name="lines[${i}][job_item_id]" class="form-control form-control-sm ji" ${dis}>${jobOptions(row)}</select></td>
              <td>${isCut && !row.qc_pending ? '<button type="button" class="btn btn-sm btn-outline-secondary split" title="Cut another put-up from this lot">+</button>' : ''}</td>`;
    const tr = document.createElement('tr');
    tr.innerHTML = cells; tr.row = row;
    return tr;
  }

  function refresh(tr) {
    const row = tr.row, pcs = num(tr.querySelector('.pcs')?.value);
    if (isCut) {
      // yards: pieces × yards per piece + leftover + rejected; all rows of the same lot share its issued yards
      const used = r3(pcs * num(tr.querySelector('.pu').selectedOptions[0]?.dataset.ypu) + num(tr.querySelector('.lo').value) + num(tr.querySelector('.rj').value));
      let total = 0;
      document.querySelectorAll('#lines tbody tr').forEach(o => { if (o.row && o.row === row) {
        const y = num(o.querySelector('.pu')?.selectedOptions[0]?.dataset.ypu); total += num(o.querySelector('.pcs').value) * y + num(o.querySelector('.lo').value) + num(o.querySelector('.rj').value); } });
      const cell = tr.querySelector('.used');
      cell.innerHTML = used ? `${used.toFixed(2)}<div class="small text-muted">${(used * 0.9144).toFixed(2)} m</div>` : '';
      cell.className = 'text-end used ' + (r3(total) > num(row.qty_yd) + 0.001 ? 'text-danger fw-bold' : '');
      cell.title = r3(total) > num(row.qty_yd) + 0.001 ? `More than the ${row.qty_yd} yd issued for cutting` : '';
    } else {
      const y = num(row.ypu);
      tr.querySelector('.yds').textContent = pcs && y ? r3(pcs * y).toFixed(2) + ' yd' : '';
      if (row.bom && row.bom.length) tr.querySelector('.used').innerHTML = row.bom.map(b => `${esc(b.name)}: ${r3(pcs * b.qty_per_piece)}`).join('<br>');
    }
  }

  function render() {
    const body = document.querySelector('#lines tbody');
    body.innerHTML = '';
    if (!stock.length) { body.innerHTML = `<tr><td colspan="11" class="text-muted">${isCut ? 'No fabric issued for cutting here — issue it first from Issuance → Processed Fabric for Cutting.' : 'No cut pieces waiting to be packed here.'}</td></tr>`; return; }
    stock.forEach(r => body.appendChild(rowHtml(r)));
  }

  function load() {
    const loc = document.getElementById('loc').value;
    if (!loc) return;
    fetch(stockUrl + '?location_id=' + loc, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => { stock = j; render(); });
  }
  function loadJob() {
    const job = document.getElementById('job').value;
    const done = () => document.querySelectorAll('#lines tbody tr').forEach(tr => { const s = tr.querySelector('.ji'); if (s) s.innerHTML = jobOptions(tr.row); });
    if (!job) { jobItems = []; done(); return; }
    fetch(jobUrl + '/' + job, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => { jobItems = j; done(); });
  }

  const body = document.querySelector('#lines tbody');
  body.addEventListener('input', e => { const tr = e.target.closest('tr'); if (tr?.row) refresh(tr); });
  body.addEventListener('change', e => {
    const tr = e.target.closest('tr'); if (!tr?.row) return;
    // Picking a customer order line sets its put-up
    if (isCut && e.target.classList.contains('ji')) { const pu = e.target.selectedOptions[0]?.dataset.putup; if (pu) tr.querySelector('.pu').value = pu; }
    refresh(tr);
  });
  body.addEventListener('click', e => {
    if (!e.target.classList.contains('split')) return;
    const tr = e.target.closest('tr'), copy = rowHtml(tr.row);
    tr.after(copy); const s = copy.querySelector('.ji'); if (s) s.innerHTML = jobOptions(tr.row);
  });
  document.getElementById('loc').addEventListener('change', load);
  document.getElementById('job').addEventListener('change', loadJob);
  if (document.getElementById('loc').value) load();
  if (document.getElementById('job').value) loadJob();
})();
</script>
@endsection
