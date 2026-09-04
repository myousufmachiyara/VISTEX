@extends('layouts.app')
@section('title', $pdc->pdc_no)
@section('content')
<div class="row"><div class="col-md-8">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $pdc->pdc_no }}</h2>
      <span class="badge bg-{{ match($pdc->status){'Cleared'=>'success','Bounced'=>'danger','Issued'=>'info',default=>'warning text-dark'} }}">{{ $pdc->status }}</span>
    </header>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4"><strong>Party:</strong> {{ $pdc->party->name ?? '' }}</div>
        <div class="col-md-4"><strong>Amount:</strong> {{ number_format($pdc->amount, 2) }}</div>
        <div class="col-md-4"><strong>Due Date:</strong> {{ $pdc->due_date->format('d-M-Y') }}</div>
      </div>

      @if($receiving)
      <div class="row mb-3">
        <div class="col-md-4">
          <strong>PO #:</strong>
          <a href="{{ route('purchase_orders.show', $receiving->purchase_order_id) }}" target="_blank">
            {{ $receiving->purchaseOrder->order_no ?? '' }}
          </a>
        </div>
        <div class="col-md-4">
          <strong>GRN #:</strong>
          <a href="{{ route('purchase_receivings.show', $receiving->id) }}" target="_blank">
            {{ $receiving->receiving_no }}
          </a>
        </div>
        <div class="col-md-4"><strong>GRN Status:</strong> {{ $receiving->status }}</div>
      </div>
      @endif
      @if($pdc->status === 'Pending')
      <form action="{{ route('pdcs.mark_created', $pdc->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <h6>Mark as Created</h6>
        <div class="row">
          <div class="col-md-4 mb-2">
            <label>Bank</label>
            <select name="bank_account_id" class="form-control" required>
              <option value="">Select Bank</option>
              @foreach($bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-2"><label>Cheque #</label><input type="text" name="cheque_no" class="form-control" required></div>
          <div class="col-md-4 mb-2"><label>Unsigned Cheque Image</label><input type="file" name="unsigned_cheque_image" class="form-control" accept="image/*" required></div>
        </div>
        <button type="submit" class="btn btn-primary">Mark as Created</button>
      </form>
      @endif

      @if($pdc->status === 'Created')
      <p><strong>Bank:</strong> {{ $pdc->bankAccount->name ?? '' }} — <strong>Cheque #:</strong> {{ $pdc->cheque_no }}</p>
      <form action="{{ route('pdcs.mark_signed', $pdc->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <h6>Mark as Signed</h6>
        <div class="mb-2"><label>Signed Cheque Image</label><input type="file" name="signed_cheque_image" class="form-control" accept="image/*" required></div>
        <button type="submit" class="btn btn-primary">Mark as Signed</button>
      </form>
      @endif

      @if($pdc->status === 'Signed')
      <form action="{{ route('pdcs.mark_issued', $pdc->id) }}" method="POST" enctype="multipart/form-data" id="issueForm">
        @csrf
        <h6>Mark as Issued</h6>
        <div class="mb-2">
          <label>Issue Method</label>
          <select name="issue_method" id="issue_method" class="form-control" required>
            <option value="">Select</option>
            <option value="handed_to_vendor">Handed to Vendor Directly</option>
            <option value="bank_deposit">Submitted to Bank</option>
          </select>
        </div>
        <div id="vendorFields" style="display:none">
          <div class="row">
            <div class="col-md-4 mb-2"><label>Receiver Name</label><input type="text" name="receiver_name" class="form-control"></div>
            <div class="col-md-4 mb-2"><label>Receiver Contact</label><input type="text" name="receiver_contact" class="form-control"></div>
            <div class="col-md-4 mb-2"><label>Receiver CNIC <span class="text-muted">(optional)</span></label><input type="text" name="receiver_cnic" class="form-control"></div>
            <div class="col-md-6 mb-2"><label>Signed Receipt Image</label><input type="file" name="receipt_signed_image" class="form-control" accept="image/*"></div>
          </div>
        </div>
        <div id="bankFields" style="display:none">
          <div class="mb-2"><label>Bank Deposit Slip Image</label><input type="file" name="bank_slip_image" class="form-control" accept="image/*"></div>
        </div>
        <div class="mb-2"><label>Issued Date</label><input type="date" name="issued_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
        <button type="submit" class="btn btn-primary">Mark as Issued</button>
      </form>
      <script>
        document.getElementById('issue_method').addEventListener('change', function () {
          document.getElementById('vendorFields').style.display = this.value === 'handed_to_vendor' ? 'block' : 'none';
          document.getElementById('bankFields').style.display = this.value === 'bank_deposit' ? 'block' : 'none';
        });
      </script>
      @endif

      @if($pdc->status === 'Issued')
      <p><strong>Issued via:</strong> {{ $pdc->issue_method === 'handed_to_vendor' ? 'Handed to Vendor' : 'Bank Deposit' }} on {{ $pdc->issued_date?->format('d-M-Y') }}</p>
      <form action="{{ route('pdcs.mark_cleared', $pdc->id) }}" method="POST" class="d-inline">
        @csrf
        <input type="date" name="cleared_date" class="form-control d-inline w-auto" value="{{ date('Y-m-d') }}">
        <button type="submit" class="btn btn-success" onclick="return confirm('Mark cleared? This posts to vendor ledger and bank balance.')">Mark as Cleared</button>
      </form>
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bounceModal">Mark as Bounced</button>
      <div class="modal fade" id="bounceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form action="{{ route('pdcs.mark_bounced', $pdc->id) }}" method="POST">
          @csrf
          <div class="modal-header"><h5>Mark {{ $pdc->pdc_no }} as Bounced</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Bounced</button></div>
        </form>
      </div></div></div>
      @endif

      @if($pdc->status === 'Cleared')<div class="alert alert-success">Cleared on {{ $pdc->cleared_date?->format('d-M-Y') }}.</div>@endif
      @if($pdc->status === 'Bounced')<div class="alert alert-danger">Bounced on {{ $pdc->bounced_date?->format('d-M-Y') }}: {{ $pdc->bounced_reason }}</div>@endif
    </div>
    <footer class="card-footer text-end"><a href="{{ route('pdcs.index') }}" class="btn btn-outline-secondary">Back</a></footer>
  </section>
</div></div>
@endsection