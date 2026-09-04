@extends('layouts.app')

@section('title', 'Products | Edit — ' . $product->name)

@section('content')
<div class="row">
  <div class="col">
    <form id="productForm" action="{{ route('products.update', $product->id) }}"
          method="POST" enctype="multipart/form-data"
          onkeydown="return event.key != 'Enter';">
      @csrf
      @method('PUT')

      @if(session('success'))
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          <ul class="mb-0">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Product</h2>
          <a href="{{ route('products.index') }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </header>

        <div class="card-body">

          {{-- ── Basic info ──────────────────────────────────────── --}}
          <div class="row pb-3">

            <div class="col-md-3 mb-3">
              <label class="form-label">Product Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                     value="{{ old('name', $product->name) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="edit_category_id" class="form-control select2-js" required>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}"
                    {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">SKU <span class="text-danger">*</span></label>
              <input type="text" name="sku" id="sku" class="form-control"
                     value="{{ old('sku', $product->sku) }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Measurement Unit <span class="text-danger">*</span></label>
              <select name="measurement_unit" class="form-control" required>
                <option value="">-- Select Unit --</option>
                @foreach($units as $unit)
                  <option value="{{ $unit->id }}"
                    {{ old('measurement_unit', $product->measurement_unit) == $unit->id ? 'selected' : '' }}>
                    {{ $unit->name }} ({{ $unit->shortcode }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Selling Price / Unit</label>
              <input type="number" step="any" name="selling_price" class="form-control"
                     value="{{ old('selling_price', $product->selling_price) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control"
                     value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Status</label>
              <select name="is_active" class="form-control">
                <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="1">{{ old('description', $product->description) }}</textarea>
            </div>

          </div>

          {{-- ── Category-wise attributes (JSON, existing feature — unaffected) ── --}}
          <div class="row" id="categoryAttributesSection">
            {{-- Populated by JS below based on selected category's schema,
                 pre-filled from $product->attributes on load --}}
          </div>

        </div>{{-- /card-body --}}

        <footer class="card-footer text-end">
          <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Product
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
  $('.select2-js').select2({ width: '100%' });

  const schemas = @json($schemas);
  const existingAttributes = @json($product->attributes ?? []);

  function renderCategoryAttributes(categoryId) {
    const section = $('#categoryAttributesSection');
    section.empty();

    const schema = schemas[categoryId];
    if (!schema || schema.length === 0) return;

    schema.forEach(function (field) {
      const existingVal = existingAttributes[field.key] ?? '';
      section.append(`
        <div class="col-md-3 mb-3">
          <label class="form-label">${field.label}</label>
          <input type="text" name="attributes[${field.key}]" class="form-control" value="${existingVal}">
        </div>
      `);
    });
  }

  // Render on load using the product's current category
  renderCategoryAttributes({{ $product->category_id }});

  // Re-render if category is changed
  $('#edit_category_id').on('change', function () {
    renderCategoryAttributes($(this).val());
  });
});
</script>

@endsection