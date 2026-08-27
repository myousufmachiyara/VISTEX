@extends('layouts.app')

@section('title', 'Order | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('orders.update', $order->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Order — {{ $order->order_no }}</h2>
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
                  <option value="{{ $c->id }}" @selected($c->id == $order->customer_id)>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Collection</label>
              <input type="text" name="collection" class="form-control" value="{{ $order->collection }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Article</label>
              <input type="text" name="article" class="form-control" value="{{ $order->article }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Pattern #</label>
              <input type="text" name="pattern_no" class="form-control" value="{{ $order->pattern_no }}">
            </div>

            <div class="col-md-1 mb-3">
              <label>Order Date</label>
              <input type="date" name="order_date" class="form-control" value="{{ $order->order_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Delivery Date</label>
              <input type="date" name="delivery_date" class="form-control" value="{{ $order->delivery_date?->format('Y-m-d') }}">
            </div>

            <div class="col-md-6 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
              @if($order->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($order->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-6 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1">{{ $order->remarks }}</textarea>
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
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                @foreach($order->items as $i => $item)
                <tr class="item-row">
                  <td>
                    <select name="items[{{ $i }}][product_id]" class="form-control select2-js sku-select">
                      <option value="">Select SKU</option>
                      @foreach ($skus as $sku)
                        <option value="{{ $sku->id }}" @selected($sku->id == $item->product_id)>{{ $sku->name }} ({{ $sku->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="items[{{ $i }}][design]" class="form-control" value="{{ $item->design }}"></td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control qty-input" step="any" min="0.001" value="{{ $item->quantity }}"></td>
                  <td><input type="number" name="items[{{ $i }}][rate]" class="form-control rate-input" step="any" min="0" value="{{ $item->rate }}"></td>
                  <td class="amount-cell text-end">{{ number_format($item->amount, 2) }}</td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-end fw-bold">Total:</td>
                  <td class="fw-bold text-end" id="grandTotal">{{ number_format($order->total_amount, 2) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add SKU
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let rowIndex = {{ $order->items->count() }};

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
        <td><input type="text" name="items[${idx}][design]" class="form-control"></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
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