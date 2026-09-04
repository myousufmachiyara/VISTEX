@extends('layouts.app')
@section('title', 'Pending Challan Inspections')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Challans Awaiting Inspection</h2></header>
    <div class="card-body">
      @if($challans->isEmpty())
        <p class="text-muted">No challans currently awaiting inspection for your assigned categories.</p>
      @else
        <table class="table table-bordered table-striped">
          <thead><tr><th>Date</th><th>Challan #</th><th>PO #</th><th>Category</th><th>Vendor</th><th></th></tr></thead>
          <tbody>
            @foreach($challans as $c)
            <tr>
              <td>{{ $c->received_date->format('d-M-Y') }}</td>
              <td>{{ $c->challan_no }}</td>
              <td>{{ $c->purchaseOrder->order_no ?? '' }}</td>
              <td>{{ $c->purchaseOrder->category->name ?? '' }}</td>
              <td>{{ $c->purchaseOrder->vendor->name ?? '' }}</td>
              <td><a href="{{ route('challans.show', $c->id) }}" class="btn btn-sm btn-outline-primary">View / Print</a></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </section>
</div></div>
@endsection