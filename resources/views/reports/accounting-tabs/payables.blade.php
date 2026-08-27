<table class="table table-bordered table-sm">
  <thead><tr><th>Vendor</th><th class="text-end">Balance Payable</th></tr></thead>
  <tbody>
    @foreach($data['vendors'] as $row)
    <tr><td>{{ $row['vendor']->name }}</td><td class="text-end">{{ number_format($row['balance'], 2) }}</td></tr>
    @endforeach
  </tbody>
  <tfoot class="fw-bold"><tr><td>Total</td><td class="text-end">{{ number_format($data['total'], 2) }}</td></tr></tfoot>
</table>