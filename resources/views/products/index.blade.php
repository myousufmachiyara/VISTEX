@extends('layouts.app')
@section('title', 'Products')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Products</h2>
      <div>
        @can('products.create')
        <a href="{{ route('products.import_xero.form') }}" class="btn btn-outline-secondary me-1">Import (Xero)</a>
        <a href="{{ route('products.import.form') }}" class="btn btn-outline-primary me-1">Import CSV</a>
        <a href="{{ route('products.create') }}" class="btn btn-primary">New Product</a>
        @endcan
      </div>
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
          <select name="category_id" class="form-control" onchange="this.form.submit()">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="prodTable">
        <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Unit</th><th class="text-end">Opening Stock</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($products as $p)
          <tr>
            <td>{{ $p->name }}</td>
            <td>{{ $p->sku }}</td>
            <td>{{ $p->category->name ?? '' }}</td>
            <td>{{ $p->measurementUnit->shortcode ?? '' }}</td>
            <td class="text-end">{{ number_format($p->opening_stock, 3) }}</td>
            <td><span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>
              @can('products.edit')
              <a href="{{ route('products.edit', $p->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
              @endcan
              @can('products.delete')
              <form action="{{ route('products.destroy', $p->id) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
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
<script>$(document).ready(()=>$('#prodTable').DataTable({pageLength:50}));</script>
@endsection