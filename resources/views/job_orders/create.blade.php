@extends('layouts.app')

@section('title', 'Job Order | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('jobs.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Job Order (Issue to Vendor)</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" id="vendor_select" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Job Type</label>
              <select name="job_type_id" class="form-control select2-js">
                <option value="">— Select —</option>
                @foreach ($jobTypes as $jt)
                  <option value="{{ $jt->id }}">{{ $jt->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Issue Date</label>
              <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Sale Order (optional)</label>
              <select name="sale_id" class="form-control select2-js">
                <option value="">— None —</option>
                {{-- populated once Sale module exists --}}
              </select>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <div class="alert alert-warning py-2" id="vendor_warning" style="display:none">
            Select a vendor first to see available stock.
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Available (Fresh + Leftover)</th>
                  <th>Issue Quantity</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <tr class="item-row">
                  <td>
                    <select name="items[0][product_id]" class="form-control select2-js product-select">
                      <option value="">Select Product</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                      @endforeach
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
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Issue Job Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let itemIndex = 1;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  function checkStock(row) {
    const vendorId = $('#vendor_select').val();
    const productId = row.find('.product-select').val();
    const stockCell = row.find('.available-stock');

    if (!vendorId || !productId) {
      stockCell.text('—');
      return;
    }

    fetch(`/jobs/available-stock?vendor_id=${vendorId}&product_id=${productId}`)
      .then(res => res.json())
      .then(data => {
        stockCell.html(`Fresh: ${data.fresh} | Leftover: ${data.leftover} | <strong>Total: ${data.total}</strong>`);
        row.find('.qty-input').attr('max', data.total);
      });
  }

  $(document).on('change', '#vendor_select', function () {
    $('.item-row').each(function () { checkStock($(this)); });
  });

  $(document).on('change', '.product-select', function () {
    checkStock($(this).closest('.item-row'));
  });

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    @foreach ($products as $product)
      html += `<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#add-row').on('click', function () {
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
  });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
    }
  });
</script>
@endsection