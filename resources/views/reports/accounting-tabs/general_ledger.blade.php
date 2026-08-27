<form method="GET" action="{{ route('reports.accounting') }}" class="row g-2 mb-3">
  <input type="hidden" name="tab" value="general_ledger">
  <div class="col-md-4">
    <select name="account_id" class="form-control select2-js" onchange="this.form.submit()">
      <option value="">Select Account</option>
      @foreach($accounts as $acc)
        <option value="{{ $acc->id }}" @selected(request('account_id') == $acc->id)>{{ $acc->account_code }} — {{ $acc->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()"></div>
  <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" onchange="this.form.submit()"></div>
</form>

@if($data['account'])
  <h5>{{ $data['account']->account_code }} — {{ $data['account']->name }}</h5>
  <table class="table table-bordered table-sm">
    <thead><tr><th>Date</th><th>Voucher</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
      <tr class="table-light"><td colspan="5"><strong>Opening Balance</strong></td><td class="text-end">{{ number_format($data['opening'], 2) }}</td></tr>
      @foreach($data['entries'] as $e)
      <tr>
        <td>{{ $e->voucher->voucher_date?->format('d-M-Y') }}</td>
        <td>{{ $e->voucher->voucher_no ?? '' }}</td>
        <td>{{ $e->voucher->narration ?? '' }}</td>
        <td class="text-end">{{ $e->debit > 0 ? number_format($e->debit, 2) : '' }}</td>
        <td class="text-end">{{ $e->credit > 0 ? number_format($e->credit, 2) : '' }}</td>
        <td class="text-end">{{ number_format($e->running_balance, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@else
  <p class="text-muted">Select an account to view its ledger.</p>
@endif