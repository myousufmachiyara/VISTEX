@extends('layouts.app')
@section('title', 'Issuance')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Issuance</h2>
      @can('issuances.create')<a href="{{ route('issuances.create') }}" class="btn btn-primary">New Issuance</a>@endcan
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="type" class="form-control" onchange="this.form.submit()">
            <option value="">All Types</option>
            @foreach(\App\Models\Issuance::TYPES as $key => $t)
              <option value="{{ $key }}" @selected(request('type')==$key)>{{ $t['label'] }}</option>
            @endforeach
          </select>
        </div>
      </form>
      <table class="table table-bordered table-striped" id="issTable">
        <thead><tr><th>Date</th><th>Issue #</th><th>Type</th><th>Against</th><th>Issued To</th><th>From → To</th><th>Items</th><th class="text-end">Qty</th><th></th></tr></thead>
        <tbody>
          @foreach($issuances as $iss)
          <tr>
            <td data-order="{{ $iss->issue_date->format('Y-m-d') }}">{{ $iss->issue_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('issuances.show', $iss->id) }}" class="text-primary">{{ $iss->issue_no }}</a></td>
            <td>{{ $iss->type_label }}</td>
            <td>@if($iss->purchaseOrder)<a href="{{ route('purchase_orders.show', $iss->purchase_order_id) }}">{{ $iss->purchaseOrder->order_no }}</a>@endif</td>
            <td>{{ $iss->purchaseOrder->vendor->name ?? '' }}</td>
            <td class="small">{{ $iss->sourceLocation->name ?? '—' }} → {{ $iss->destinationLocation->name ?? 'Mill' }}</td>
            <td class="small">@foreach($iss->items as $it){{ $it->product->name ?? '' }}@if(!$loop->last), @endif @endforeach</td>
            <td class="text-end">{{ number_format($iss->total_quantity, 3) }}</td>
            <td class="text-nowrap">
              <a href="{{ route('issuances.print', $iss->id) }}" target="_blank" class="btn btn-sm btn-outline-success">Print</a>
              @can('issuances.edit')<a href="{{ route('issuances.edit', $iss->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endcan
              @can('issuances.delete')
              <form action="{{ route('issuances.destroy', $iss->id) }}" method="POST" class="d-inline">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Reverse and delete {{ $iss->issue_no }}? Stock and ledger entries will be undone.')">Delete</button>
              </form>
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#issTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection
