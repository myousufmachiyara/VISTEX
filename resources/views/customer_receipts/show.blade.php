@extends('layouts.app')
@section('title', $receipt->receipt_no)
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Receipt {{ $receipt->receipt_no }}</h2>
      <div class="d-flex gap-2">
        @can('customer_receipts.delete')
        <form method="POST" action="{{ route('customer_receipts.destroy', $receipt->id) }}" onsubmit="return confirm('Delete this receipt? Its posting is reversed and invoices reopen.')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
        @endcan
        <a href="{{ route('customer_receipts.index') }}" class="btn btn-outline-primary">Back</a>
      </div>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Customer:</strong> {{ $receipt->customer->name ?? '' }}</div>
        <div class="col-md-2"><strong>Date:</strong> {{ $receipt->receipt_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Into:</strong> {{ $receipt->account->name ?? '' }} · {{ \App\Models\CustomerReceipt::METHODS[$receipt->method] ?? $receipt->method }}{{ $receipt->reference ? ' ' . $receipt->reference : '' }}</div>
        <div class="col-md-4"><strong>Amount:</strong> {{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}@if($receipt->currency !== 'PKR') @ {{ $receipt->exchange_rate }} = PKR {{ number_format($receipt->amount_pkr, 2) }}@endif</div>
      </div>
      <table class="table table-sm table-bordered">
        <thead><tr><th>Invoice</th><th>Invoice date</th><th class="text-end">Allocated</th><th>Invoice status</th></tr></thead>
        <tbody>
        @forelse($receipt->allocations as $a)
          <tr><td><a href="{{ route('customer_invoices.show', $a->customer_invoice_id) }}">{{ $a->invoice->invoice_no ?? '' }}</a></td><td>{{ $a->invoice?->invoice_date?->format('d-M-Y') }}</td>
            <td class="text-end">{{ number_format($a->amount, 2) }}</td><td>{{ $a->invoice->status_label ?? '' }}</td></tr>
        @empty
          <tr><td colspan="4" class="text-muted">Not allocated to any invoice.</td></tr>
        @endforelse
        </tbody>
      </table>
      @if($receipt->unallocated > 0)<p class="small"><strong>On account:</strong> {{ $receipt->currency }} {{ number_format($receipt->unallocated, 2) }}</p>@endif
      @if(abs($receipt->exchange_difference) > 0.004)<p class="small"><strong>Exchange {{ $receipt->exchange_difference > 0 ? 'gain' : 'loss' }}:</strong> PKR {{ number_format(abs($receipt->exchange_difference), 2) }}</p>@endif
      @if($receipt->remarks)<p class="small"><strong>Remarks:</strong> {{ $receipt->remarks }}</p>@endif
    </div>
  </section>
</div></div>
@endsection
