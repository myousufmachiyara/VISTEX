@extends('layouts.app')
@section('title', $order->order_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $order->order_no }} <small class="text-muted">({{ ucfirst($order->type) }})</small></h2>
      <div>
        @can('purchase_orders.index')
          @if(in_array($order->status, ['Approved','Issued','PartiallyReceived']))
          <a href="{{ route('purchase_order_objections.create', $order->id) }}" class="btn btn-sm btn-outline-danger mb-3">Report Objection</a>
          @endif
        @endcan
        <span class="badge bg-{{ match($order->status){'Approved','Received'=>'success','Rejected'=>'danger','Issued'=>'info',default=>'warning text-dark'} }}">{{ $order->status }}</span>
      </div>
      </header>

    <div class="card-body">
      @if($order->status === 'Pending' && $order->canBeApprovedBy(auth()->user()))
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>This PO is pending superadmin approval.</span>
        <div>
          <form action="{{ route('purchase_orders.approve', $order->id) }}" method="POST" class="d-inline">
            @csrf<button class="btn btn-success btn-sm" onclick="return confirm('Approve this PO?')">Approve</button>
          </form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        </div>
      </div>
      <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
          <form action="{{ route('purchase_orders.reject', $order->id) }}" method="POST">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reject {{ $order->order_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
          </form>
        </div></div>
      </div>
      @endif

      @if($order->status === 'Rejected')
      <div class="alert alert-danger"><strong>Rejected:</strong> {{ $order->rejection_reason }}</div>
      @endif

      <div class="row mb-3">
        <div class="col-md-3"><strong>Vendor:</strong> {{ $order->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>Category:</strong> {{ $order->category->name ?? '' }}</div>
        <div class="col-md-3"><strong>Drop Off:</strong> {{ $order->dropOffLocation->name ?? '' }}</div>
        <div class="col-md-3"><strong>Payment Term:</strong> {{ ucfirst($order->payment_term_type) }} {{ $order->payment_term_days ? "({$order->payment_term_days} days)" : '' }}</div>
      </div>
      @if($order->broker)
      <div class="row mb-3">
        <div class="col-md-3"><strong>Broker:</strong> {{ $order->broker->name }}</div>
        <div class="col-md-3"><strong>Commission:</strong> {{ $order->broker_commission_value }} {{ $order->broker_commission_type === 'percentage' ? '%' : 'flat' }} = {{ number_format($order->broker_commission_amount, 2) }}</div>
      </div>
      @endif

      @if($order->type === 'purchase')
        <table class="table table-bordered">
          <thead><tr><th>Product</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
            @foreach($order->items as $item)
            <tr><td>{{ $item->product->name ?? '' }}</td><td>{{ $item->measurementUnit->shortcode ?? '' }}</td>
                <td class="text-end">{{ number_format($item->quantity,3) }}</td><td class="text-end">{{ number_format($item->rate,2) }}</td><td class="text-end">{{ number_format($item->amount,2) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @elseif($order->type === 'weaving')
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>Item</td><td>{{ $order->item_name }}</td></tr>
            <tr><td>Warp / Weft Yarn</td><td>{{ $order->warpProduct->name ?? '' }} / {{ $order->weftProduct->name ?? '' }}</td></tr>
            <tr><td>Total Meters Required</td><td>{{ number_format($order->total_meters_required,3) }}</td></tr>
            <tr><td><strong>Total Yarn Required (lbs)</strong></td><td>{{ number_format($order->total_yarn_weight_consumed,0) }}</td></tr>
            <tr><td>Weaving Cost</td><td>{{ number_format($order->weaving_cost,2) }}</td></tr>
          </tbody>
        </table>
      @endif

      <div class="row mt-3 fw-bold">
        <div class="col-md-3 text-end offset-md-6">Subtotal: {{ number_format($order->subtotal,2) }}</div>
        <div class="col-md-3 text-end">Total: {{ number_format($order->total_amount,2) }}</div>
      </div>

      @if($order->type === 'weaving' && $order->status === 'Approved')
      <hr>
      <h6>Yarn Issued Against This PO</h6>
      @if($order->yarnIssues->isEmpty())
        <p class="text-muted">None yet.</p>
      @else
        <table class="table table-sm table-bordered">
          <thead><tr><th>Issue #</th><th>Date</th><th>Items</th></tr></thead>
          <tbody>
            @foreach($order->yarnIssues as $yi)
            <tr><td>{{ $yi->issue_no }}</td><td>{{ $yi->issue_date->format('d-M-Y') }}</td>
                <td>@foreach($yi->items as $it){{ $it->product->name ?? '' }} ({{ $it->quantity }})@if(!$loop->last), @endif @endforeach</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif
      @endif

      <hr>
        <h6>Receiving History</h6>
        @if($order->receivings->isEmpty())
          <p class="text-muted">No receivings yet against this PO.</p>
        @else
          <table class="table table-sm table-bordered">
            <thead><tr><th>GRN #</th><th>Challan #</th><th>Date</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @foreach($order->receivings as $r)
              <tr>
                <td><a href="{{ route('purchase_receivings.show', $r->id) }}" class="text-primary">{{ $r->receiving_no }}</a></td>
                <td>{{ $r->challan->challan_no ?? '' }}</td>
                <td>{{ $r->receiving_date->format('d-M-Y') }}</td>
                <td class="text-end">{{ number_format($r->amount, 2) }}</td>
                <td><span class="badge bg-{{ match($r->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $r->status === 'PendingApproval' ? 'Pending' : $r->status }}</span></td>
                <td></td>
              </tr>
              @endforeach
            </tbody>
          </table>
        @endif

        @if(in_array($order->status, ['Approved', 'Issued', 'PartiallyReceived']))
        <div class="alert alert-info d-flex justify-content-between align-items-center mt-2">
          <span>
            @if($order->status === 'PartiallyReceived')
              This PO still has outstanding quantity to receive.
            @else
              Ready to receive — log a challan first when goods arrive.
            @endif
          </span>
          @can('challans.create')
          <a href="{{ route('challans.create') }}" class="btn btn-sm btn-primary">Log Challan for This PO</a>
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