@extends('layouts.app')
@section('title', 'Stock Movement | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('stock_movements.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Stock Movement</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-3 mb-3">
            <label>Movement Type <span class="text-danger">*</span></label>
            <select name="movement_type" id="movement_type" class="form-control" required>
              <option value="">Select Type</option>
              <option value="warehouse_to_warehouse">Warehouse → Warehouse</option>
              <option value="warehouse_to_vendor">Warehouse → Vendor</option>
              <option value="vendor_to_warehouse">Vendor → Warehouse</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>From Location <span class="text-danger">*</span></label>
            <select name="from_location_id" id="from_location" class="form-control select2-js" required>
              <option value="">Select Location</option>
              @foreach($locations as $loc)<option value="{{ $loc->id }}" data-vendor="{{ $loc->vendor_id ?? '' }}">{{ $loc->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>To Location <span class="text-danger">*</span></label>
            <select name="to_location_id" id="to_location" class="form-control select2-js" required>
              <option value="">Select Location</option>
              @foreach($locations as $loc)<option value="{{ $loc->id }}" data-vendor="{{ $loc->vendor_id ?? '' }}">{{ $loc->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3" id="lotField" style="display:none">
            <label>Lot # <span class="text-muted">(assigned by mill)</span> <span class="text-danger">*</span></label>
            <input type="text" name="lot_no" class="form-control">
          </div>
          <div class="col-md-3 mb-3"><label>Movement Date <span class="text-danger">*</span></label><input type="date" name="movement_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-5 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
          <div class="col-md-4 mb-3"><label>Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>

        <table class="table table-bordered" id="itemsTable">
          <thead><tr><th width="30%">Product</th><th id="lotColHeader" style="display:none">Source Lot #</th><th>Quantity</th><th></th></tr></thead>
          <tbody id="itemsBody"></tbody>
        </table>
        <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Item</button>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Movement</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = 0;
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); addRow(); });

  $('#movement_type').on('change', function () {
    const type = $(this).val();
    $('#lotField').toggle(type === 'warehouse_to_vendor');
    $('#lotColHeader').toggle(type === 'vendor_to_warehouse');
    $('.source-lot-cell').toggle(type === 'vendor_to_warehouse');
  });

  function productOptionsHtml() {
    let h = '<option value="">Select Product</option>';
    @foreach($products as $p)h += `<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->sku }}</option>`;@endforeach
    return h;
  }

  function addRow() {
    const idx = rowIndex++;
    const type = $('#movement_type').val();
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>${productOptionsHtml()}</select></td>
        <td class="source-lot-cell" style="display:${type === 'vendor_to_warehouse' ? 'table-cell' : 'none'}">
          <select name="items[${idx}][source_lot_no]" class="form-control lot-select"><option value="">—</option></select>
        </td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control" step="any" min="0.001" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }
  $('#addRowBtn').on('click', addRow);

  $(document).on('change', '.product-select', function () {
    const row = $(this).closest('tr');
    const type = $('#movement_type').val();
    if (type !== 'vendor_to_warehouse') return;

    const locationId = $('#from_location').val();
    const productId = $(this).val();
    if (!locationId || !productId) return;

    fetch(`/stock-movements/available-lots?location_id=${locationId}&product_id=${productId}`).then(r => r.json()).then(lots => {
      let html = '<option value="">—</option>';
      lots.forEach(l => html += `<option value="${l.lot_no}">${l.lot_no} (${l.quantity})</option>`);
      row.find('.lot-select').html(html);
    });
  });

  $(document).on('click', '.remove-row', function () { if ($('.item-row').length > 1) $(this).closest('tr').remove(); });
</script>
@endsection