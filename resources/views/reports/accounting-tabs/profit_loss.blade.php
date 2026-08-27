<form method="GET" action="{{ route('reports.accounting') }}" class="row g-2 mb-3">
  <input type="hidden" name="tab" value="profit_loss">
  <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="{{ $data['from'] }}" onchange="this.form.submit()"></div>
  <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="{{ $data['to'] }}" onchange="this.form.submit()"></div>
</form>
<h6>Revenue</h6>
<table class="table table-bordered table-sm">
  @foreach($data['revenue'] as $r)
    <tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>
  @endforeach
  <tr class="fw-bold"><td>Total Revenue</td><td class="text-end">{{ number_format($data['total_revenue'], 2) }}</td></tr>
</table>
<h6>Expenses</h6>
<table class="table table-bordered table-sm">
  @foreach($data['expenses'] as $r)
    <tr><td>{{ $r['account']->name }}</td><td class="text-end">{{ number_format($r['amount'], 2) }}</td></tr>
  @endforeach
  <tr class="fw-bold"><td>Total Expenses</td><td class="text-end">{{ number_format($data['total_expense'], 2) }}</td></tr>
</table>
<h5 class="{{ $data['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
  Net {{ $data['net_profit'] >= 0 ? 'Profit' : 'Loss' }}: {{ number_format(abs($data['net_profit']), 2) }}
</h5>