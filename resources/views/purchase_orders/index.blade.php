@extends('layouts.app')
@section('title', 'Purchase Orders')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Purchase Orders</h2>
      @can('purchase_orders.create')
      <a href="{{ route('purchase_orders.create') }}" class="btn btn-primary">New PO</a>
      @endcan
    </header>

    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['Pending','PartiallyReceived','Received'] as $s)
              <option value="{{ $s }}" @selected(request('status')==$s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped" id="poTable">
          <thead>
            <tr>
              <th>#</th><th>Date</th><th>PO #</th><th>Category</th><th>Vendor</th>
              <th>From → Drop Off</th><th class="text-end">Total</th><th>Status</th><th width="18%">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($orders as $i => $order)
            <tr>
              <td>{{ $i+1 }}</td>
              <td>{{ $order->order_date->format('d-M-Y') }}</td>
              <td class="text-primary">{{ $order->order_no }} <small class="text-muted">(Rev {{ $order->revision_no }})</small></td>
              <td>{{ $order->category->name ?? '' }}</td>
              <td>{{ $order->vendor->name ?? '' }}</td>
              <td class="small">{{ $order->fromLocation->name ?? '—' }} → {{ $order->dropOffLocation->name ?? '' }}</td>
              <td class="text-end">{{ number_format($order->total_amount, 2) }}</td>
              <td>
                <span class="badge bg-{{ match($order->status){'Received'=>'success','PartiallyReceived'=>'warning text-dark',default=>'info text-dark'} }}">
                  {{ $order->status }}
                </span>
              </td>
              <td>
                <a href="{{ route('purchase_orders.show', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-warning me-1">Show</a>
                <a href="{{ route('purchase_orders.print', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-success me-1">Print</a>
                @can('challans.create')
                  @if(in_array($order->status, ['Approved','Issued']))
                    <a href="{{ route('challans.create') }}?purchase_order_id={{ $order->id }}" class="btn btn-sm btn-outline-success me-1">Log Challan</a>
                  @endif
                @endcan
                @can('purchase_orders.edit')
                @if($order->canBeEditedBy(auth()->user()) && $order->status === 'Pending')
                <a href="{{ route('purchase_orders.edit', $order->id) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                @endif
                @endcan
                @can('purchase_orders.delete')
                @if($order->canBeEditedBy(auth()->user()) && $order->status === 'Pending')
                <form action="{{ route('purchase_orders.destroy', $order->id) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
                </form>
                @endif
                @endcan
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div></div>
<script>$(document).ready(function(){ $('#poTable').DataTable({ pageLength: 50, order: [[0,'desc']] }); });</script>
@endsection