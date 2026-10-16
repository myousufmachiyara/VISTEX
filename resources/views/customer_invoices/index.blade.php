@extends('layouts.app')
@section('title', 'Customer Invoices')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Customer Invoices</h2>
      <div class="d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex gap-2">
          <select name="customer_id" class="form-control" onchange="this.form.submit()"><option value="">All customers</option>
            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
          <select name="status" class="form-control" onchange="this.form.submit()"><option value="">All</option>
            @foreach(\App\Models\CustomerInvoice::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach</select>
        </form>
        @can('customer_invoices.create')<a href="{{ route('customer_invoices.create') }}" class="btn btn-primary">New Invoice</a>@endcan
        @can('customer_receipts.create')<a href="{{ route('customer_receipts.create') }}" class="btn btn-outline-primary">Receive Payment</a>@endcan
      </div>
    </header>
    <div class="card-body">
      @php
        $open = $invoices->whereIn('status', ['Unpaid', 'PartiallyPaid']);
      @endphp
      <div class="row mb-3 small">
        <div class="col-md-4"><strong>Outstanding (PKR at invoice rates):</strong> {{ number_format($open->sum(fn($i) => $i->balance * $i->exchange_rate), 2) }}</div>
        <div class="col-md-4"><strong>Overdue invoices:</strong> {{ $open->filter(fn($i) => $i->isOverdue())->count() }}</div>
      </div>
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="invTable">
        <thead><tr><th>Date</th><th>Invoice #</th><th>Customer</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Received</th><th class="text-end">Balance</th><th class="text-end">PKR</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($invoices as $i)
          <tr>
            <td data-order="{{ $i->invoice_date->format('Y-m-d') }}">{{ $i->invoice_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('customer_invoices.show', $i->id) }}" class="text-primary">{{ $i->invoice_no }}</a></td>
            <td>{{ $i->customer->name ?? '' }}</td>
            <td class="{{ $i->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ $i->due_date?->format('d-M-Y') }}</td>
            <td class="text-end">{{ $i->currency }} {{ number_format($i->total, 2) }}</td>
            <td class="text-end">{{ number_format($i->paid_amount, 2) }}</td>
            <td class="text-end">{{ number_format($i->status === 'Cancelled' ? 0 : $i->balance, 2) }}</td>
            <td class="text-end">{{ number_format($i->total_pkr, 2) }}</td>
            <td><span class="badge bg-{{ $i->status_badge }}">{{ $i->status_label }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#invTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
