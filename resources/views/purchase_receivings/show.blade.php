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
            <td>{{ $item->product->name ?? '' }}@if($item->grade_label)<div class="small text-muted">Quality: {{ $item->grade_label }}</div>@endif</td>
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
      @if(($receiving->purchaseOrder->type ?? null) === 'processing')
        <div class="row small mt-2">
          <div class="col-md-6">
            <strong>Cost:</strong> {{ $receiving->purchaseOrder->isReprocess() ? 'fabric value' : 'greige used' }} {{ number_format($receiving->greige_cost_amount, 2) }} + processing charge {{ number_format($receiving->processing_charge_amount, 2) }}
            @if($receiving->is_final_receiving)<span class="badge bg-dark ms-1">Final delivery</span>@endif
          </div>
          <div class="col-md-6">
            <strong>{{ $receiving->purchaseOrder->isReprocess() ? 'Fabric used (reprocessed)' : 'Greige used at the mill' }}:</strong>
            @forelse($receiving->greige_consumed_meta ?? [] as $g)
              lot {{ $g['lot_no'] ?? '—' }}: {{ rtrim(rtrim(number_format($g['quantity'], 3), '0'), '.') }} m{{ $loop->last ? '' : ' · ' }}
            @empty
              none
            @endforelse
          </div>
        </div>
        @if($receiving->items->sum('quantity_rejected') > 0)<div class="small text-muted mt-1">Rejected meters were not stocked — they stay with the mill for reprocessing.</div>@endif
        @if($receiving->qc_status)
          <div class="small mt-2"><strong>QC:</strong>
            @if($receiving->qcInspection)
              <a href="{{ route('qc_inspections.show', $receiving->qcInspection->id) }}">{{ $receiving->qcInspection->qc_no }}</a> — {{ $receiving->qcInspection->status }}
            @else
              <span class="badge bg-warning text-dark">Pending</span>
              @can('qc_inspections.create')
                <form method="POST" action="{{ route('qc_inspections.start', $receiving->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary ms-1">Start QC</button></form>
              @endcan
            @endif
          </div>
        @endif
      @endif

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