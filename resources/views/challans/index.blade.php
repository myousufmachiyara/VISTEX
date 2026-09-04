@extends('layouts.app')
@section('title', 'Challans')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Challans</h2>
      @can('challans.create')<a href="{{ route('challans.create') }}" class="btn btn-primary">Log Challan</a>@endcan
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="AwaitingInspection" @selected(request('status')=='AwaitingInspection')>Awaiting Inspection</option>
            <option value="Processed" @selected(request('status')=='Processed')>Processed</option>
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="challanTable">
        <thead><tr><th>Date</th><th>Challan #</th><th>PO #</th><th>Category</th><th>Vendor</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($challans as $c)
          <tr>
            <td>{{ $c->received_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('challans.show', $c->id) }}" class="text-primary">{{ $c->challan_no }}</a></td>
            <td>{{ $c->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $c->purchaseOrder->category->name ?? '' }}</td>
            <td>{{ $c->purchaseOrder->vendor->name ?? '' }}</td>
            <td><span class="badge bg-{{ $c->status === 'Processed' ? 'success' : 'warning text-dark' }}">{{ $c->status === 'AwaitingInspection' ? 'Awaiting Inspection' : 'Processed' }}</span></td>
            <td><a href="{{ route('challans.show', $c->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#challanTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection