@extends('layouts.app')
@section('title', $order->order_no)
@section('content')
@php($user = auth()->user())
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $order->order_no }} <small class="text-muted">({{ ucfirst($order->type) }} · Rev {{ $order->revision_no }})</small></h2>
      <div>
        @can('purchase_orders.index')
          @if(in_array($order->status, ['Approved','Issued','PartiallyReceived']))
          <a href="{{ route('purchase_order_objections.create', $order->id) }}" class="btn btn-sm btn-outline-danger">Report Objection</a>
          @endif
        @endcan
        <a href="{{ route('purchase_orders.print', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-success">Print</a>
        <span class="badge bg-{{ $order->status_badge }} ms-1">{{ $order->status_label }}</span>
      </div>
    </header>

    <div class="card-body">

      {{-- ── Lifecycle banners ─────────────────────────────────────── --}}
      @if($order->status === 'Draft')
      <div class="alert alert-secondary d-flex justify-content-between align-items-center">
        <span><strong>Draft.</strong> Only you can see this PO. Submit it when it is ready for approval.</span>
        <div>
          @if($order->canBeEditedBy($user))
            <a href="{{ route('purchase_orders.edit', $order->id) }}" class="btn btn-outline-primary btn-sm">Edit</a>
          @endif
          @if($order->canBeSubmittedBy($user))
          <form action="{{ route('purchase_orders.submit', $order->id) }}" method="POST" class="d-inline">
            @csrf<button class="btn btn-success btn-sm" onclick="return confirm('Submit this PO for approval?')">Submit for Approval</button>
          </form>
          @endif
        </div>
      </div>
      @endif

      @if($order->status === 'Pending')
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>
          Requested{{ $order->submitter ? ' by ' . $order->submitter->name : '' }}{{ $order->submitted_at ? ' on ' . $order->submitted_at->format('d-M-Y H:i') : '' }} — pending superadmin approval.
        </span>
        <div>
          @if($order->canBeRecalledBy($user) && !$order->canBeApprovedBy($user))
          <form action="{{ route('purchase_orders.recall', $order->id) }}" method="POST" class="d-inline">
            @csrf<button class="btn btn-outline-secondary btn-sm" onclick="return confirm('Move this PO back to Draft?')">Recall to Draft</button>
          </form>
          @endif
          @if($order->canBeApprovedBy($user))
          <form action="{{ route('purchase_orders.approve', $order->id) }}" method="POST" class="d-inline">
            @csrf<button class="btn btn-success btn-sm" onclick="return confirm('Approve this PO?')">Approve</button>
          </form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
          @endif
        </div>
      </div>
      @if($order->canBeApprovedBy($user))
      <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
          <form action="{{ route('purchase_orders.reject', $order->id) }}" method="POST">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reject {{ $order->order_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason — the creator will see this"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
          </form>
        </div></div>
      </div>
      @endif
      @endif

      @if($order->status === 'Rejected')
      <div class="alert alert-danger d-flex justify-content-between align-items-center">
        <span><strong>Rejected:</strong> {{ $order->rejection_reason }}</span>
        @if($order->canBeEditedBy($user))
          <a href="{{ route('purchase_orders.edit', $order->id) }}" class="btn btn-sm btn-outline-danger">Revise &amp; Resubmit</a>
        @endif
      </div>
      @endif

      @if($order->openObjections->isNotEmpty())
      <div class="alert alert-danger">
        <strong>Open objections:</strong>
        <ul class="mb-0">
          @foreach($order->openObjections as $obj)
            <li>{{ $obj->raisedBy->name ?? '' }}: {{ $obj->remarks }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      {{-- ── Header ────────────────────────────────────────────────── --}}
      <div class="row mb-3">
        <div class="col-md-3"><strong>Vendor:</strong> {{ $order->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>Category:</strong> {{ $order->category->name ?? '' }}</div>
        <div class="col-md-3"><strong>Drop Off:</strong> {{ $order->dropOffLocation->name ?? '' }}</div>
        <div class="col-md-3"><strong>Payment Term:</strong> {{ ucfirst($order->payment_term_type) }} {{ $order->payment_term_days ? "({$order->payment_term_days} days)" : '' }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-3"><strong>Order Date:</strong> {{ $order->order_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Expected:</strong> {{ $order->expected_date?->format('d-M-Y') ?? '—' }}</div>
        <div class="col-md-3"><strong>Created By:</strong> {{ $order->creator->name ?? '—' }}</div>
        <div class="col-md-3"><strong>Approved By:</strong> {{ $order->approver->name ?? '—' }}</div>
      </div>
      @if($order->broker)
      <div class="row mb-3">
        <div class="col-md-3"><strong>Broker:</strong> {{ $order->broker->name }}</div>
        <div class="col-md-3"><strong>Commission:</strong> {{ number_format($order->broker_commission_amount, 2) }}</div>
      </div>
      @endif

      @if(in_array($order->type, ['purchase', 'processing']))
        <table class="table table-bordered">
          <thead><tr><th>Item</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            @foreach($order->items as $item)
            <tr>
              <td>{{ $item->product->name ?? trim($item->pattern_code . ' ' . $item->description) }}</td>
              <td>{{ $item->measurementUnit->shortcode ?? '' }}</td>
              <td class="text-end">{{ number_format($item->quantity,3) }}</td>
              <td class="text-end">{{ number_format($item->quantity_received,3) }}</td>
              <td class="text-end">{{ number_format($item->outstanding_qty,3) }}</td>
              <td class="text-end">{{ number_format($item->rate,2) }}</td>
              <td class="text-end">{{ number_format($item->amount,2) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @elseif($order->type === 'weaving')
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>Item</td><td>{{ $order->greigeProduct->name ?? $order->item_name }}</td></tr>
            <tr><td>Warp / Weft Yarn</td><td>{{ $order->warpProduct->name ?? '' }} / {{ $order->weftProduct->name ?? '' }}</td></tr>
            <tr><td>Total Meters Required</td><td>{{ number_format($order->total_meters_required,3) }}</td></tr>
            <tr><td>Warp Required (lbs)</td><td>{{ number_format($order->warp_required_lbs, 4) }}</td></tr>
            <tr><td>Weft Required (lbs)</td><td>{{ number_format($order->weft_required_lbs, 4) }}</td></tr>
            <tr><td><strong>Total Yarn Required (lbs)</strong></td><td>{{ number_format($order->total_yarn_required, 4) }}</td></tr>
            <tr><td>Weaving Cost</td><td>{{ number_format($order->weaving_cost,2) }}</td></tr>
          </tbody>
        </table>
      @endif

      <div class="row mt-3 fw-bold">
        <div class="col-md-3 text-end offset-md-6">Subtotal: {{ number_format($order->subtotal,2) }}</div>
        <div class="col-md-3 text-end">Total: {{ number_format($order->total_amount,2) }}</div>
      </div>

      {{-- ── Issuances (weaving / processing) ───────────────────────── --}}
      @if(in_array($order->type, ['weaving','processing']) && !in_array($order->status, ['Draft','Pending','Rejected']))
      <hr>
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ $order->type === 'weaving' ? 'Yarn' : 'Greige' }} Issued Against This PO</h6>
        @can('issuances.create')
          @if(in_array($order->status, ['Approved','Issued','PartiallyReceived']))
          <a href="{{ route('issuances.create', ['type' => $order->type === 'weaving' ? 'yarn_weaving' : 'greige_processing', 'purchase_order_id' => $order->id]) }}" class="btn btn-sm btn-outline-primary">New Issuance</a>
          @endif
        @endcan
      </div>
      @if($order->issuances->isEmpty())
        <p class="text-muted mt-2">Nothing issued yet.</p>
      @else
        <table class="table table-sm table-bordered mt-2">
          <thead><tr><th>Issue #</th><th>Date</th><th>Items</th><th class="text-end">Qty</th></tr></thead>
          <tbody>
            @foreach($order->issuances as $iss)
            <tr>
              <td><a href="{{ route('issuances.show', $iss->id) }}">{{ $iss->issue_no }}</a></td>
              <td>{{ $iss->issue_date->format('d-M-Y') }}</td>
              <td>@foreach($iss->items as $it){{ $it->product->name ?? '' }} ({{ number_format($it->quantity,3) }})@if(!$loop->last), @endif @endforeach</td>
              <td class="text-end">{{ number_format($iss->total_quantity, 3) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @endif
      @endif

      {{-- ── Gate challans + receiving ───────────────────────────────── --}}
      @if($order->challans->isNotEmpty())
      <hr>
      <h6>Gate Challans &amp; Receiving</h6>
      <table class="table table-sm table-bordered">
        <thead><tr><th>Challan #</th><th>Date</th><th>Status</th><th>GRN</th><th class="text-end">GRN Amount</th></tr></thead>
        <tbody>
          @foreach($order->challans as $c)
          @php($grn = $order->receivings->firstWhere('challan_id', $c->id))
          <tr>
            <td><a href="{{ route('challans.show', $c->id) }}" class="text-primary">{{ $c->challan_no }}</a></td>
            <td>{{ $c->received_date->format('d-M-Y') }}</td>
            <td><span class="badge bg-{{ $c->status_badge }}">{{ $c->status_label }}</span></td>
            <td>@if($grn)<a href="{{ route('purchase_receivings.show', $grn->id) }}">{{ $grn->receiving_no }}</a>@else — @endif</td>
            <td class="text-end">{{ $grn ? number_format($grn->amount, 2) : '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif

      {{-- ── Amendments ─────────────────────────────────────────────── --}}
      @if($order->amendments->isNotEmpty())
      <hr>
      <h6>Amendments</h6>
      <table class="table table-sm table-bordered">
        <thead><tr><th>#</th><th>Requested By</th><th>Reason</th><th>Changes</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($order->amendments as $a)
          <tr>
            <td>{{ $a->amendment_no }}</td>
            <td>{{ $a->requestedBy->name ?? '' }}<br><small class="text-muted">{{ $a->created_at->format('d-M-Y') }}</small></td>
            <td>{{ $a->reason }}</td>
            <td class="small">
              @foreach($a->change_lines as $line)<div>{{ $line }}</div>@endforeach
            </td>
            <td><span class="badge bg-{{ match($a->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $a->status }}</span>
              @if($a->rejection_reason)<br><small>{{ $a->rejection_reason }}</small>@endif
            </td>
            <td class="text-nowrap">
              @if($a->status === 'Pending' && $user->hasRole('superadmin'))
                <form action="{{ route('purchase_order_amendments.approve', $a->id) }}" method="POST" class="d-inline">
                  @csrf<button class="btn btn-sm btn-success" onclick="return confirm('Approve and apply this amendment?')">Approve</button>
                </form>
                <form action="{{ route('purchase_order_amendments.reject', $a->id) }}" method="POST" class="d-inline" onsubmit="const r=prompt('Reason for rejecting?'); if(!r) return false; this.reason.value=r;">
                  @csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-outline-danger">Reject</button>
                </form>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif

      @if(in_array($order->status, ['Approved', 'Issued', 'PartiallyReceived']))
      <div class="alert alert-info d-flex justify-content-between align-items-center mt-3">
        <span>
          @if($order->type !== 'purchase' && $order->status === 'Approved')
            Issue {{ $order->type === 'weaving' ? 'yarn' : 'greige' }} to the mill first. The PO becomes receivable at the gate once material is issued.
          @else
            This PO is open at the gate. The gatekeeper logs a challan on arrival; the category incharge then reviews it.
          @endif
        </span>
        @can('challans.create')
          @if($order->type === 'purchase' || $order->status !== 'Approved')
          <a href="{{ route('challans.create') }}?purchase_order_id={{ $order->id }}" class="btn btn-sm btn-primary">Log Challan</a>
          @endif
        @endcan
      </div>
      @endif
    </div>

    <footer class="card-footer text-end">
      <a href="{{ route('purchase_orders.index') }}" class="btn btn-outline-secondary">Back</a>
    </footer>
  </section>
</div></div>
@endsection
