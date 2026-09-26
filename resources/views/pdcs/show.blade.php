@extends('layouts.app')
@section('title', $pdc->pdc_no)
@section('content')
<div class="row"><div class="col-md-9">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header"><h2 class="card-title">{{ $pdc->pdc_no }}</h2></header>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><strong>Party:</strong> {{ $pdc->party->name ?? '' }}</div>
        <div class="col-md-3"><strong>Total Amount:</strong> {{ number_format($pdc->amount, 2) }}</div>
        <div class="col-md-3"><strong>Due Date:</strong> {{ $pdc->due_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Remaining Unallocated:</strong> <span class="{{ $pdc->pending_amount > 0 ? 'text-danger fw-bold' : 'text-success' }}">{{ number_format($pdc->pending_amount, 2) }}</span></div>
      </div>
      @if($receiving)
      <div class="row mb-3">
        <div class="col-md-4"><strong>PO #:</strong> <a href="{{ route('purchase_orders.show', $receiving->purchase_order_id) }}" target="_blank">{{ $receiving->purchaseOrder->order_no ?? '' }}</a></div>
        <div class="col-md-4"><strong>GRN #:</strong> <a href="{{ route('purchase_receivings.show', $receiving->id) }}" target="_blank">{{ $receiving->receiving_no }}</a></div>
      </div>
      @endif

      @if($pdc->pending_amount > 0.01)
      <div class="border rounded p-3 mb-4 bg-light" id="addChequesBlock" data-pending="{{ $pdc->pending_amount }}" data-action="{{ route('pdcs.add_cheque', $pdc->id) }}">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Add Cheque(s)</h6>
          <div>
            Remaining Unallocated: <strong id="pendingDisplay">{{ number_format($pdc->pending_amount, 2) }}</strong> &nbsp;
            Allocated in this batch: <strong id="batchTotalDisplay">0.00</strong>
            <span id="batchOverWarning" class="text-danger ms-2" style="display:none">Exceeds remaining unallocated amount</span>
          </div>
        </div>
        <table class="table table-sm table-bordered mb-2" id="chequeRowsTable">
          <thead><tr><th width="18%">Amount</th><th width="22%">Bank</th><th width="18%">Cheque #</th><th width="27%">Unsigned Cheque Image</th><th width="10%">Status</th><th></th></tr></thead>
          <tbody id="chequeRowsBody"></tbody>
        </table>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addChequeRowBtn">+ Add Another Cheque</button>
        <button type="button" class="btn btn-primary btn-sm" id="saveAllChequesBtn">Save Cheque(s)</button>
        <div id="batchProgressMsg" class="small text-muted mt-2"></div>
      </div>
      @endif

      <h6>Cheques Against This PDC</h6>
      @if($pdc->cheques->isEmpty())
        <p class="text-muted">No cheques added yet.</p>
      @else
        @foreach($pdc->cheques as $cheque)
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Cheque #{{ $cheque->sequence_no }} — {{ number_format($cheque->amount, 2) }}</strong>
            <span class="badge bg-{{ match($cheque->status){'Cleared'=>'success','Bounced'=>'danger','Issued'=>'info',default=>'warning text-dark'} }}">{{ $cheque->status }}</span>
          </div>
          <p class="small text-muted mb-2">Bank: {{ $cheque->bankAccount->name ?? '' }} — Cheque #: {{ $cheque->cheque_no }}</p>

          @if($cheque->status === 'Created')
          <form action="{{ route('pdcs.mark_signed', $cheque->id) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
            @csrf
            <div><label class="small">Signed Cheque Image</label><input type="file" name="signed_cheque_image" class="form-control form-control-sm" accept="image/*" required></div>
            <button class="btn btn-sm btn-primary">Mark Signed</button>
          </form>
          @endif

          @if($cheque->status === 'Signed')
          <form action="{{ route('pdcs.mark_issued', $cheque->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
              <div class="col-md-3"><select name="issue_method" class="form-control form-control-sm issue-method-select" data-target="cheque{{ $cheque->id }}" required><option value="">Issue Method</option><option value="handed_to_vendor">Handed to Vendor</option><option value="bank_deposit">Bank Deposit</option></select></div>
              <div class="col-md-3 vendor-fields-{{ $cheque->id }}" style="display:none"><input type="text" name="receiver_name" class="form-control form-control-sm" placeholder="Receiver Name"></div>
              <div class="col-md-3 vendor-fields-{{ $cheque->id }}" style="display:none"><input type="file" name="receipt_signed_image" class="form-control form-control-sm" accept="image/*"></div>
              <div class="col-md-3 bank-fields-{{ $cheque->id }}" style="display:none"><input type="file" name="bank_slip_image" class="form-control form-control-sm" accept="image/*"></div>
              <div class="col-md-3"><button class="btn btn-sm btn-primary">Mark Issued</button></div>
            </div>
          </form>
          @endif

          @if($cheque->status === 'Issued')
          <div class="d-flex gap-2">
            <form action="{{ route('pdcs.mark_cleared', $cheque->id) }}" method="POST" class="d-flex gap-1">
              @csrf<input type="date" name="cleared_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"><button class="btn btn-sm btn-success" onclick="return confirm('Clear this cheque?')">Clear</button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bounceModal{{ $cheque->id }}">Bounced</button>
          </div>
          <div class="modal fade" id="bounceModal{{ $cheque->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form action="{{ route('pdcs.mark_bounced', $cheque->id) }}" method="POST">
              @csrf
              <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
              <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm</button></div>
            </form>
          </div></div></div>
          @endif

          @if($cheque->status === 'Cleared')<p class="text-success small mb-0">Cleared on {{ $cheque->cleared_date?->format('d-M-Y') }}</p>@endif
          @if($cheque->status === 'Bounced')<p class="text-danger small mb-0">Bounced: {{ $cheque->bounced_reason }}</p>@endif
        </div>
        @endforeach
      @endif
    </div>
    <footer class="card-footer text-end"><a href="{{ route('pdcs.index') }}" class="btn btn-outline-secondary">Back</a></footer>
  </section>
</div></div>
<script>
$(document).on('change', '.issue-method-select', function () {
  const id = $(this).data('target').replace('cheque', '');
  const val = $(this).val();
  $('.vendor-fields-' + id).toggle(val === 'handed_to_vendor');
  $('.bank-fields-' + id).toggle(val === 'bank_deposit');
});

// ── Add multiple cheques against this PDC in one go ──
const bankOptionsHtml = `<option value="">Select Bank</option>@foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach`;
let chequeRowIndex = 0;

function addChequeRow() {
  const idx = chequeRowIndex++;
  const pending = parseFloat($('#addChequesBlock').data('pending')) || 0;
  const row = $(`
    <tr class="cheque-row" data-idx="${idx}">
      <td><input type="number" class="form-control form-control-sm cheque-amount" step="any" min="0.01" max="${pending}" value="0"></td>
      <td><select class="form-control form-control-sm cheque-bank">${bankOptionsHtml}</select></td>
      <td><input type="text" class="form-control form-control-sm cheque-no"></td>
      <td><input type="file" class="form-control form-control-sm cheque-image" accept="image/*"></td>
      <td class="cheque-row-status text-muted small">Pending</td>
      <td><button type="button" class="btn btn-sm btn-outline-danger remove-cheque-row">&times;</button></td>
    </tr>
  `);
  $('#chequeRowsBody').append(row);
}
$('#addChequeRowBtn').on('click', addChequeRow);
addChequeRow(); // start with one row

$(document).on('click', '.remove-cheque-row', function () {
  if ($('.cheque-row').length > 1) { $(this).closest('tr').remove(); recalcBatchTotal(); }
});

$(document).on('input', '.cheque-amount', recalcBatchTotal);

function recalcBatchTotal() {
  let total = 0;
  $('.cheque-amount').each(function () { total += parseFloat($(this).val()) || 0; });
  const pending = parseFloat($('#addChequesBlock').data('pending')) || 0;
  $('#batchTotalDisplay').text(total.toFixed(2));
  $('#batchOverWarning').toggle(total > pending + 0.005);
}

// Submits each row one at a time to the existing single-cheque endpoint
// (unchanged backend), so multiple cheques can be raised in one sitting
// without needing a new batch API.
$('#saveAllChequesBtn').on('click', async function () {
  const $btn = $(this);
  const $rows = $('.cheque-row');
  const actionUrl = $('#addChequesBlock').data('action');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const total = [...$('.cheque-amount')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
  const pending = parseFloat($('#addChequesBlock').data('pending')) || 0;
  if (total > pending + 0.005) {
    if (!confirm('The total of these cheques exceeds the remaining unallocated amount. Continue anyway?')) return;
  }

  $btn.prop('disabled', true);
  $('#addChequeRowBtn').prop('disabled', true);

  let savedCount = 0;
  for (let i = 0; i < $rows.length; i++) {
    const $row = $rows.eq(i);
    const amount = $row.find('.cheque-amount').val();
    const bankId = $row.find('.cheque-bank').val();
    const chequeNo = $row.find('.cheque-no').val();
    const file = $row.find('.cheque-image')[0].files[0];
    const $status = $row.find('.cheque-row-status');

    if (!amount || parseFloat(amount) <= 0 || !bankId || !chequeNo || !file) {
      $status.removeClass('text-muted').addClass('text-danger').text('Incomplete — skipped');
      continue;
    }

    $status.removeClass('text-muted text-danger text-success').text('Saving…');
    $('#batchProgressMsg').text(`Saving cheque ${i + 1} of ${$rows.length}…`);

    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('amount', amount);
    formData.append('bank_account_id', bankId);
    formData.append('cheque_no', chequeNo);
    formData.append('unsigned_cheque_image', file);

    try {
      const res = await fetch(actionUrl, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await res.text();
      const failed = /alert-danger/.test(html);
      if (failed) {
        const match = html.match(/alert-danger[^>]*>\s*([\s\S]*?)\s*<\/div>/);
        $status.removeClass('text-muted').addClass('text-danger').text('Failed');
        $('#batchProgressMsg').html(`<span class="text-danger">Cheque ${i + 1} failed${match ? ': ' + match[1].trim() : ''}. Remaining rows were not submitted — fix and try again.</span>`);
        break;
      }
      $status.removeClass('text-muted').addClass('text-success').text('Saved');
      savedCount++;
    } catch (e) {
      $status.removeClass('text-muted').addClass('text-danger').text('Failed');
      $('#batchProgressMsg').html('<span class="text-danger">Network error — remaining rows were not submitted.</span>');
      break;
    }
  }

  if (savedCount > 0) {
    $('#batchProgressMsg').append(`<br>${savedCount} cheque(s) saved. Reloading…`);
    setTimeout(() => window.location.reload(), 900);
  } else {
    $btn.prop('disabled', false);
    $('#addChequeRowBtn').prop('disabled', false);
  }
});
</script>
@endsection