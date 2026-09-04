@extends('layouts.app')
@section('title', 'Report Objection — ' . $order->order_no)
@section('content')
<div class="row"><div class="col-md-6">
  <form action="{{ route('purchase_order_objections.store', $order->id) }}" method="POST">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Report Objection — {{ $order->order_no }}</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <p><strong>Vendor:</strong> {{ $order->vendor->name ?? '' }}</p>
        <div class="mb-3">
          <label>What's the issue?</label>
          <textarea name="remarks" class="form-control" rows="4" required placeholder="e.g. Quantity mismatch, damaged goods, wrong item delivered, quality concern..."></textarea>
        </div>
      </div>
      <footer class="card-footer text-end">
        <button type="submit" class="btn btn-danger">Report Objection</button>
        <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
      </footer>
    </section>
  </form>
</div></div>
@endsection