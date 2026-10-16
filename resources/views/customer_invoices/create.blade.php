@extends('layouts.app')
@section('title', 'New Customer Invoice')
@section('content')
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('customer_invoices.store') }}">
  @csrf
  <section class="card">
    <header class="card-header"><h2 class="card-title">New Customer Invoice — from delivered goods</h2></header>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-md-4"><label>Customer</label><select name="customer_id" id="cust" class="form-control" required><option value="">Select…</option>
          @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $preset) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label>Invoice Date</label><input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-2"><label>Due Date <small class="text-muted">(from terms if blank)</small></label><input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
        <div class="col-md-2" id="rateF" style="display:none"><label>Exchange rate <span id="cur"></span></label><input type="number" step="0.0001" min="0" name="exchange_rate" id="rate" class="form-control" value="{{ old('exchange_rate') }}"></div>
      </div>
      <div class="table-responsive">
      <table class="table table-bordered table-sm" id="lines">
        <thead class="table-light"><tr><th></th><th>Order</th><th>Delivery</th><th>Item</th><th class="text-end">To invoice</th><th style="width:120px">Invoice qty</th><th>Billed as</th><th class="text-end">Rate</th><th class="text-end">Disc %</th><th class="text-end">Tax %</th><th class="text-end">Amount</th></tr></thead>
        <tbody><tr><td colspan="11" class="text-muted">Choose a customer.</td></tr></tbody>
        <tfoot><tr class="fw-bold"><td colspan="10" class="text-end">Total <span id="curT"></span></td><td class="text-end" id="total">0.00</td></tr></tfoot>
      </table>
      </div>
      <div class="small text-muted">One invoice is in one currency. Customer-order lines bill meters (pieces × meters per piece) at the order's price.</div>
      <div class="mt-3"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}"></div>
    </div>
    <footer class="card-footer text-end"><a href="{{ route('customer_invoices.index') }}" class="btn btn-outline-secondary">Cancel</a> <button class="btn btn-primary">Post Invoice</button></footer>
  </section>
  </form>
</div></div>
<script>
(function () {
  const url = @json(url('customer-invoices/uninvoiced'));
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const num = v => parseFloat(v) || 0;
  let rows = [];
  function render() {
    const body = document.querySelector('#lines tbody');
    if (!rows.length) { body.innerHTML = '<tr><td colspan="11" class="text-muted">Nothing delivered and not yet invoiced for this customer.</td></tr>'; return; }
    body.innerHTML = rows.map((r, i) => `<tr data-cur="${r.currency}" data-factor="${r.factor}" data-rate="${r.rate}" data-disc="${r.discount_pct}" data-tax="${r.tax_rate}">
      <td><input type="checkbox" class="pick" name="lines[${i}][pick]" value="1"><input type="hidden" name="lines[${i}][stock_movement_item_id]" value="${r.stock_movement_item_id}"></td>
      <td>${esc(r.order)} <span class="badge bg-light text-dark">${r.currency}</span></td><td class="small">${esc(r.delivery_no)}<br>${esc(r.delivery_date)}</td><td>${esc(r.product)}</td>
      <td class="text-end">${r.left}</td><td><input type="number" step="any" min="0" max="${r.left}" name="lines[${i}][quantity]" class="form-control form-control-sm q" value="${r.left}"></td>
      <td class="small billed"></td><td class="text-end">${r.rate}</td><td class="text-end">${r.discount_pct || ''}</td><td class="text-end">${r.tax_rate || ''}</td><td class="text-end amt"></td></tr>`).join('');
    calc();
  }
  function calc() {
    let tot = 0, cur = null;
    document.querySelectorAll('#lines tbody tr[data-cur]').forEach(tr => {
      const d = tr.dataset, q = num(tr.querySelector('.q').value), billed = q * num(d.factor);
      const amt = billed * num(d.rate) * (1 - num(d.disc) / 100), tax = amt * num(d.tax) / 100;
      tr.querySelector('.billed').textContent = d.factor != 1 ? (Math.round(billed * 1000) / 1000) + ' m' : '';
      tr.querySelector('.amt').textContent = (amt + tax).toFixed(2);
      if (tr.querySelector('.pick').checked) { tot += amt + tax; cur = d.cur; }
    });
    document.getElementById('total').textContent = tot.toFixed(2);
    document.getElementById('curT').textContent = cur ? '(' + cur + ')' : '';
    const foreign = cur && cur !== 'PKR';
    document.getElementById('rateF').style.display = foreign ? '' : 'none';
    document.getElementById('cur').textContent = foreign ? cur + ' → PKR' : '';
    if (foreign && !document.getElementById('rate').value) {
      const first = rows.find(r => r.currency === cur); if (first) document.getElementById('rate').value = first.exchange_rate;
    }
  }
  function load() {
    const c = document.getElementById('cust').value;
    if (!c) return;
    fetch(url + '/' + c, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => { rows = j; render(); });
  }
  document.querySelector('#lines tbody').addEventListener('input', calc);
  document.querySelector('#lines tbody').addEventListener('change', calc);
  document.getElementById('cust').addEventListener('change', load);
  if (document.getElementById('cust').value) load();
})();
</script>
@endsection
