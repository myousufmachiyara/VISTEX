@extends('layouts.app')
@section('title', $job->job_no)
@section('content')
@php
  $badge = match($job->status) { 'Approved' => 'success', 'Rejected' => 'danger', default => 'warning text-dark' };
  $cur = $job->currency ?? 'PKR';
  $pcsTotal = $job->items->sum(fn($i) => $i->pieces ?? 0);
@endphp
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Customer Order {{ $job->job_no }}</h2>
      <span class="badge bg-{{ $badge }}">{{ $job->status }}</span>
    </header>
    <div class="card-body">
      @if($job->status === 'Pending' && $job->canBeApprovedBy(auth()->user()))
        <div class="alert alert-warning d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <span>Pending superadmin approval.</span>
          <div>
            <form action="{{ route('jobs.approve', $job->id) }}" method="POST" class="d-inline">@csrf<button class="btn btn-success btn-sm">Approve</button></form>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
          </div>
        </div>
        <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
          <form action="{{ route('jobs.reject', $job->id) }}" method="POST">@csrf
            <div class="modal-header"><h5 class="modal-title">Reject {{ $job->job_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
          </form>
        </div></div></div>
      @endif
      @if($job->status === 'Rejected')<div class="alert alert-danger"><strong>Rejected:</strong> {{ $job->rejection_reason }}</div>@endif

      <div class="row mb-2">
        <div class="col-md-3 mb-2"><strong>Customer:</strong> {{ $job->customer->name ?? '' }}</div>
        <div class="col-md-3 mb-2"><strong>Customer PO #:</strong> {{ $job->customer_po_number ?? '—' }}</div>
        <div class="col-md-3 mb-2"><strong>Order Ref:</strong> {{ $job->customer_reference ?? '—' }}</div>
        <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $job->buyer_name ?? '—' }}</div>
        <div class="col-md-3 mb-2"><strong>Order Date:</strong> {{ $job->order_date->format('d-M-Y') }}</div>
        <div class="col-md-3 mb-2"><strong>Delivery Date:</strong> {{ $job->expected_date?->format('d-M-Y') ?? '—' }}</div>
        <div class="col-md-3 mb-2"><strong>Payment:</strong> {{ ucfirst($job->payment_term_type) }}{{ $job->payment_term_days ? " ({$job->payment_term_days} days)" : '' }}{{ $job->payment_term_note ? ' — ' . $job->payment_term_note : '' }}</div>
        <div class="col-md-3 mb-2"><strong>Currency:</strong> {{ $cur }}@if($cur !== 'PKR') @ {{ rtrim(rtrim(number_format($job->exchange_rate, 4), '0'), '.') }}@endif</div>
        <div class="col-md-12 mb-2"><strong>Ship to:</strong> {{ $job->consignee_name }}{{ $job->consignee_name && $job->shipping_address ? ', ' : '' }}{{ $job->shipping_address ?: ($job->consignee_name ? '' : '—') }}</div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>#</th><th>Collection</th><th>Pattern #</th><th>Design</th><th>Colour Scheme</th><th>Packaging</th>
              <th class="text-end">Qty (m)</th><th class="text-end">Pcs</th><th class="text-end">Rate</th><th class="text-end">Disc %</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr>
          </thead>
          <tbody>
            @foreach($job->items as $n => $item)
            <tr>
              <td>{{ $n + 1 }}</td>
              <td>{{ $item->collection }}</td>
              <td class="font-monospace">{{ $item->pattern_code }}</td>
              <td>{{ $item->design }}</td>
              <td>{{ $item->color_scheme }}</td>
              <td>{{ $item->product->name ?? '—' }}</td>
              <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
              <td class="text-end">{{ $item->pieces !== null ? number_format($item->pieces) : '' }}</td>
              <td class="text-end">{{ rtrim(rtrim(number_format($item->unit_price, 4), '0'), '.') }}</td>
              <td class="text-end">{{ (float) $item->discount_pct ? number_format($item->discount_pct, 2) : '' }}</td>
              <td class="text-end">{{ (float) $item->tax_amount ? number_format($item->tax_amount, 2) : '' }}</td>
              <td class="text-end">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot class="fw-bold">
            <tr><td colspan="6" class="text-end">Total</td><td class="text-end">{{ number_format($job->total_meters, 2) }}</td><td class="text-end">{{ $pcsTotal ? number_format($pcsTotal) : '' }}</td><td colspan="3"></td><td class="text-end">{{ $cur }} {{ number_format($job->total_amount, 2) }}</td></tr>
          </tfoot>
        </table>
      </div>

      <div class="row">
        <div class="col-md-7">
          @if($job->terms->isNotEmpty())
            <h6 class="mt-2">Terms &amp; Conditions</h6>
            <ol class="small ps-3">@foreach($job->terms as $t)<li><strong>{{ $t->title }}</strong>@if($t->description)<div class="text-muted" style="white-space:pre-line">{{ $t->description }}</div>@endif</li>@endforeach</ol>
          @endif
          @if($job->remarks)<p class="small"><strong>Remarks:</strong> {{ $job->remarks }}</p>@endif
          @foreach($job->attachments ?? [] as $file)
            <div class="small"><i class="fa fa-paperclip"></i> <a href="{{ \App\Support\Media::url($file) }}" target="_blank">{{ basename($file) }}</a></div>
          @endforeach
        </div>
        <div class="col-md-5">
          <table class="table table-sm mb-0">
            <tr><td>Gross</td><td class="text-end">{{ $cur }} {{ number_format($job->subtotal, 2) }}</td></tr>
            @if((float) $job->discount_amount)<tr><td>Discount</td><td class="text-end">− {{ number_format($job->discount_amount, 2) }}</td></tr>@endif
            @if((float) $job->tax_amount)<tr><td>Tax</td><td class="text-end">{{ number_format($job->tax_amount, 2) }}</td></tr>@endif
            <tr class="fw-bold"><td>Order Total</td><td class="text-end">{{ $cur }} {{ number_format($job->total_amount, 2) }}</td></tr>
            @if($cur !== 'PKR')<tr><td>In PKR</td><td class="text-end">Rs {{ number_format($job->total_pkr) }}</td></tr>@endif
          </table>
          @if($job->approver)<p class="small text-muted mt-2 mb-0">Approved by {{ $job->approver->name }}, {{ $job->approved_at?->format('d-M-Y H:i') }}</p>@endif
        </div>
      </div>
    </div>
    <footer class="card-footer d-flex flex-wrap gap-2 justify-content-between">
      <div>
        @if($job->isEditable())
          @can('jobs.edit')<a href="{{ route('jobs.edit', $job->id) }}" class="btn btn-outline-primary">Edit</a>@endcan
        @endif
        @if($job->status === 'Pending')
          @can('jobs.delete')
            <form action="{{ route('jobs.destroy', $job->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete {{ $job->job_no }}?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
          @endcan
        @endif
      </div>
      <div>
        @can('jobs.print')<a href="{{ route('jobs.print', $job->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="fa fa-print"></i> Print</a>@endcan
        <a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary">Back</a>
      </div>
    </footer>
  </section>
</div></div>
@endsection
