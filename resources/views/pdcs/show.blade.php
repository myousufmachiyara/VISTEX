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
      <form action="{{ route('pdcs.add_cheque', $pdc->id) }}" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-4 bg-light">
        @csrf
        <h6>Add Cheque</h6>
        <div class="row">
          <div class="col-md-3 mb-2"><label>Amount</label><input type="number" name="amount" class="form-control" step="any" min="0.01" max="{{ $pdc->pending_amount }}" value="{{ $pdc->pending_amount }}" required></div>
          <div class="col-md-3 mb-2"><label>Bank</label><select name="bank_account_id" class="form-control" required><option value="">Select Bank</option>@foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div class="col-md-3 mb-2"><label>Cheque #</label><input type="text" name="cheque_no" class="form-control" required></div>
          <div class="col-md-3 mb-2"><label>Unsigned Cheque Image</label><input type="file" name="unsigned_cheque_image" class="form-control" accept="image/*" required></div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm mt-2">Add Cheque</button>
      </form>
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
</script>
@endsection