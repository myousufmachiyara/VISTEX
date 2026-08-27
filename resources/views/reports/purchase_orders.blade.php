{{-- reports/purchase_orders.blade.php --}}
@extends('layouts.app')
@section('title', 'Purchase Order Report')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Purchase Order Report</h2></header>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><div class="card p-2 text-center"><small>Total Ordered Value</small><h5>{{ number_format($summary['total_ordered_value'],2) }}</h5></div></div>
        <div class="col-md-3"><div class="card p-2 text-center"><small>Fully Received</small><h5>{{ $summary['fully_received'] }}</h5></div></div>
        <div class="col-md-3"><div class="card p-2 text-center"><small>Partially Received</small><h5>{{ $summary['partially_received'] }}</h5></div></div>
        <div class="col-md-3"><div class="card p-2 text-center"><small>Pending</small><h5>{{ $summary['pending'] }}</h5></div></div>
      </div>
      <table class="table table-bordered table-sm">
        <thead><tr><th>PO #</th><th>Date</th><th>Category</th><th>Vendor</th><th class="text-end">Ordered Qty</th><th class="text-end">Received Qty</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($orders as $o)
          <tr>
            <td>{{ $o->order_no }}</td><td>{{ $o->order_date->format('d-M-Y') }}</td><td>{{ $o->category->name ?? '' }}</td><td>{{ $o->vendor->name ?? '' }}</td>
            <td class="text-end">{{ number_format($o->quantity_ordered,3) }}</td><td class="text-end">{{ number_format($o->quantity_received,3) }}</td>
            <td class="text-end">{{ number_format($o->total_amount,2) }}</td><td>{{ $o->status }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection