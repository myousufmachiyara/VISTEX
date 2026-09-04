@extends('layouts.app')
@section('title', 'Edit Return | ' . $return->return_no)
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_returns.update', $return->id) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    <section class="card">
      <header class="card-header"><h2 class="card-title">Edit Return — {{ $return->return_no }}</h2></header>
      <div class="card-body">
        <p><strong>GRN:</strong> {{ $return->purchaseReceiving->receiving_no ?? '' }}</p>
        <table class="table table-bordered table-sm">
          <thead><tr><th>Product</th><th class="text-end">Qty Returned</th></tr></thead>
          <tbody>
            @foreach($return->items as $item)
            <tr><td>{{ $item->purchaseReceivingItem->product->name ?? '' }}</td><td class="text-end">{{ number_format($item->quantity_returned,3) }}</td></tr>
            @endforeach
          </tbody>
        </table>
        <p class="text-muted small">Quantities cannot be edited after creation — only remarks and proof images can be updated.</p>

        <div class="mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ $return->remarks }}</textarea></div>
        <div class="mb-3">
          <label>Existing Proof Images</label>
          <div class="row">
            @foreach($return->proof_images ?? [] as $img)
            <div class="col-md-3 mb-2"><img src="{{ Storage::url($img) }}" class="img-fluid border rounded"></div>
            @endforeach
          </div>
        </div>
        <div class="mb-3"><label>Add More Proof Images</label><input type="file" name="proof_images[]" class="form-control" multiple accept="image/*"></div>
      </div>
      <footer class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="{{ route('purchase_returns.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </footer>
    </section>
  </form>
</div></div>
@endsection