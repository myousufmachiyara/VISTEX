@extends('layouts.app')

@section('title', 'Stock Movement | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('stock_movements.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Stock Movement</h2>
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

          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Type <span class="text-danger">*</span></label>
              <select name="movement_type" id="movement_type" class="form-control" required>
                <option value="transfer">Transfer (between locations)</option>
                <option value="adjustment">Adjustment (manual correction)</option>
              </select>
            </div>

            <div class="col-md-3 mb-3" id="from_field">
              <label>From Location <span class="text-danger">*</span></label>
              <select name="from_location_id" id="from_location" class="form-control select2-js">
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}">{{ $loc->name }} @if($loc->vendor) ({{ $loc->vendor->name }}) @endif</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3" id="to_field">
              <label>To Location <span class="text-danger">*</span></label>
              <select name="to_location_id" class="form-control select2-js">
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}">{{ $loc->name }} @if($loc->vendor) ({{ $loc->vendor->name }}) @endif</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="movement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-6 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="selectLocationMsg">
            Select a "From Location" to see available stock (for transfers).
          </div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Available</th>
                  <th>Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody"></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Product
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save (Pending Acceptance)</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let availableItems = [];
  let rowIndex = 0;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    toggleType();
  });

  $('#movement_type').on('change', toggleType);

  function toggleType() {
    const type = $('#movement_type').val();
    if (type === 'adjustment') {
      $('#itemsSection').show();
      $('#selectLocationMsg').hide();
      if ($('#itemsBody tr').length === 0) addRow(null, null, true);
    } else {
      $('#itemsSection').hide();
      $('#selectLocationMsg').show();
      $('#itemsBody').empty();
    }
  }

  $('#from_location').on('change', function () {
    if ($('#movement_type').val() !== 'transfer') return;

    const locationId = $(this).val();
    $('#itemsBody').empty();
    rowIndex = 0;

    if (!locationId) {
      $('#itemsSection').hide();
      $('#selectLocationMsg').show().text('Select a "From Location" to see available stock.');
      return;
    }

    fetch(`/stock-movements/available-at?location_id=${locationId}`)
      .then(res => res.json())
      .then(data => {
        availableItems = data;
        if (data.length === 0) {
          $('#itemsSection').hide();
          $('#selectLocationMsg').show().text('No stock available at this location.');
          return;
        }
        $('#selectLocationMsg').hide();
        $('#itemsSection').show();
        addRow();
      });
  });

  function productOptionsHtml(selectedId) {
    if ($('#movement_type').val() === 'adjustment') {
      // Adjustments allow any product, not just what's available at a location
      let html = '<option value="">Select Product</option>';
      @foreach (\App\Models\Product::active()->orderBy('name')->get() as $p)
        html += `<option value="{{ $p->id }}" @if($loop->first)selected @endif>{{ $p->name }} ({{ $p->sku }})</option>`;
      @endforeach
      return html;
    }
    let html = '<option value="">Select Product</option>';
    availableItems.forEach(item => {
      const sel = item.product_id == selectedId ? 'selected' : '';
      html += `<option value="${item.product_id}" data-available="${item.available}" ${sel}>
                 ${item.product_name} (available: ${item.available})
               </option>`;
    });
    return html;
  }

  function addRow() {
    const idx = rowIndex++;
    const isAdjustment = $('#movement_type').val() === 'adjustment';
    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>
            ${productOptionsHtml()}
          </select>
        </td>
        <td class="available-cell">—</td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" value="0"
              ${isAdjustment ? '' : 'min="0.001"'}></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });

    if (isAdjustment) {
      row.find('.available-cell').text('N/A');
      row.find('.qty-input').attr('title', 'Positive to add, negative to remove');
    }
  }

  $('#addRowBtn').on('click', addRow);

  $(document).on('change', '.product-select', function () {
    if ($('#movement_type').val() !== 'transfer') return;
    const selected = $(this).find('option:selected');
    const available = parseFloat(selected.data('available')) || 0;
    const row = $(this).closest('tr');
    row.find('.available-cell').text(available);
    row.find('.qty-input').attr('max', available);
  });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
    }
  });
</script>
@endsection