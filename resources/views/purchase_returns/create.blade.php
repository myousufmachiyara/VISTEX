@extends('layouts.app')
@section('title', 'Return Rejected Items')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_returns.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="purchase_receiving_id" value="{{ $receiving->id }}">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Return Rejected Items — {{ $receiving->receiving_no }}</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <p><strong>Vendor:</strong> {{ $receiving->purchaseOrder->vendor->name ?? '' }}</p>

        <table class="table table-bordered">
          <thead><tr><th>Product</th><th class="text-end">Rejected</th><th class="text-end">Already Returned</th><th class="text-end">Pending Return</th><th width="18%">Return Qty Now</th></tr></thead>
          <tbody>
            @foreach($pendingItems as $idx => $item)
            <tr>
              <td>{{ $item->product->name ?? '' }}<input type="hidden" name="items[{{ $idx }}][purchase_receiving_item_id]" value="{{ $item->id }}"></td>
              <td class="text-end">{{ number_format($item->quantity_rejected,3) }}</td>
              <td class="text-end">{{ number_format($item->quantity_returned,3) }}</td>
              <td class="text-end"><strong>{{ number_format($item->quantity_pending_return,3) }}</strong></td>
              <td><input type="number" name="items[{{ $idx }}][quantity_returned]" class="form-control" value="0" step="any" min="0" max="{{ $item->quantity_pending_return }}"></td>
            </tr>
            @endforeach
          </tbody>
        </table>

        <div class="row">
          <div class="col-md-3 mb-3"><label>Return Date</label><input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-5 mb-3"><label>Proof (photo/receipt)</label><input type="file" name="proof_images[]" class="form-control" multiple></div>
          <div class="col-md-4 mb-3"><label>Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Record Return</button></footer>
    </section>
  </form>
</div></div>
@endsection