@extends('layouts.app')
@section('title', 'Receive Payment')
@section('content')
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('customer_receipts.store') }}">
  @csrf
  <section class="card">
    <header class="card-header"><h2 class="card-title">Receive Payment from Customer</h2></header>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-md-4"><label>Customer</label><select name="customer_id" id="cust" class="form-control" required><option value="">Select…</option>
          @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $preset['customer_id'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label>Date</label><input type="date" name="receipt_date" class="form-control" value="{{ old('receipt_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-3"><label>Received into <small class="text-muted" id="intoHint"></small></label><select name="account_id" class="form-control" required>
          @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label>Method</label><div class="d-flex gap-2"><select name="method" class="form-control">
          @foreach(\App\Models\CustomerReceipt::METHODS as $k => $label)<option value="{{ $k }}" @selected(old('method', 'bank') === $k)>{{ $label }}</option>@endforeach</select>
          <input name="reference" class="form-control" placeholder="Cheque # / ref" value="{{ old('reference') }}"></div></div>
        <div class="col-md-2" id="chqF"><label>Cheque date</label><input type="date" name="cheque_date" id="chq" class="form-control" value="{{ old('cheque_date') }}"></div>
        <div class="col-md-2"><label>Currency</label><select name="currency" id="cur" class="form-control">
          @foreach(\App\Models\Job::CURRENCIES as $c)<option value="{{ $c }}" @selected(old('currency', 'PKR') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="col-md-2" id="rateF"><label>Rate to PKR</label><input type="number" step="0.0001" min="0" name="exchange_rate" id="rate" class="form-control" value="{{ old('exchange_rate') }}"></div>
        <div class="col-md-3"><label>Amount received</label><input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required></div>
        <div class="col-md-5"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}"></div>
      </div>
      <div class="d-flex justify-content-between align-items-end mb-1"><h6 class="mb-0">Allocate to invoices</h6><button type="button" class="btn btn-sm btn-outline-secondary" id="auto">Allocate oldest first</button></div>
      <table class="table table-bordered table-sm" id="inv">
        <thead class="table-light"><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th style="width:160px">Allocate</th></tr></thead>
        <tbody><tr><td colspan="6" class="text-muted">Choose a customer.</td></tr></tbody>
      </table>
      <div class="small" id="summary"></div>
    </div>
    <footer class="card-footer text-end"><a href="{{ route('customer_receipts.index') }}" class="btn btn-outline-secondary">Cancel</a> <button class="btn btn-primary">Post Receipt</button></footer>
  </section>
  </form>
</div></div>
<script>
(function () {
  const url = @json(url('customer-receipts/open-invoices')), presetInv = @json($preset['invoice_id'] ?? null);
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const num = v => parseFloat(v) || 0;
  let invoices = [];
  const cur = () => document.getElementById('cur').value;
  function render() {
    const body = document.querySelector('#inv tbody'), list = invoices.filter(i => i.currency === cur());
    body.innerHTML = list.length ? list.map((i, k) => `<tr><td>${esc(i.invoice_no)}<input type="hidden" name="allocations[${k}][customer_invoice_id]" value="${i.id}"></td>
      <td>${esc(i.date)}</td><td class="${i.overdue ? 'text-danger fw-bold' : ''}">${esc(i.due ?? '')}</td><td class="text-end">${i.total.toFixed(2)}</td><td class="text-end">${i.balance.toFixed(2)}</td>
      <td><input type="number" step="0.01" min="0" max="${i.balance}" name="allocations[${k}][amount]" class="form-control form-control-sm al" data-id="${i.id}"></td></tr>`).join('')
      : `<tr><td colspan="6" class="text-muted">No open ${esc(cur())} invoices — the amount will be kept on account.</td></tr>`;
    document.getElementById('rateF').style.display = cur() === 'PKR' ? 'none' : '';
    if (presetInv) { const inv = list.find(i => i.id == presetInv); if (inv) { const a = document.querySelector(`.al[data-id="${inv.id}"]`); a.value = inv.balance.toFixed(2); if (!document.getElementById('amount').value) document.getElementById('amount').value = inv.balance.toFixed(2); } }
    sum();
  }
  function sum() {
    let a = 0; document.querySelectorAll('.al').forEach(i => a += num(i.value));
    const amt = num(document.getElementById('amount').value), left = Math.round((amt - a) * 100) / 100;
    document.getElementById('summary').innerHTML = `Allocated <strong>${a.toFixed(2)}</strong> of ${amt.toFixed(2)}` + (left > 0 ? ` · <strong>${left.toFixed(2)}</strong> kept on account` : '') + (left < 0 ? ' · <span class="text-danger">more than received</span>' : '');
  }
  document.getElementById('auto').addEventListener('click', () => {
    let left = num(document.getElementById('amount').value);
    document.querySelectorAll('.al').forEach(i => { const take = Math.min(left, num(i.max)); i.value = take > 0 ? take.toFixed(2) : ''; left -= take; });
    sum();
  });
  function load() {
    const c = document.getElementById('cust').value;
    if (!c) return;
    fetch(url + '/' + c, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
      invoices = j;
      if (presetInv) { const inv = j.find(i => i.id == presetInv); if (inv) { document.getElementById('cur').value = inv.currency; } }
      render();
    });
  }
  const method = document.querySelector('[name=method]');
  const pdcUi = () => { const p = method.value === 'pdc'; document.getElementById('chqF').style.display = p ? '' : 'none'; document.getElementById('chq').required = p;
    document.getElementById('intoHint').textContent = p ? '(when it clears)' : ''; };
  method.addEventListener('change', pdcUi); pdcUi();
  document.getElementById('cust').addEventListener('change', load);
  document.getElementById('cur').addEventListener('change', render);
  document.getElementById('amount').addEventListener('input', sum);
  document.querySelector('#inv tbody').addEventListener('input', sum);
  if (document.getElementById('cust').value) load(); else render();
})();
</script>
@endsection
