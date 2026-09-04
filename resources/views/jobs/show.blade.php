@extends('layouts.app')
@section('title', $job->job_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $job->job_no }}</h2>
      <span class="badge bg-{{ match($job->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $job->status }}</span>
    </header>
    <div class="card-body">
      @if($job->status === 'Pending' && $job->canBeApprovedBy(auth()->user()))
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>Pending superadmin approval.</span>
        <div>
          <form action="{{ route('jobs.approve', $job->id) }}" method="POST" class="d-inline">@csrf<button class="btn btn-success btn-sm">Approve</button></form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        </div>
      </div>
      <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form action="{{ route('jobs.reject', $job->id) }}" method="POST">@csrf
          <div class="modal-header"><h5>Reject {{ $job->job_no }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
        </form>
      </div></div></div>
      @endif
      @if($job->status === 'Rejected')<div class="alert alert-danger"><strong>Rejected:</strong> {{ $job->rejection_reason }}</div>@endif

      <div class="row mb-3">
        <div class="col-md-3"><strong>Customer:</strong> {{ $job->customer->name ?? '' }}</div>
        <div class="col-md-3"><strong>Buyer:</strong> {{ $job->buyer_name ?? '—' }}</div>
        <div class="col-md-3"><strong>Customer PO#:</strong> {{ $job->customer_po_number ?? '—' }}</div>
        <div class="col-md-3"><strong>Order Ref:</strong> {{ $job->customer_reference ?? '—' }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-3"><strong>Order Date:</strong> {{ $job->order_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Expected Delivery:</strong> {{ $job->expected_date?->format('d-M-Y') ?? '—' }}</div>
        <div class="col-md-3"><strong>Payment Term:</strong> {{ ucfirst($job->payment_term_type) }} {{ $job->payment_term_days ? "({$job->payment_term_days}d)" : '' }}</div>
        <div class="col-md-3"><strong>Shipping:</strong> {{ $job->shipping_address ?? '—' }}</div>
      </div>

      <table class="table table-bordered">
        <thead><tr><th>SKU</th><th>Description</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Rate</th><th class="text-end">Disc%</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
          @foreach($job->items as $item)
          <tr>
            <td>{{ $item->product->sku ?? '' }}</td>
            <td>{{ $item->product->name ?? '' }}</td>
            <td class="text-end">{{ number_format($item->quantity,3) }}</td>
            <td>{{ $item->measurementUnit->shortcode ?? '' }}</td>
            <td class="text-end">{{ number_format($item->unit_price,4) }}</td>
            <td class="text-end">{{ number_format($item->discount_pct,2) }}</td>
            <td class="text-end">{{ number_format($item->amount,2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div class="text-end fw-bold">Grand Total: {{ number_format($job->total_amount,2) }}</div>
    </div>
    <footer class="card-footer text-end"><a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary">Back</a></footer>
  </section>
</div></div>
@endsection