@extends('layouts.app')
@section('title', 'PO Objections')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header"><h2 class="card-title">PO Objections</h2></header>
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

      <table class="table table-bordered table-striped">
        <thead><tr><th>PO #</th><th>Vendor</th><th>Raised By</th><th>Remarks</th><th>Raised On</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($objections as $obj)
          <tr>
            <td><a href="{{ route('purchase_orders.show', $obj->purchase_order_id) }}" class="text-primary">{{ $obj->purchaseOrder->order_no ?? '' }}</a></td>
            <td>{{ $obj->purchaseOrder->vendor->name ?? '' }}</td>
            <td>{{ $obj->raisedBy->name ?? '' }}</td>
            <td class="small" style="white-space:pre-line">{{ $obj->remarks }}</td>
            <td>{{ $obj->created_at->format('d-M-Y H:i') }}</td>
            <td><span class="badge bg-{{ $obj->status === 'Open' ? 'danger' : 'success' }}">{{ $obj->status }}</span></td>
            <td>
              @if($obj->status === 'Open')
              <form action="{{ route('purchase_order_objections.resolve', $obj->id) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success" onclick="return confirm('Mark as resolved?')">Resolve</button>
              </form>
              @else
              <span class="text-muted small">by {{ $obj->resolvedBy->name ?? '' }}</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection