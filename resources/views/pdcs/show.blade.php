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
      @can('pdcs.edit')
      <form action="{{ route('pdcs.add_cheques', $pdc->id) }}" method="POST" enctype="multipart/form-data" id="addChequesForm"
            class="border rounded p-3 mb-4 bg-light" data-pending="{{ $pdc->pending_amount }}" onkeydown="return event.key != 'Enter';">
        @csrf
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
          <h6 class="mb-0">Add Cheque(s)</h6>
          <div class="small">
            Unallocated: <strong>{{ number_format($pdc->pending_amount, 2) }}</strong> &nbsp;·&nbsp;
            This batch: <strong id="batchTotalDisplay">0.00</strong> &nbsp;·&nbsp;
            Left after: <strong id="batchLeftDisplay">{{ number_format($pdc->pending_amount, 2) }}</strong>
          </div>
        </div>
        @if($errors->any())
          <div class="alert alert-danger py-2 small mb-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <div class="table-responsive">
        <table class="table table-sm table-bordered mb-2 bg-white">
          <thead><tr>
            <th width="4%">#</th><th width="15%">Amount</th><th width="20%">Bank</th><th width="15%">Cheque #</th>
            <th width="14%">Cheque Date</th><th width="27%">Unsigned Cheque Image</th><th width="5%"></th>
          </tr></thead>
          <tbody id="chequeRowsBody"></tbody>
        </table>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-outline-primary btn-sm" id="addChequeRowBtn">+ Add Another Cheque</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="splitEqualBtn" title="Divide the unallocated amount equally across the rows">Split Equally</button>
          <span id="batchOverWarning" class="text-danger small" style="display:none">Batch is more than the unallocated amount.</span>
          <button type="submit" class="btn btn-primary btn-sm ms-auto" id="saveAllChequesBtn">Save Cheque(s)</button>
        </div>
        <div class="small text-muted mt-2">All cheques are saved together — if any row has a problem, none are saved.</div>
      </form>
      @endcan
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
          <p class="small text-muted mb-2">Bank: {{ $cheque->bankAccount->name ?? '' }} — Cheque #: {{ $cheque->cheque_no }} — Dated: {{ ($cheque->cheque_date ?? $pdc->due_date)->format('d-M-Y') }}</p>

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

// ── Add several cheques against this PDC in one submit ──
const bankOptionsHtml = `<option value="">Select Bank</option>@foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ e($b->name) }}</option>@endforeach`;
const PDC_DUE = @json($pdc->due_date->format('Y-m-d'));
let chequeRowIndex = 0;

function addChequeRow(prefill = {}) {
  const i = chequeRowIndex++;
  const row = $(`
    <tr class="cheque-row">
      <td class="row-no text-muted"></td>
      <td><input type="number" name="cheques[${i}][amount]" class="form-control form-control-sm cheque-amount" step="0.01" min="0.01" required></td>
      <td><select name="cheques[${i}][bank_account_id]" class="form-control form-control-sm cheque-bank" required>${bankOptionsHtml}</select></td>
      <td><input type="text" name="cheques[${i}][cheque_no]" class="form-control form-control-sm" maxlength="50" required></td>
      <td><input type="date" name="cheques[${i}][cheque_date]" class="form-control form-control-sm" value="${PDC_DUE}"></td>
      <td><input type="file" name="cheques[${i}][unsigned_cheque_image]" class="form-control form-control-sm" accept="image/*" required></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger remove-cheque-row" title="Remove">&times;</button></td>
    </tr>`);
  // Same bank as the row above saves re-selecting it each time
  const prevBank = $('#chequeRowsBody .cheque-bank').last().val();
  if (prevBank) row.find('.cheque-bank').val(prevBank);
  $('#chequeRowsBody').append(row);
  renumber(); recalcBatchTotal();
}

function renumber() { $('#chequeRowsBody .cheque-row').each((n, r) => $(r).find('.row-no').text(n + 1)); }

function recalcBatchTotal() {
  const pending = parseFloat($('#addChequesForm').data('pending')) || 0;
  let total = 0;
  $('.cheque-amount').each(function () { total += parseFloat(this.value) || 0; });
  const over = total > pending + 0.005;
  $('#batchTotalDisplay').text(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  $('#batchLeftDisplay').text((pending - total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
    .toggleClass('text-danger', over);
  $('#batchOverWarning').toggle(over);
  $('#saveAllChequesBtn').prop('disabled', over);
}

if ($('#addChequesForm').length) {
  addChequeRow();
  $('#addChequeRowBtn').on('click', () => addChequeRow());
  $(document).on('click', '.remove-cheque-row', function () {
    if ($('.cheque-row').length > 1) { $(this).closest('tr').remove(); renumber(); recalcBatchTotal(); }
  });
  $(document).on('input', '.cheque-amount', recalcBatchTotal);

  // Divide the unallocated amount across the rows; any paisa remainder goes on the last cheque
  $('#splitEqualBtn').on('click', function () {
    const pending = parseFloat($('#addChequesForm').data('pending')) || 0;
    const $amounts = $('.cheque-amount'); const n = $amounts.length;
    const each = Math.floor((pending / n) * 100) / 100;
    $amounts.each(function (k) { this.value = (k === n - 1 ? (pending - each * (n - 1)) : each).toFixed(2); });
    recalcBatchTotal();
  });

  $('#addChequesForm').on('submit', function () {
    const n = $('.cheque-row').length;
    $('#saveAllChequesBtn').prop('disabled', true).text('Saving…');
    return true;
  });
}
</script>
@endsection