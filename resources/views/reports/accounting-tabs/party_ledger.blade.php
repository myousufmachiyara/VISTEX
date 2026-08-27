<form method="GET" action="{{ route('reports.accounting') }}" class="row g-2 mb-3">
  <input type="hidden" name="tab" value="party_ledger">
  <div class="col-md-2">
    <select name="party_type" class="form-control" onchange="this.form.submit()">
      <option value="">Type</option>
      <option value="customer" @selected(request('party_type')=='customer')>Customer</option>
      <option value="vendor" @selected(request('party_type')=='vendor')>Vendor</option>
    </select>
  </div>
  <div class="col-md-4">
    <select name="party_id" class="form-control select2-js" onchange="this.form.submit()">
      <option value="">Select Party</option>
      @if(request('party_type')=='customer')
        @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('party_id')==$c->id)>{{ $c->name }}</option>@endforeach
      @elseif(request('party_type')=='vendor')
        @foreach($vendors as $v)<option value="{{ $v->id }}" @selected(request('party_id')==$v->id)>{{ $v->name }}</option>@endforeach
      @endif
    </select>
  </div>
  <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()"></div>
  <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" onchange="this.form.submit()"></div>
</form>

@if($data['party'])
  <h5>{{ $data['party']->name }}</h5>
  <table class="table table-bordered table-sm">
    <thead><tr><th>Date</th><th>Voucher</th><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
      @foreach($data['entries'] as $e)
      <tr>
        <td>{{ $e->voucher->voucher_date?->format('d-M-Y') }}</td>
        <td>{{ $e->voucher->voucher_no ?? '' }}</td>
        <td>{{ $e->account->name ?? '' }}</td>
        <td class="text-end">{{ $e->debit > 0 ? number_format($e->debit,2) : '' }}</td>
        <td class="text-end">{{ $e->credit > 0 ? number_format($e->credit,2) : '' }}</td>
        <td class="text-end">{{ number_format($e->running_balance, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@else
  <p class="text-muted">Select a party to view its ledger.</p>
@endif