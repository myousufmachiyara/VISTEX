@extends('layouts.app')
@section('title', 'Report Issue | ' . $po->order_no)
@section('content')
<div class="row"><div class="col-md-8">
  <form action="{{ route('purchase_orders.object.store', $po->id) }}" method="POST">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Report Issue — {{ $po->order_no }}</h2></header>
      <div class="card-body">
        <p><strong>Vendor:</strong> {{ $po->vendor->name ?? '' }}</p>
        <table class="table table-bordered table-sm mb-3">
          <thead><tr><th>Product</th><th class="text-end">Ordered</th><th class="text-end">Received So Far</th></tr></thead>
          <tbody>
            @foreach($po->items as $item)
            <tr><td>{{ $item->product->name ?? '' }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">{{ $item->quantity_received }}</td></tr>
            @endforeach
          </tbody>
        </table>
        <div class="mb-3">
          <label class="form-label">Describe the issue <span class="text-danger">*</span></label>
          <textarea name="remarks" class="form-control" rows="4" required placeholder="e.g. Only 80kg arrived instead of 100kg ordered."></textarea>
        </div>
      </div>
      <footer class="card-footer text-end">
        <button type="submit" class="btn btn-danger">Submit Objection</button>
        <a href="{{ route('purchase_receivings.create') }}" class="btn btn-outline-secondary">Cancel</a>
      </footer>
    </section>
  </form>
</div></div>
@endsection