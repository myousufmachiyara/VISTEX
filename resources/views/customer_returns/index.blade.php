@extends('layouts.app')
@section('title', 'Returns & Credit Notes')
@section('content')
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Customer Returns</h2>
      @can('customer_returns.create')<a href="{{ route('customer_returns.create') }}" class="btn btn-primary">New Return</a>@endcan
    </header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="retTable">
        <thead><tr><th>Date</th><th>Return #</th><th>Customer</th><th>Into</th><th class="text-end">Qty</th><th class="text-end">Cost back</th><th>Credit notes</th></tr></thead>
        <tbody>
        @foreach($returns as $r)
          <tr><td data-order="{{ $r->return_date->format('Y-m-d') }}">{{ $r->return_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('customer_returns.show', $r->id) }}" class="text-primary">{{ $r->return_no }}</a></td>
            <td>{{ $r->customer->name ?? '' }}</td><td>{{ $r->location->name ?? '' }}</td>
            <td class="text-end">{{ rtrim(rtrim(number_format($r->items->sum('quantity'), 3), '0'), '.') }}</td>
            <td class="text-end">{{ number_format($r->cost_amount, 2) }}</td>
            <td class="small">{{ $r->creditNotes->pluck('credit_no')->implode(', ') ?: '—' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
  <section class="card">
    <header class="card-header"><h2 class="card-title">Credit Notes</h2></header>
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-striped" id="cnTable">
        <thead><tr><th>Date</th><th>Credit #</th><th>Customer</th><th>Invoice</th><th class="text-end">Total</th><th class="text-end">On account</th></tr></thead>
        <tbody>
        @foreach($notes as $n)
          <tr><td data-order="{{ $n->credit_date->format('Y-m-d') }}">{{ $n->credit_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('customer_returns.credit_note', $n->id) }}" class="text-primary">{{ $n->credit_no }}</a></td>
            <td>{{ $n->customer->name ?? '' }}</td><td>{{ $n->invoice->invoice_no ?? '—' }}</td>
            <td class="text-end">{{ $n->currency }} {{ number_format($n->total, 2) }}</td>
            <td class="text-end">{{ $n->unapplied > 0 ? number_format($n->unapplied, 2) : '' }}</td></tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(() => { $('#retTable').DataTable({ pageLength: 25, order: [[0, 'desc']] }); $('#cnTable').DataTable({ pageLength: 25, order: [[0, 'desc']] }); });</script>
@endsection
