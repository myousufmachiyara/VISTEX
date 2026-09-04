@extends('layouts.app')
@section('title', $receiving->receiving_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $receiving->receiving_no }}</h2>
      <span class="badge bg-{{ match($receiving->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $receiving->status === 'PendingApproval' ? 'Pending Approval' : $receiving->status }}</span>
    </header>
    <div class="card-body">
      @if($receiving->status === 'PendingApproval' && $receiving->canBeApprovedBy(auth()->user()))
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>Pending your approval.</span>
        <div>
          <form action="{{ route('purchase_receivings.approve', $receiving->id) }}" method="POST" class="d-inline">
            @csrf<button class="btn btn-success btn-sm" onclick="return confirm('Approve? This posts stock, vendor ledger, and creates a PDC.')">Approve</button>
          </form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        </div>
      </div>
      <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form action="{{ route('purchase_receivings.reject', $receiving->id) }}" method="POST">@csrf
          <div class="modal-header"><h5>Reject {{ $receiving->receiving_no }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
        </form>
      </div></div></div>
      @endif
      @if($receiving->status === 'Rejected')<div class="alert alert-danger"><strong>Rejected:</strong> {{ $receiving->rejection_reason }}</div>@endif

      <div class="row mb-3">
        <div class="col-md-3"><strong>PO #:</strong> {{ $receiving->purchaseOrder->order_no ?? '' }}</div>
        <div class="col-md-3"><strong>Challan #:</strong> {{ $receiving->challan->challan_no ?? '' }}</div>
        <div class="col-md-3"><strong>Vendor:</strong> {{ $receiving->purchaseOrder->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>Date:</strong> {{ $receiving->receiving_date->format('d-M-Y') }}</div>
      </div>

      <table class="table table-bordered">
        <thead><tr><th>Product</th><th class="text-end">Received</th><th class="text-end">Rejected</th><th class="text-end">Accepted</th><th class="text-end">Amount</th><th>Return Status</th></tr></thead>
        <tbody>
          @foreach($receiving->items as $item)
          <tr>
            <td>{{ $item->product->name ?? '' }}</td>
            <td class="text-end">{{ number_format($item->quantity_received,3) }}</td>
            <td class="text-end">{{ number_format($item->quantity_rejected,3) }}</td>
            <td class="text-end">{{ number_format($item->quantity_accepted,3) }}</td>
            <td class="text-end">{{ number_format($item->amount,2) }}</td>
            <td>
              @if($item->quantity_rejected > 0)
                @if($item->quantity_pending_return > 0)
                  <span class="badge bg-warning text-dark">{{ $item->quantity_pending_return }} pending return</span>
                @else
                  <span class="badge bg-success">Returned</span>
                @endif
              @else
                —
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div class="text-end fw-bold">Total: {{ number_format($receiving->amount,2) }}</div>

      @if($receiving->status === 'Approved')
      <div class="alert alert-info mt-3">
        Return and Amendment actions for this receiving are part of the next build stage.
      </div>
      @endif
    </div>
    <footer class="card-footer text-end"><a href="{{ route('purchase_receivings.index') }}" class="btn btn-outline-secondary">Back</a></footer>
  </section>
</div></div>
@endsection