@extends('layouts.app')
@section('title', ucfirst($type) . ' Vouchers')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ ucfirst($type) }} Vouchers</h2>
      @if($type !== 'system')
      @can('vouchers.create')
      <a href="{{ route('vouchers.create', $type) }}" class="btn btn-primary">New {{ ucfirst($type) }}</a>
      @endcan
      @endif
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Date</th><th>Voucher #</th><th>Narration</th><th class="text-end">Amount</th><th>Source</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($vouchers as $v)
          <tr>
            <td>{{ $v->voucher_date->format('d-M-Y') }}</td>
            <td class="text-primary">
              <a href="javascript:void(0)" onclick="showVoucher('{{ $type }}', {{ $v->id }})">{{ $v->voucher_no }}</a>
            </td>
            <td>{{ $v->narration }}</td>
            <td class="text-end">{{ number_format($v->total_debit, 2) }}</td>
            <td>{{ $v->reference_type ? $v->reference_type . ' #' . $v->reference_id : 'Manual' }}</td>
            <td>
              @if(!$v->reference_type)
              @can('vouchers.delete')
              <form action="{{ route('vouchers.destroy', [$type, $v->id]) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
              </form>
              @endcan
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <div id="voucherModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <header class="card-header"><h2 class="card-title" id="vm_no"></h2></header>
      <div class="card-body">
        <p><strong>Date:</strong> <span id="vm_date"></span></p>
        <p><strong>Narration:</strong> <span id="vm_narration"></span></p>
        <table class="table table-sm table-bordered">
          <thead><tr><th>Account</th><th>Party</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
          <tbody id="vm_body"></tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="button" class="btn btn-default modal-dismiss">Close</button></footer>
    </section>
  </div>
</div></div>

<script>
function showVoucher(type, id) {
  fetch(`/vouchers/${type}/${id}`).then(r => r.json()).then(data => {
    $('#vm_no').text(data.voucher_no);
    $('#vm_date').text(data.date);
    $('#vm_narration').text(data.narration ?? '');
    let html = '';
    data.entries.forEach(e => {
      html += `<tr><td>${e.account_name}</td><td>${e.party_name ?? '—'}</td><td class="text-end">${e.debit > 0 ? e.debit.toFixed(2) : ''}</td><td class="text-end">${e.credit > 0 ? e.credit.toFixed(2) : ''}</td></tr>`;
    });
    $('#vm_body').html(html);
    $.magnificPopup.open({ items: { src: '#voucherModal' }, type: 'inline' });
  });
}
</script>
@endsection