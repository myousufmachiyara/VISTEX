{{-- stock_movements/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Stock Movements')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Stock Movements</h2>
      @can('stock_movements.create')<a href="{{ route('stock_movements.create') }}" class="btn btn-primary">New Movement</a>@endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="smTable">
        <thead><tr><th>Date</th><th>Movement #</th><th>Type</th><th>From</th><th>To</th><th>Lot #</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($movements as $m)
          <tr>
            <td>{{ $m->movement_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('stock_movements.show', $m->id) }}" class="text-primary">{{ $m->movement_no }}</a></td>
            <td>{{ \App\Models\StockMovement::TYPES[$m->movement_type] ?? $m->movement_type }}</td>
            <td>{{ $m->fromLocation->name ?? '' }}</td>
            <td>{{ $m->toLocation->name ?? '' }}</td>
            <td>{{ $m->lot_no ?? '—' }}</td>
            <td><span class="badge bg-{{ match($m->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $m->status === 'PendingApproval' ? 'Pending' : $m->status }}</span></td>
            <td><a href="{{ route('stock_movements.show', $m->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#smTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection