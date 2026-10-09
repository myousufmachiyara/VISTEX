@extends('layouts.app')
@section('title', 'Challans')
@section('content')
@php($canEdit = auth()->user()->can('challans.edit'))
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Challans</h2>
      <div>
        <a href="{{ route('challans.pending') }}" class="btn btn-outline-warning">Inspection Queue</a>
        @can('challans.create')<a href="{{ route('challans.create') }}" class="btn btn-primary">Log Challan</a>@endcan
      </div>
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(\App\Models\Challan::STATUS_LABELS as $key => $label)
              <option value="{{ $key }}" @selected(request('status')==$key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="type" class="form-control" onchange="this.form.submit()">
            <option value="">PO &amp; Without PO</option>
            <option value="po" @selected(request('type')=='po')>Against PO</option>
            <option value="direct" @selected(request('type')=='direct')>Without PO</option>
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="challanTable">
        <thead><tr><th>Date</th><th>Challan #</th><th>PO #</th><th>Category</th><th>Vendor</th><th>Logged By</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($challans as $c)
          <tr>
            <td data-order="{{ $c->received_date->format('Y-m-d') }}">{{ $c->received_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('challans.show', $c->id) }}" class="text-primary">{{ $c->challan_no }}</a></td>
            <td>{{ $c->purchaseOrder->order_no ?? 'Without PO' }}</td>
            <td>{{ $c->purchaseOrder->category->name ?? ($c->category->name ?? '') }}</td>
            <td>{{ $c->display_vendor_name }}</td>
            <td>{{ $c->receivedBy->name ?? '' }}</td>
            <td>
              <span class="badge bg-{{ $c->status_badge }}">{{ $c->status_label }}</span>
              @if($c->has_objection)<span class="badge bg-danger" title="Objection raised at gate">!</span>@endif
            </td>
            <td class="text-nowrap">
              <a href="{{ route('challans.show', $c->id) }}" class="btn btn-sm btn-outline-primary">View</a>
              @if($canEdit && $c->canBeEditedBy(auth()->user()))
                <a href="{{ route('challans.edit', $c->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
              @endif
              @if($c->isAwaitingReview() && $c->canBeReviewedBy(auth()->user()))
                <a href="{{ $c->entry_type === 'po' ? route('challans.review_form', $c->id) : route('challans.review_direct_form', $c->id) }}" class="btn btn-sm btn-primary">Review</a>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#challanTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection
