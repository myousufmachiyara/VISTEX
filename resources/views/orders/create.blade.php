@extends('layouts.app')

@section('title', 'Order | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('orders.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Customer Order</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Customer <span class="text-danger">*</span></label>
              <select name="customer_id" id="customer_select" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach ($customers as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Collection</label>
              <input type="text" name="collection" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Article</label>
              <input type="text" name="article" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Pattern #</label>
              <input type="text" name="pattern_no" class="form-control">
            </div>

            <div class="col-md-1 mb-3">
              <label>Order Date</label>
              <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Delivery Date</label>
              <input type="date" name="delivery_date" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-6 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Design / Print</th>
                  <th>Quantity</th>
                  <th>Rate</th>
                  <th>Amount</th>
                  <th>Save rate?</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <tr class="item-row">
                  <td>
                    <select name="items[0][product_id]" class="form-control select2-js sku-select">
                      <option value="">Select SKU</option>
                      @foreach ($skus as $sku)
                        <option value="{{ $sku->id }}">{{ $sku->name }} ({{ $sku->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="items[0][design]" class="form-control" placeholder="Print/design detail"></td>
                  <td><input type="number" name="items[0][quantity]" class="form-control qty-input" step="any" min="0.001" value="0"></td>
                  <td><input type="number" name="items[0][rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
                  <td class="amount-cell text-end">0.00</td>
                  <td class="text-center"><input type="checkbox" name="items[0][save_rate]" value="1" class="save-rate-check"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-end fw-bold">Total:</td>
                  <td class="fw-bold text-end" id="grandTotal">0.00</td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add SKU
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let rowIndex = 1;

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  function skuOptionsHtml() {
    let html = '<option value="">Select SKU</option>';
    @foreach ($skus as $sku)
      html += `<option value="{{ $sku->id }}">{{ $sku->name }} ({{ $sku->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js sku-select">${skuOptionsHtml()}</select></td>
        <td><input type="text" name="items[${idx}][design]" class="form-control" placeholder="Print/design detail"></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td class="text-center"><input type="checkbox" name="items[${idx}][save_rate]" value="1" class="save-rate-check"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('change', '.sku-select', function () {
    const customerId = $('#customer_select').val();
    const productId = $(this).val();
    const row = $(this).closest('tr');

    if (!customerId || !productId) return;

    fetch(`/orders/suggest-rate?customer_id=${customerId}&product_id=${productId}`)
      .then(res => res.json())
      .then(data => {
        if (data.rate !== null) {
          row.find('.rate-input').val(data.rate);
          recalcLine(row);
        }
      });
  });

  $(document).on('input', '.qty-input, .rate-input', function () { recalcLine($(this).closest('tr')); });

  function recalcLine(row) {
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    row.find('.amount-cell').text((qty * rate).toFixed(2));
    recalcTotal();
  }

  function recalcTotal() {
    let total = 0;
    $('.item-row').each(function () { total += parseFloat($(this).find('.amount-cell').text()) || 0; });
    $('#grandTotal').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotal(); }
  });
</script>
@endsection