@extends('layouts.app')
@section('title', 'Purchase Returns')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Purchase Returns</h2></header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Date</th><th>Return #</th><th>GRN #</th><th>Vendor</th><th>Items Returned</th><th></th></tr></thead>
        <tbody>
          @foreach($returns as $ret)
          <tr>
            <td>{{ $ret->return_date->format('d-M-Y') }}</td>
            <td>{{ $ret->return_no }}</td>
            <td>{{ $ret->purchaseReceiving->receiving_no ?? '' }}</td>
            <td>{{ $ret->purchaseReceiving->purchaseOrder->vendor->name ?? '' }}</td>
            <td class="small">@foreach($ret->items as $it){{ $it->purchaseReceivingItem->product->name ?? '' }} ({{ $it->quantity_returned }})@if(!$loop->last), @endif @endforeach</td>
            <td><a href="{{ route('purchase_returns.edit', $ret->id) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection