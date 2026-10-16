<div class="table-responsive">
<table class="table table-bordered table-sm">
  <thead class="table-light"><tr><th>Date</th><th>Voucher</th><th>Document</th><th>Details</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
  <tbody>
    <tr class="fw-bold"><td>{{ date('d-M-Y', strtotime($from)) }}</td><td colspan="5">Opening balance</td><td class="text-end">{{ number_format($result['opening'], 2) }}</td></tr>
    @foreach($result['rows'] as $r)
      <tr><td>{{ date('d-M-Y', strtotime($r['date'])) }}</td><td class="small">{{ $r['voucher_no'] }}</td><td class="small">{{ $r['document'] }}</td><td class="small">{{ $r['narration'] }}</td>
        <td class="text-end">{{ $r['debit'] ? number_format($r['debit'], 2) : '' }}</td><td class="text-end">{{ $r['credit'] ? number_format($r['credit'], 2) : '' }}</td>
        <td class="text-end">{{ number_format($r['balance'], 2) }}</td></tr>
    @endforeach
  </tbody>
  <tfoot class="fw-bold">
    <tr><td colspan="4" class="text-end">Totals</td><td class="text-end">{{ number_format($result['debits'], 2) }}</td><td class="text-end">{{ number_format($result['credits'], 2) }}</td><td></td></tr>
    <tr><td colspan="6" class="text-end">Closing balance ({{ $type === 'customer' ? ($result['closing'] >= 0 ? 'customer owes us' : 'we owe the customer') : ($result['closing'] >= 0 ? 'we owe' : 'they owe us') }})</td><td class="text-end">{{ number_format(abs($result['closing']), 2) }}</td></tr>
  </tfoot>
</table>
</div>
<div class="small text-muted">PKR. From all postings against the party up to the date; master-file opening balances are not included.</div>
