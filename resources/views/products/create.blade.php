@extends('layouts.app')
@section('title', 'Product | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('products.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Product</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Category <span class="text-danger">*</span></label>
            <select name="category_id" id="category_select" class="form-control select2-js" required>
              <option value="">Select Category</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-4 mb-3">
            <label>SKU / Code <span class="text-muted">(auto-generated if blank)</span></label>
            <input type="text" name="sku" class="form-control">
          </div>
          <div class="col-md-3 mb-3">
            <label>Measurement Unit <span class="text-danger">*</span></label>
            <select name="measurement_unit" class="form-control select2-js" required>
              <option value="">Select Unit</option>
              @foreach($units as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->shortcode }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>Opening Stock</label>
            <input type="number" name="opening_stock" class="form-control" step="any" min="0" value="0">
          </div>
          <div class="col-md-3 mb-3">
            <label>Selling Price</label>
            <input type="number" name="selling_price" class="form-control" step="any" min="0" value="0">
          </div>
          <div class="col-md-3 mb-3">
            <label>Status</label>
            <select name="is_active" class="form-control">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
          <div class="col-md-12 mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <div id="dynamicFieldsSection" style="display:none">
          <hr>
          <h6 id="dynamicFieldsTitle">Category-Specific Details</h6>
          <div class="row" id="dynamicFieldsBody"></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Product</button></footer>
    </section>
  </form>
</div></div>

<script>
  const schemas = @json($schemas);

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#category_select').on('change', function () {
    const catId = $(this).val();
    const fields = schemas[catId] || [];
    const body = $('#dynamicFieldsBody');
    body.empty();

    if (fields.length === 0) {
      $('#dynamicFieldsSection').hide();
      return;
    }

    fields.forEach(field => {
      body.append(`
        <div class="col-md-4 mb-3">
          <label>${field.label}</label>
          <input type="text" name="attributes[${field.key}]" class="form-control">
        </div>
      `);
    });

    $('#dynamicFieldsSection').show();
  });
</script>
@endsection