@extends('layouts.app')
@section('title', 'Inspection Queue')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Challans Awaiting Your Inspection</h2></header>
    <div class="card-body">
      @if($challans->isEmpty())
        <p class="text-muted">Nothing waiting at the gate for your categories.</p>
      @else
        <table class="table table-bordered table-striped">
          <thead><tr><th>Date</th><th>Challan #</th><th>PO #</th><th>Category</th><th>Vendor</th><th>Gate</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @foreach($challans as $c)
            <tr>
              <td>{{ $c->received_date->format('d-M-Y') }}</td>
              <td>{{ $c->challan_no }}</td>
              <td>{{ $c->purchaseOrder->order_no ?? 'Without PO' }}</td>
              <td>{{ $c->purchaseOrder->category->name ?? ($c->category->name ?? '') }}</td>
              <td>{{ $c->display_vendor_name }}</td>
              <td>{{ $c->receivedBy->name ?? '' }} @if($c->has_objection)<span class="badge bg-danger">Objection</span>@endif</td>
              <td>
                <span class="badge bg-{{ $c->status_badge }}">{{ $c->status_label }}</span>
                @if($c->status === 'AwaitingAmendment' && $c->amendment)<br><small class="text-muted">Amendment #{{ $c->amendment->amendment_no }} pending approval</small>@endif
              </td>
              <td class="text-nowrap">
                @if($c->isAwaitingReview())
                  <a href="{{ $c->entry_type === 'po' ? route('challans.review_form', $c->id) : route('challans.review_direct_form', $c->id) }}" class="btn btn-sm btn-primary">Review</a>
                @endif
                <a href="{{ route('challans.show', $c->id) }}" class="btn btn-sm btn-outline-secondary">View</a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </section>
</div></div>
@endsection
