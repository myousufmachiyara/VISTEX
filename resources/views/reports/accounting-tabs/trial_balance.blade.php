<form method="GET" action="{{ route('reports.accounting') }}" class="row g-2 mb-3">
  <input type="hidden" name="tab" value="trial_balance">
  <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="{{ request('to_date', now()->toDateString()) }}" onchange="this.form.submit()"></div>
</form>
<table class="table table-bordered table-sm">
  <thead><tr><th>Code</th><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
  <tbody>
    @foreach($data['rows'] as $row)
    <tr>
      <td>{{ $row['account']->account_code }}</td>
      <td>{{ $row['account']->name }}</td>
      <td class="text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
      <td class="text-end">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot class="fw-bold">
    <tr><td colspan="2" class="text-end">Total</td><td class="text-end">{{ number_format($data['total_debit'], 2) }}</td><td class="text-end">{{ number_format($data['total_credit'], 2) }}</td></tr>
  </tfoot>
</table>