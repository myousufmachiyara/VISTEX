@extends('layouts.app')
@section('title', 'Import Products')
@section('content')
<div class="row"><div class="col-md-8">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Import Products from CSV</h2></header>
    <div class="card-body">
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
      @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert alert-warning">
          <strong>{{ count(session('import_errors')) }} rows had issues:</strong>
          <ul class="mb-0">@foreach(session('import_errors') as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">File (CSV or tab-separated, first row must be a header)</label>
          <input type="file" name="import_file" class="form-control" accept=".csv,.txt" required>
        </div>

        <div class="alert alert-info py-2">
          <strong>Required columns:</strong> <code>name</code>, <code>category_code</code>
          <br><strong>Optional columns:</strong> <code>sku</code> (auto-generated if blank), <code>description</code>,
          <code>opening_stock</code>, <code>selling_price</code>, <code>unit_shortcode</code> (e.g. kg, m, pcs — defaults to pcs), <code>is_active</code>
          <p class="mb-1 mt-2"><strong>Valid category_code values:</strong>
            @foreach($categories as $cat)<code>{{ $cat->code }}</code>@if(!$loop->last), @endif @endforeach
          </p>
          <p class="mb-0">Any other column (e.g. <code>construction</code>, <code>count</code>, <code>warp</code>, <code>width</code>) is automatically stored as a category-specific attribute.</p>
        </div>

        <button type="submit" class="btn btn-primary">Import</button>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div>
  </section>
</div></div>
@endsection