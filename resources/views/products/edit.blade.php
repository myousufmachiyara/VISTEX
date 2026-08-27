@extends('layouts.app')

@section('title', 'Products | Edit — ' . $product->name)

@section('content')
<div class="row">
  <div class="col">
    {{-- FIX: added onkeydown — was missing on edit form --}}
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

            <div class="col-md-2 mb-3">
              <label class="form-label">Product Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                     value="{{ old('name', $product->name) }}">
            </div>

            <div class="col-md-2 mb-3">
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

            <div class="col-md-2 mb-3">
              <label class="form-label">Sub Category</label>
              {{-- FIX: only shows subcategories of the product's own category
                   (controller now passes filtered $subcategories) --}}
              <select name="subcategory_id" id="edit_subcategory_id" class="form-control">
                <option value="">None</option>
                @foreach($subcategories as $subcat)
                  <option value="{{ $subcat->id }}"
                    {{ old('subcategory_id', $product->subcategory_id) == $subcat->id ? 'selected' : '' }}>
                    {{ $subcat->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">SKU <span class="text-danger">*</span></label>
              <input type="text" name="sku" id="sku" class="form-control"
                     value="{{ old('sku', $product->sku) }}" required>
            </div>

            <div class="col-md-2 mb-3">
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

            <div class="col-md-2 mb-3">
              <label class="form-label">Selling Price / Unit</label>
              <input type="number" step="any" name="selling_price" class="form-control"
                     value="{{ old('selling_price', $product->selling_price) }}">
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control"
                     value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Status</label>
              <select name="is_active" class="form-control">
                <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
            </div>

          </div>

          {{-- ── Existing variations ─────────────────────────────── --}}
          <div class="row mt-3">
            <div class="col-md-12">
              <h2 class="card-title">Existing Variations</h2>
              <div id="variation-section">
                @foreach($product->variations as $i => $variation)
                  <div class="variation-block border p-2 mb-3 existing-variation">
                    <input type="hidden" name="variations[{{ $i }}][id]" value="{{ $variation->id }}">
                    <div class="row">
                      <div class="col-md-4 mb-2">
                        <label class="form-label">SKU</label>
                        <input type="text" name="variations[{{ $i }}][sku]"
                               class="form-control sku-field" value="{{ $variation->sku }}">
                      </div>
                      <div class="col-md-2 mb-2">
                        <label class="form-label">Stock</label>
                        <input type="number" step="any"
                               name="variations[{{ $i }}][stock_quantity]"
                               class="form-control" value="{{ $variation->stock_quantity }}">
                      </div>
                      <div class="col-md-4 mb-2">
                        <label class="form-label">Attributes</label>
                        <select name="variations[{{ $i }}][attributes][]"
                                multiple class="form-control select2-js variation-attributes">
                          @foreach($attributes as $attribute)
                            @foreach($attribute->values as $value)
                              <option value="{{ $value->id }}"
                                {{ $variation->attributeValues->pluck('id')->contains($value->id) ? 'selected' : '' }}>
                                {{ $attribute->name }} — {{ $value->value }}
                              </option>
                            @endforeach
                          @endforeach
                        </select>
                      </div>
                      <div class="col-md-2 d-flex align-items-end mb-2">
                        <button type="button" class="btn btn-sm btn-danger remove-existing-variation"
                                data-id="{{ $variation->id }}">
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>

              {{-- ── Add new variations ───────────────────────────── --}}
              <h2 class="card-title mt-3">Add New Variations</h2>
              <div id="new-variation-section"></div>
              <button type="button" class="btn btn-sm btn-secondary mt-2" id="addNewVariationBtn">
                <i class="fas fa-plus"></i> Add Variation
              </button>
            </div>
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

@push('scripts')
<script>
$(document).ready(function () {

  // ── Init Select2 on existing variation attribute selects ─────────
  $('.select2-js').select2({ width: '100%' });

  // ── Category change → reload subcategories ───────────────────────
  $('#edit_category_id').on('change', function () {
    var categoryId = $(this).val();
    var subSelect  = $('#edit_subcategory_id');

    subSelect.empty().append('<option value="">Loading…</option>');

    if (categoryId) {
      $.ajax({
        url:  "{{ route('helpers.category.subcategories', ':id') }}".replace(':id', categoryId),
        type: 'GET',
        success: function (data) {
          subSelect.empty().append('<option value="">None</option>');
          $.each(data, function (i, subcat) {
            subSelect.append('<option value="' + subcat.id + '">' + subcat.name + '</option>');
          });
        },
        error: function () {
          subSelect.empty().append('<option value="">Failed to load</option>');
        }
      });
    } else {
      subSelect.empty().append('<option value="">None</option>');
    }
  });

  // ── Auto-update SKU when variation attributes change ─────────────
  $(document).on('change', '.variation-attributes', function () {
    var block       = $(this).closest('.variation-block');
    var attrTexts   = [];
    $(this).find('option:selected').each(function () {
      // Text format is "Attribute — Value" — extract value part after '—'
      var parts = $(this).text().split('—');
      attrTexts.push(parts.length > 1 ? parts[1].trim() : $(this).text().trim());
    });
    var variationName = attrTexts.join('-');
    block.find('.sku-field').val($('#sku').val() + '-' + variationName);
  });

  // ── Add new variation row ────────────────────────────────────────
  var newVariationIndex = 0;
  $('#addNewVariationBtn').on('click', function () {
    newVariationIndex++;
    var attrOptions = '';
    @foreach($attributes as $attribute)
      @foreach($attribute->values as $value)
        attrOptions += '<option value="{{ $value->id }}">{{ $attribute->name }} — {{ $value->value }}</option>';
      @endforeach
    @endforeach

    var html =
      '<div class="variation-block border p-2 mb-3">' +
        '<div class="row">' +
          '<div class="col-md-4 mb-2">' +
            '<label class="form-label">SKU</label>' +
            '<input type="text" name="new_variations[' + newVariationIndex + '][sku]" class="form-control sku-field">' +
          '</div>' +
          '<div class="col-md-2 mb-2">' +
            '<label class="form-label">Stock</label>' +
            '<input type="number" step="any" name="new_variations[' + newVariationIndex + '][stock_quantity]" value="0" class="form-control">' +
          '</div>' +
          '<div class="col-md-4 mb-2">' +
            '<label class="form-label">Attributes</label>' +
            '<select name="new_variations[' + newVariationIndex + '][attributes][]" multiple class="form-control select2-js variation-attributes">' +
              attrOptions +
            '</select>' +
          '</div>' +
          '<div class="col-md-2 d-flex align-items-end mb-2">' +
            '<button type="button" class="btn btn-sm btn-danger remove-new-variation"><i class="fas fa-times"></i></button>' +
          '</div>' +
        '</div>' +
      '</div>';

    $('#new-variation-section').append(html);
    // Re-init Select2 only on the newly added select
    $('#new-variation-section .select2-js').last().select2({ width: '100%' });
  });

  // ── Remove new variation ─────────────────────────────────────────
  $(document).on('click', '.remove-new-variation', function () {
    $(this).closest('.variation-block').remove();
  });

  // ── Remove existing variation (with undo) ────────────────────────
  $(document).on('click', '.remove-existing-variation', function () {
    var block       = $(this).closest('.variation-block');
    var variationId = $(this).data('id');

    if (confirm('Remove this variation? You can undo before saving.')) {
      block.find('input, select, textarea').prop('disabled', true);
      block.hide();
      block.append(
        '<input type="hidden" name="removed_variations[]" value="' + variationId + '" class="removed-variation-flag">'
      );
      block.after(
        '<div class="undo-variation-alert alert alert-warning mb-3" data-id="' + variationId + '">' +
          'Variation removed. ' +
          '<button type="button" class="btn btn-sm btn-link p-0 undo-remove-variation">Undo</button>' +
        '</div>'
      );
    }
  });

  // ── Undo variation removal ───────────────────────────────────────
  $(document).on('click', '.undo-remove-variation', function () {
    var alertBox    = $(this).closest('.undo-variation-alert');
    var variationId = alertBox.data('id');

    // FIX: was using wrong selector input[name="variation_ids[]"]
    // Correct: look for the hidden input with name containing "[id]" and matching value
    var block = $('.variation-block').has(
      'input[value="' + variationId + '"][name*="[id]"]'
    );

    block.find('.removed-variation-flag').remove();
    block.find('input, select, textarea').prop('disabled', false);
    block.show();
    alertBox.remove();
  });

});
</script>
@endpush

@endsection