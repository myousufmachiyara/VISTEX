@extends('layouts.app')
@section('title', 'Customer Receipts')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Customer Receipts</h2>
      @can('customer_receipts.create')<a href="{{ route('customer_receipts.create') }}" class="btn btn-primary">Receive Payment</a>@endcan
    </header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="rcvTable">
        <thead><tr><th>Date</th><th>Receipt #</th><th>Customer</th><th>Into</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">PKR</th><th class="text-end">On account</th></tr></thead>
        <tbody>
        @foreach($receipts as $r)
          <tr>
            <td data-order="{{ $r->receipt_date->format('Y-m-d') }}">{{ $r->receipt_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('customer_receipts.show', $r->id) }}" class="text-primary">{{ $r->receipt_no }}</a></td>
            <td>{{ $r->customer->name ?? '' }}</td><td>{{ $r->account->name ?? '' }}</td>
            <td>{{ \App\Models\CustomerReceipt::METHODS[$r->method] ?? $r->method }}{{ $r->reference ? ' · ' . $r->reference : '' }}</td>
            <td class="text-end">{{ $r->currency }} {{ number_format($r->amount, 2) }}</td>
            <td class="text-end">{{ number_format($r->amount_pkr, 2) }}</td>
            <td class="text-end">{{ $r->unallocated > 0 ? number_format($r->unallocated, 2) : '' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => $('#rcvTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }));</script>
@endsection
