@extends('layouts.app')

@section('title', 'Gate Pass | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('gate_passes.update', $gatePass->doc_no) }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Gate Pass — {{ $gatePass->doc_no }}</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Editing will reverse and redo this gate pass's stock movement.
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>From Location <span class="text-danger">*</span></label>
              <select name="from_location_id" class="form-control select2-js" required>
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}" @selected($loc->id == $gatePass->from_location_id)>
                    {{ $loc->name }} @if($loc->vendor) ({{ $loc->vendor->name }}) @endif
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label>To Location <span class="text-danger">*</span></label>
              <select name="to_location_id" class="form-control select2-js" required>
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}" @selected($loc->id == $gatePass->to_location_id)>
                    {{ $loc->name }} @if($loc->vendor) ({{ $loc->vendor->name }}) @endif
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="entry_date" class="form-control" value="{{ $gatePass->entry_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $gatePass->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                @foreach($gatePass->items as $i => $item)
                <tr class="item-row">
                  <td>
                    <select name="items[{{ $i }}][product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $item->product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control" step="any" min="0.001" value="{{ $item->quantity }}"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Product
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Gate Pass</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let rowIndex = {{ $gatePass->items->count() }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][product_id]" class="form-control select2-js" required>
            <option value="">Select Product</option>
            @foreach ($products as $p)
              <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control" step="any" min="0.001" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
    }
  });
</script>
@endsection