@extends('layouts.app')
@section('title', 'Purchase Receivings')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Purchase Receivings</h2></header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="PendingApproval" @selected(request('status')=='PendingApproval')>Pending Approval</option>
            <option value="Approved" @selected(request('status')=='Approved')>Approved</option>
            <option value="Rejected" @selected(request('status')=='Rejected')>Rejected</option>
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="grnTable">
        <thead><tr><th>Date</th><th>GRN #</th><th>PO #</th><th>Vendor</th><th>Challan #</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($receivings as $r)
          <tr>
            <td>{{ $r->receiving_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('purchase_receivings.show', $r->id) }}" class="text-primary">{{ $r->receiving_no }}</a></td>
            <td>{{ $r->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $r->purchaseOrder->vendor->name ?? '' }}</td>
            <td>{{ $r->challan->challan_no ?? '' }}</td>
            <td class="text-end">{{ number_format($r->amount, 2) }}</td>
            <td><span class="badge bg-{{ match($r->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $r->status === 'PendingApproval' ? 'Pending Approval' : $r->status }}</span></td>
            <td><a href="{{ route('purchase_receivings.show', $r->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#grnTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection