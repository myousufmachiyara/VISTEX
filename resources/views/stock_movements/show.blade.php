{{-- stock_movements/show.blade.php --}}
@extends('layouts.app')
@section('title', $movement->movement_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $movement->movement_no }}</h2>
      <span class="badge bg-{{ match($movement->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $movement->status === 'PendingApproval' ? 'Pending Approval' : $movement->status }}</span>
    </header>
    <div class="card-body">
      @if($movement->status === 'PendingApproval' && $movement->canBeApprovedBy(auth()->user()))
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>Pending your approval.</span>
        <div>
          <form action="{{ route('stock_movements.approve', $movement->id) }}" method="POST" class="d-inline">@csrf<button class="btn btn-success btn-sm">Approve</button></form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        </div>
      </div>
      <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form action="{{ route('stock_movements.reject', $movement->id) }}" method="POST">@csrf
          <div class="modal-header"><h5>Reject</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required></textarea></div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm</button></div>
        </form>
      </div></div></div>
      @endif
      @if($movement->status === 'Rejected')<div class="alert alert-danger">{{ $movement->rejection_reason }}</div>@endif

      <div class="row mb-3">
        <div class="col-md-3"><strong>Type:</strong> {{ \App\Models\StockMovement::TYPES[$movement->movement_type] ?? '' }}</div>
        <div class="col-md-3"><strong>From:</strong> {{ $movement->fromLocation->name ?? '' }}</div>
        <div class="col-md-3"><strong>To:</strong> {{ $movement->toLocation->name ?? '' }}</div>
        <div class="col-md-3"><strong>Lot #:</strong> {{ $movement->lot_no ?? '—' }}</div>
      </div>
      <table class="table table-bordered">
        <thead><tr><th>Product</th><th class="text-end">Quantity</th><th class="text-end">Value</th></tr></thead>
        <tbody>
          @foreach($movement->items as $item)
          <tr><td>{{ $item->product->name ?? '' }}</td><td class="text-end">{{ number_format($item->quantity,3) }}</td><td class="text-end">{{ number_format($item->amount,2) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <footer class="card-footer text-end"><a href="{{ route('stock_movements.index') }}" class="btn btn-outline-secondary">Back</a></footer>
  </section>
</div></div>
@endsection