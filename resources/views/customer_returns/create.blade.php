@extends('layouts.app')
@section('title', 'New Customer Return')
@section('content')
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('customer_returns.store') }}">
  @csrf
  <section class="card">
    <header class="card-header"><h2 class="card-title">Goods Returned by a Customer</h2></header>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-md-4"><label>Customer</label><select name="customer_id" id="cust" class="form-control" required><option value="">Select…</option>
          @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $preset) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label>Back into</label><select name="location_id" class="form-control" required>
          @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('location_id') == $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label>Date</label><input type="date" name="return_date" class="form-control" value="{{ old('return_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-3"><label>Reason</label><input name="reason" class="form-control" value="{{ old('reason') }}" placeholder="e.g. shade complaint, damaged in transit"></div>
      </div>
      <div class="table-responsive">
      <table class="table table-bordered table-sm" id="lines">
        <thead class="table-light"><tr><th>Delivery</th><th>Order</th><th>Item</th><th class="text-end">Delivered</th><th class="text-end">Invoiced</th><th class="text-end">Already back</th><th style="width:120px">Returning</th><th style="width:150px">Condition</th></tr></thead>
        <tbody><tr><td colspan="8" class="text-muted">Choose a customer.</td></tr></tbody>
      </table>
      </div>
      <div class="small text-muted">Stock goes back under the lots it left from, at the cost it left at. Fresh = can be sold again; Rejected = held as rejected stock, not sellable. Quantities that were already invoiced get a credit note at the invoice price.</div>
      <div class="mt-3"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}"></div>
    </div>
    <footer class="card-footer text-end"><a href="{{ route('customer_returns.index') }}" class="btn btn-outline-secondary">Cancel</a> <button class="btn btn-primary">Post Return</button></footer>
  </section>
  </form>
</div></div>
<script>
(function () {
  const url = @json(url('customer-returns/returnable'));
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const q = v => Math.round(v * 1000) / 1000;
  function load() {
    const c = document.getElementById('cust').value, body = document.querySelector('#lines tbody');
    if (!c) return;
    fetch(url + '/' + c, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(rows => {
      body.innerHTML = rows.length ? rows.map((r, i) => `<tr>
        <td class="small">${esc(r.delivery_no)}<br>${esc(r.date)}<input type="hidden" name="lines[${i}][stock_movement_item_id]" value="${r.stock_movement_item_id}"></td>
        <td class="small">${esc(r.order)}</td><td>${esc(r.product)}</td><td class="text-end">${q(r.delivered)} ${esc(r.unit)}</td>
        <td class="text-end">${q(r.invoiced)}</td><td class="text-end">${q(r.returned) || ''}</td>
        <td><input type="number" step="any" min="0" max="${r.can_return}" name="lines[${i}][quantity]" class="form-control form-control-sm"></td>
        <td><select name="lines[${i}][condition]" class="form-control form-control-sm"><option value="fresh">Fresh</option><option value="rejected">Rejected</option></select></td></tr>`).join('')
        : '<tr><td colspan="8" class="text-muted">Nothing delivered to this customer that can come back.</td></tr>';
    });
  }
  document.getElementById('cust').addEventListener('change', load);
  if (document.getElementById('cust').value) load();
})();
</script>
@endsection
