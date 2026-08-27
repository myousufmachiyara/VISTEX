@extends('layouts.app')
@section('title', 'Purchase Order Objections')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">PO Objections</h2>
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="Open" @selected(request('status')=='Open')>Open</option>
            <option value="Resolved" @selected(request('status')=='Resolved')>Resolved</option>
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="objTable">
        <thead>
          <tr><th>PO #</th><th>Vendor</th><th>Raised By</th><th>Remarks</th><th>Raised On</th><th>Status</th><th>Resolved By</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($objections as $obj)
          <tr>
            <td class="text-primary">{{ $obj->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $obj->purchaseOrder->vendor->name ?? '' }}</td>
            <td>{{ $obj->raisedBy->name ?? '' }}</td>
            <td class="small">{{ $obj->remarks }}</td>
            <td>{{ $obj->created_at->format('d-M-Y H:i') }}</td>
            <td><span class="badge bg-{{ $obj->status === 'Open' ? 'danger' : 'success' }}">{{ $obj->status }}</span></td>
            <td>{{ $obj->resolvedBy->name ?? '—' }}</td>
            <td>
              @can('purchase_orders.edit')
              @if($obj->status === 'Open')
              <a href="{{ route('purchase_orders.edit', $obj->purchase_order_id) }}" class="btn btn-sm btn-warning">Correct PO</a>
              @endif
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(function () { $('#objTable').DataTable({ pageLength: 50 }); });</script>
@endsection