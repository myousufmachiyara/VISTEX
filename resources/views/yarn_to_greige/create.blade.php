@extends('layouts.app')

@section('title', 'Yarn-to-Greige Order | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('yarn_to_greige.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Yarn-to-Greige Order (Issue against CPO)</h2>
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
            <div class="col-md-4 mb-3">
              <label>Conversion PO <span class="text-danger">*</span></label>
              <select name="cpo_id" id="cpo_select" class="form-control select2-js" required>
                <option value="">Select CPO</option>
                @foreach ($cpos as $cpo)
                  <option value="{{ $cpo->id }}">
                    {{ $cpo->cpo_no }} — {{ $cpo->vendor->name ?? '' }} — {{ $cpo->item_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor Location <span class="text-danger">*</span></label>
              <select name="location_id" id="location_select" class="form-control select2-js" required disabled>
                <option value="">Select CPO first</option>
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Issue Date</label>
              <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <div class="alert alert-warning py-2" id="locationWarning">
            Select a CPO first, then select the vendor location to issue from.
          </div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Available (Fresh + Leftover)</th>
                  <th>Issue Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <tr class="item-row">
                  <td>
                    <select name="items[0][product_id]" class="form-control select2-js product-select">
                      <option value="">Select Product</option>
                    </select>
                  </td>
                  <td class="available-stock text-muted">—</td>
                  <td><input type="number" name="items[0][quantity]" class="form-control qty-input" step="any" min="0.001"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" id="add-row"><i class="fas fa-plus"></i> Add Product</button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Issue Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let cpoProducts = []; // warp+weft products from the selected CPO
  let itemIndex = 1;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

    $('#cpo_select').on('change', function () {
    const cpoId = $(this).val();
    $('#location_select').prop('disabled', true).html('<option value="">Loading...</option>').trigger('change');
    $('#itemsSection').hide();
    $('#itemsBody').empty();
    itemIndex = 0;
    cpoProducts = [];

    if (!cpoId) return;

    fetch(`/yarn-to-greige/vendor-locations/${cpoId}`)
      .then(res => res.json())
      .then(locations => {
        let html = '<option value="">Select Location</option>';
        locations.forEach(loc => {
          html += `<option value="${loc.id}">${loc.name}</option>`;
        });
        $('#location_select').prop('disabled', false).html(html);
      });

    fetch(`/yarn-to-greige/cpo-yarns/${cpoId}`)
      .then(res => res.json())
      .then(products => {
        cpoProducts = products;
      });
  });

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    cpoProducts.forEach(p => {
      html += `<option value="${p.id}">${p.name}</option>`;
    });
    return html;
  }

  function addRow() {
    const idx = itemIndex++;
    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][product_id]" class="form-control select2-js product-select">
            ${productOptionsHtml()}
          </select>
        </td>
        <td class="available-stock text-muted">—</td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }

  $('#location_select').on('change', function () {
    const locationId = $(this).val();
    if (!locationId) {
      $('#itemsSection').hide();
      return;
    }
    $('#itemsSection').show();
    addRow();
  });

  function addRow() {
    const idx = itemIndex++;
    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][product_id]" class="form-control select2-js product-select">
            <option value="">Select Product</option>
          </select>
        </td>
        <td class="available-stock text-muted">—</td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }

  $('#add-row').on('click', addRow);

  function checkStock(row) {
    const locationId = $('#location_select').val();
    const productId = row.find('.product-select').val();
    const stockCell = row.find('.available-stock');

    if (!locationId || !productId) {
      stockCell.text('—');
      return;
    }

    fetch(`/yarn-to-greige/available-stock?location_id=${locationId}&product_id=${productId}`)
      .then(res => res.json())
      .then(data => {
        stockCell.html(`Fresh: ${data.fresh} | Leftover: ${data.leftover} | <strong>Total: ${data.total}</strong>`);
        row.find('.qty-input').attr('max', data.total);
      });
  }

  $(document).on('change', '.product-select', function () {
    checkStock($(this).closest('.item-row'));
  });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
    }
  });
</script>
@endsection