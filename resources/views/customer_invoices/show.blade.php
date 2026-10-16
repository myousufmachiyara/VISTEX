@extends('layouts.app')
@section('title', $invoice->invoice_no)
@section('content')
@php
  $q = fn($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
  $cur = $invoice->currency;
@endphp
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Invoice {{ $invoice->invoice_no }} <span class="badge bg-{{ $invoice->status_badge }} ms-2">{{ $invoice->status_label }}</span></h2>
      <div class="d-flex flex-wrap gap-2">
        @if(in_array($invoice->status, ['Unpaid', 'PartiallyPaid']))
          @can('customer_receipts.create')<a href="{{ route('customer_receipts.create', ['customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id]) }}" class="btn btn-success">Receive Payment</a>@endcan
        @endif
        @if($invoice->status === 'Unpaid')
          @can('customer_invoices.delete')<form method="POST" action="{{ route('customer_invoices.cancel', $invoice->id) }}" onsubmit="return confirm('Cancel this invoice? Its posting is reversed.')">@csrf<button class="btn btn-outline-danger">Cancel Invoice</button></form>@endcan
        @endif
        @can('customer_invoices.print')<a href="{{ route('customer_invoices.print', $invoice->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="fa fa-print"></i> Print</a>@endcan
        <a href="{{ route('customer_invoices.index') }}" class="btn btn-outline-primary">Back</a>
      </div>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Customer:</strong> {{ $invoice->customer->name ?? '' }}</div>
        <div class="col-md-2"><strong>Date:</strong> {{ $invoice->invoice_date->format('d-M-Y') }}</div>
        <div class="col-md-2 {{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}"><strong>Due:</strong> {{ $invoice->due_date?->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Currency:</strong> {{ $cur }}@if($cur !== 'PKR') @ {{ $invoice->exchange_rate }} = PKR {{ number_format($invoice->total_pkr, 2) }}@endif</div>
        <div class="col-md-2"><strong>By:</strong> {{ $invoice->creator->name ?? '' }}</div>
      </div>
      <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th>#</th><th>Item</th><th>Order · Delivery</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Disc %</th><th class="text-end">Amount</th><th class="text-end">Tax</th></tr></thead>
        <tbody>
        @foreach($invoice->items as $n => $it)
          <tr><td>{{ $n + 1 }}</td><td>{{ $it->product->name ?? '' }}@if($it->unit === 'm')<div class="small text-muted">{{ $q($it->delivered_qty) }} pcs</div>@endif</td><td class="small">{{ $it->description }}</td>
            <td class="text-end">{{ $q($it->quantity) }} {{ $it->unit }}</td><td class="text-end">{{ rtrim(rtrim(number_format($it->rate, 4), '0'), '.') }}</td>
            <td class="text-end">{{ $it->discount_pct ? $q($it->discount_pct) : '' }}</td><td class="text-end">{{ number_format($it->amount, 2) }}</td>
            <td class="text-end">{{ $it->tax_amount ? number_format($it->tax_amount, 2) : '' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="6" class="text-end">Subtotal</td><td class="text-end">{{ number_format($invoice->subtotal, 2) }}</td><td></td></tr>
          @if($invoice->tax_amount > 0)<tr><td colspan="6" class="text-end">Sales Tax</td><td class="text-end">{{ number_format($invoice->tax_amount, 2) }}</td><td></td></tr>@endif
          <tr class="fw-bold"><td colspan="6" class="text-end">Total {{ $cur }}</td><td class="text-end">{{ number_format($invoice->total, 2) }}</td><td></td></tr>
          <tr><td colspan="6" class="text-end">Received / credited</td><td class="text-end">{{ number_format($invoice->paid_amount, 2) }}</td><td></td></tr>
          <tr class="fw-bold"><td colspan="6" class="text-end">Balance</td><td class="text-end">{{ number_format($invoice->status === 'Cancelled' ? 0 : $invoice->balance, 2) }}</td><td></td></tr>
        </tfoot>
      </table>
      @if($invoice->allocations->isNotEmpty())
        <h6>Payments</h6>
        <ul class="small">
        @foreach($invoice->allocations as $a)<li><a href="{{ route('customer_receipts.show', $a->customer_receipt_id) }}">{{ $a->receipt->receipt_no ?? '' }}</a> — {{ $a->receipt?->receipt_date?->format('d-M-Y') }} — {{ $cur }} {{ number_format($a->amount, 2) }}</li>@endforeach
        </ul>
      @endif
      @if($invoice->creditNotes->isNotEmpty() || $invoice->creditApplications->isNotEmpty())
        <h6>Credit notes</h6>
        <ul class="small">
        @foreach($invoice->creditApplications as $c)<li><a href="{{ route('customer_returns.credit_note', $c->customer_credit_note_id) }}">{{ $c->creditNote->credit_no ?? '' }}</a> — {{ $cur }} {{ number_format($c->amount, 2) }} applied</li>@endforeach
        </ul>
      @endif
      @php
        $credits = in_array($invoice->status, ['Unpaid', 'PartiallyPaid']) ? collect(app(\App\Services\CustomerCreditService::class)->available($invoice->customer_id))->where('currency', $cur) : collect();
      @endphp
      @if($credits->isNotEmpty())
        <div class="alert alert-info small">This customer has credit on account that can settle this invoice:
          @foreach($credits as $c)
            <a href="{{ $c['type'] === 'receipt' ? route('customer_receipts.show', $c['id']) : route('customer_returns.credit_note', $c['id']) }}">{{ $c['no'] }}</a> ({{ $cur }} {{ number_format($c['amount'], 2) }}){{ $loop->last ? '' : ',' }}
          @endforeach
          — open it and use "Apply".
        </div>
      @endif
      @if($invoice->remarks)<p class="small"><strong>Remarks:</strong> {{ $invoice->remarks }}</p>@endif
    </div>
  </section>
</div></div>
@endsection
