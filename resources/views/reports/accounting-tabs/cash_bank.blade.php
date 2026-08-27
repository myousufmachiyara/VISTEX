<form method="GET" action="{{ route('reports.accounting') }}" class="row g-2 mb-3">
  <input type="hidden" name="tab" value="cash_bank">
  <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()"></div>
  <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="{{ request('to_date', now()->toDateString()) }}" onchange="this.form.submit()"></div>
</form>
@foreach($data['accounts'] as $acc)
  <h6>{{ $acc['account']->name }} <span class="text-muted small">(Closing: {{ number_format($acc['closing'], 2) }})</span></h6>
  <table class="table table-bordered table-sm mb-4">
    <thead><tr><th>Date</th><th>Voucher</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
      @foreach($acc['entries'] as $e)
      <tr>
        <td>{{ $e->voucher->voucher_date?->format('d-M-Y') }}</td>
        <td>{{ $e->voucher->voucher_no ?? '' }}</td>
        <td class="text-end">{{ $e->debit > 0 ? number_format($e->debit,2) : '' }}</td>
        <td class="text-end">{{ $e->credit > 0 ? number_format($e->credit,2) : '' }}</td>
        <td class="text-end">{{ number_format($e->running_balance, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endforeach