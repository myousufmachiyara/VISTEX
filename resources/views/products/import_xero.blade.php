@extends('layouts.app')
@section('title', 'Import Products (Xero Export)')
@section('content')
<div class="row"><div class="col-md-8">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Import Products from Xero Inventory Export</h2></header>
    <div class="card-body">
      @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert alert-warning">
          <strong>{{ count(session('import_errors')) }} rows skipped:</strong>
          <ul class="mb-0">@foreach(array_slice(session('import_errors'), 0, 20) as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ route('products.import_xero') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">Xero Inventory Items CSV</label>
          <input type="file" name="import_file" class="form-control" accept=".csv" required>
        </div>
        <div class="alert alert-info py-2">
          <strong>Category mapping (by InventoryAssetAccount):</strong>
          <ul class="mb-0 small">
            <li>35000 → Yarn</li><li>35001 → Greige</li><li>35003 → Packaging</li>
            <li>35005 → Leftover</li><li>35006 → Rejection</li><li>35007 → SKU / Finished Goods</li>
            <li>35004 (Cut Pcs / per-job WIP) — <strong>skipped</strong></li>
          </ul>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div>
  </section>
</div></div>
@endsection