@extends('layouts.app')
@section('title', 'Receiving | ' . $receiving->receiving_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $receiving->receiving_no }}</h2>
      <span class="badge bg-{{ match($receiving->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">
        {{ $receiving->status === 'PendingApproval' ? 'Pending Approval' : $receiving->status }}
      </span>
    </header>

    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><strong>PO #:</strong> {{ $receiving->purchaseOrder->order_no ?? '' }}</div>
        <div class="col-md-3"><strong>Category:</strong> {{ $receiving->purchaseOrder->category->name ?? '' }}</div>
        <div class="col-md-3"><strong>Vendor:</strong> {{ $receiving->purchaseOrder->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>Location:</strong> {{ $receiving->location->name ?? '' }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-3"><strong>Receiving Date:</strong> {{ $receiving->receiving_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Vendor Challan #:</strong> {{ $receiving->vendor_challan_no }}</div>
        @if($receiving->status === 'Approved')
        <div class="col-md-3"><strong>Approved By:</strong> {{ $receiving->approver->name ?? '' }}</div>
        <div class="col-md-3"><strong>Approved At:</strong> {{ $receiving->approved_at?->format('d-M-Y H:i') }}</div>
        @endif
      </div>

      @if($receiving->status === 'Rejected')
      <div class="alert alert-danger">
        <strong>Rejection Reason:</strong> {{ $receiving->rejection_reason ?? '—' }}
      </div>
      @endif

      @if($receiving->remarks)
      <div class="mb-3"><strong>Remarks:</strong> {{ $receiving->remarks }}</div>
      @endif

      @if($receiving->attachments)
      <div class="mb-3">
        <strong>Attachments:</strong>
        @foreach($receiving->attachments as $path)
          <a href="{{ Storage::url($path) }}" target="_blank" class="me-2"><i class="fas fa-file"></i> File</a>
        @endforeach
      </div>
      @endif

      <table class="table table-bordered">
        <thead><tr><th>Product</th><th class="text-end">Quantity Received</th><th>Unit</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
          @foreach($receiving->items as $item)
          <tr>
            <td>{{ $item->product->name ?? '' }}</td>
            <td class="text-end">{{ number_format($item->quantity_received, 3) }}</td>
            <td>{{ $item->product->measurementUnit->shortcode ?? '' }}</td>
            <td class="text-end">{{ number_format($item->rate, 2) }}</td>
            <td class="text-end">{{ number_format($item->amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot class="fw-bold">
          <tr><td colspan="4" class="text-end">Total</td><td class="text-end">{{ number_format($receiving->amount, 2) }}</td></tr>
        </tfoot>
      </table>
    </div>

    <footer class="card-footer text-end">
      <a href="{{ route('purchase_receivings.print', $receiving->id) }}" target="_blank" class="btn btn-outline-success">Print</a>
      <a href="{{ route('purchase_receivings.index') }}" class="btn btn-outline-secondary">Back</a>
    </footer>
  </section>
</div></div>
@endsection