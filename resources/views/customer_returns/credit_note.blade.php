@extends('layouts.app')
@section('title', $note->credit_no)
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Credit Note {{ $note->credit_no }}</h2>
      <a href="{{ route('customer_returns.index') }}" class="btn btn-outline-primary">Back</a>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Customer:</strong> {{ $note->customer->name ?? '' }}</div>
        <div class="col-md-2"><strong>Date:</strong> {{ $note->credit_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Against:</strong> @if($note->invoice)<a href="{{ route('customer_invoices.show', $note->customer_invoice_id) }}">{{ $note->invoice->invoice_no }}</a>@else — @endif</div>
        <div class="col-md-4"><strong>Return:</strong> @if($note->return)<a href="{{ route('customer_returns.show', $note->customer_return_id) }}">{{ $note->return->return_no }}</a>@else — @endif</div>
      </div>
      <table class="table table-sm table-bordered" style="max-width:520px">
        <tr><td>Goods</td><td class="text-end">{{ $note->currency }} {{ number_format($note->subtotal, 2) }}</td></tr>
        @if($note->tax_amount > 0)<tr><td>Sales tax</td><td class="text-end">{{ number_format($note->tax_amount, 2) }}</td></tr>@endif
        <tr class="fw-bold"><td>Total credit</td><td class="text-end">{{ $note->currency }} {{ number_format($note->total, 2) }}@if($note->currency !== 'PKR')<div class="small fw-normal">@ {{ $note->exchange_rate }} = PKR {{ number_format($note->total_pkr, 2) }}</div>@endif</td></tr>
        <tr><td>On account (not applied)</td><td class="text-end">{{ number_format($note->unapplied, 2) }}</td></tr>
      </table>
      @if($note->applications->isNotEmpty())
        <h6>Applied to</h6>
        <ul class="small">@foreach($note->applications as $a)<li><a href="{{ route('customer_invoices.show', $a->customer_invoice_id) }}">{{ $a->invoice->invoice_no ?? '' }}</a> — {{ number_format($a->amount, 2) }}</li>@endforeach</ul>
      @endif
    </div>
  </section>
  @if($open->isNotEmpty())
  @can('customer_returns.create')
  <section class="card">
    <header class="card-header"><h2 class="card-title">Apply the rest to other invoices</h2></header>
    <div class="card-body">
      <form method="POST" action="{{ route('customer_returns.credit_note.apply', $note->id) }}">@csrf
        <table class="table table-sm table-bordered">
          <thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Balance</th><th style="width:170px">Apply</th></tr></thead>
          <tbody>
          @foreach($open as $k => $inv)
            <tr><td>{{ $inv->invoice_no }}<input type="hidden" name="rows[{{ $k }}][customer_invoice_id]" value="{{ $inv->id }}"></td><td>{{ $inv->invoice_date->format('d-M-Y') }}</td>
              <td class="text-end">{{ number_format($inv->balance, 2) }}</td><td><input type="number" step="0.01" min="0" max="{{ $inv->balance }}" name="rows[{{ $k }}][amount]" class="form-control form-control-sm"></td></tr>
          @endforeach
          </tbody>
        </table>
        <div class="text-end"><button class="btn btn-primary">Apply</button></div>
      </form>
    </div>
  </section>
  @endcan
  @endif
</div></div>
@endsection
