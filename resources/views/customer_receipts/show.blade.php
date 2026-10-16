@extends('layouts.app')
@section('title', $receipt->receipt_no)
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <section class="card mb-3">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Receipt {{ $receipt->receipt_no }} <span class="badge bg-{{ $receipt->status_badge }} ms-2">{{ $receipt->status === 'Pending' ? 'Post-dated cheque' : $receipt->status }}</span></h2>
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
        @if($receipt->cheque_date)<div class="col-md-3 mt-1"><strong>Cheque date:</strong> {{ $receipt->cheque_date->format('d-M-Y') }}</div>@endif
        @if($receipt->cleared_at && $receipt->method === 'pdc')<div class="col-md-3 mt-1"><strong>Cleared:</strong> {{ $receipt->cleared_at->format('d-M-Y') }}</div>@endif
        @if($receipt->status === 'Bounced')<div class="col-12 mt-1 text-danger"><strong>Bounced:</strong> {{ $receipt->bounce_reason }}</div>@endif
      </div>

      @if($receipt->status === 'Pending')
        @can('customer_receipts.edit')
        <div class="alert alert-warning d-flex flex-wrap gap-3 align-items-end">
          <div class="flex-grow-1">Held in <strong>Cheques in Hand</strong> until it clears into {{ $receipt->account->name ?? 'the bank' }}.</div>
          <form method="POST" action="{{ route('customer_receipts.clear', $receipt->id) }}" class="d-flex gap-2 align-items-end">@csrf
            <div><label class="small">Cleared on</label><input type="date" name="cleared_at" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"></div>
            <button class="btn btn-sm btn-success">Mark cleared</button></form>
          <form method="POST" action="{{ route('customer_receipts.bounce', $receipt->id) }}" class="d-flex gap-2 align-items-end" onsubmit="return confirm('Mark this cheque bounced? The receipt is reversed and its invoices reopen.')">@csrf
            <div><label class="small">Bounce reason</label><input name="reason" class="form-control form-control-sm" required></div>
            <button class="btn btn-sm btn-outline-danger">Bounced</button></form>
        </div>
        @endcan
      @endif

      <table class="table table-sm table-bordered">
        <thead><tr><th>Invoice</th><th>Invoice date</th><th class="text-end">Allocated</th><th class="text-end">Exchange diff (PKR)</th><th>Invoice status</th></tr></thead>
        <tbody>
        @forelse($receipt->allocations as $a)
          <tr><td><a href="{{ route('customer_invoices.show', $a->customer_invoice_id) }}">{{ $a->invoice->invoice_no ?? '' }}</a>@if($a->applied_later) <span class="badge bg-light text-dark">applied later</span>@endif</td><td>{{ $a->invoice?->invoice_date?->format('d-M-Y') }}</td>
            <td class="text-end">{{ number_format($a->amount, 2) }}</td><td class="text-end">{{ $a->exchange_difference ? number_format($a->exchange_difference, 2) : '' }}</td><td>{{ $a->invoice->status_label ?? '' }}</td></tr>
        @empty
          <tr><td colspan="5" class="text-muted">Not allocated to any invoice.</td></tr>
        @endforelse
        </tbody>
      </table>
      @if($receipt->unallocated > 0)<p class="small"><strong>On account:</strong> {{ $receipt->currency }} {{ number_format($receipt->unallocated, 2) }}</p>@endif
      @if(abs($receipt->exchange_difference) > 0.004)<p class="small"><strong>Exchange {{ $receipt->exchange_difference > 0 ? 'gain' : 'loss' }}:</strong> PKR {{ number_format(abs($receipt->exchange_difference), 2) }}</p>@endif
      @if($receipt->remarks)<p class="small"><strong>Remarks:</strong> {{ $receipt->remarks }}</p>@endif
    </div>
  </section>

  @if($open->isNotEmpty())
  @can('customer_receipts.create')
  <section class="card">
    <header class="card-header"><h2 class="card-title">Apply money on account ({{ $receipt->currency }} {{ number_format($receipt->unallocated, 2) }})</h2></header>
    <div class="card-body">
      <form method="POST" action="{{ route('customer_receipts.apply', $receipt->id) }}">@csrf
        <table class="table table-sm table-bordered">
          <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Balance</th><th style="width:170px">Apply</th></tr></thead>
          <tbody>
          @php
            $left = $receipt->unallocated;
          @endphp
          @foreach($open as $k => $inv)
            @php
              $suggest = round(min($left, $inv->balance), 2); $left = max(0, $left - $suggest);
            @endphp
            <tr><td><a href="{{ route('customer_invoices.show', $inv->id) }}">{{ $inv->invoice_no }}</a><input type="hidden" name="rows[{{ $k }}][customer_invoice_id]" value="{{ $inv->id }}"></td>
              <td>{{ $inv->invoice_date->format('d-M-Y') }}</td><td class="{{ $inv->isOverdue() ? 'text-danger' : '' }}">{{ $inv->due_date?->format('d-M-Y') }}</td>
              <td class="text-end">{{ number_format($inv->balance, 2) }}</td>
              <td><input type="number" step="0.01" min="0" max="{{ $inv->balance }}" name="rows[{{ $k }}][amount]" class="form-control form-control-sm" value="{{ $suggest > 0 ? number_format($suggest, 2, '.', '') : '' }}"></td></tr>
          @endforeach
          </tbody>
        </table>
        <div class="text-end"><button class="btn btn-primary">Apply</button></div>
        @if($receipt->currency !== 'PKR')<div class="small text-muted">Any difference between this receipt's rate and an invoice's rate is posted to exchange gain / loss.</div>@endif
      </form>
    </div>
  </section>
  @endcan
  @endif
</div></div>
@endsection
