@extends('layouts.app')

@section('title', 'Purchase Return | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_returns.update', $return->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Return — {{ $return->return_no }}</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          @if($return->purchase)
            <div class="alert alert-info py-2">
              Linked to Purchase Invoice <strong>{{ $return->purchase->purchase_no }}</strong>
            </div>
          @else
            <div class="alert alert-warning py-2">No invoice linked — manual entry.</div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" class="form-control select2-js" required {{ $return->purchase ? 'disabled' : '' }}>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" @selected($vendor->id == $return->vendor_id)>{{ $vendor->name }}</option>
                @endforeach
              </select>
              @if($return->purchase)
                <input type="hidden" name="vendor_id" value="{{ $return->vendor_id }}">
              @endif
            </div>

            <div class="col-md-3 mb-3">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ $return->return_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
              @if($return->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($return->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1">{{ $return->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th>Unit Price</th>
                  <th>Amount</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                @foreach($return->items as $i => $item)
                <tr class="item-row">
                  <td>
                    <select name="items[{{ $i }}][product_id]" class="form-control select2-js" required>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $item->product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                    @if($item->purchase_item_id)
                      <input type="hidden" name="items[{{ $i }}][purchase_item_id]" value="{{ $item->purchase_item_id }}">
                    @endif
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control qty-input" step="any" min="0" value="{{ $item->quantity }}"></td>
                  <td><input type="number" name="items[{{ $i }}][unit_price]" class="form-control price-input" step="any" min="0" value="{{ $item->unit_price }}"></td>
                  <td class="amount-cell text-end">{{ number_format($item->amount, 2) }}</td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-bold">Total:</td>
                  <td class="fw-bold text-end" id="grandTotal">{{ number_format($return->total_amount, 2) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Product
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Return</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let rowIndex = {{ $return->items->count() }};

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); recalcTotal(); });

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    @foreach ($products as $p)
      html += `<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js">${productOptionsHtml()}</select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][unit_price]" class="form-control price-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.qty-input, .price-input', function () { recalcLine($(this).closest('tr')); });

  function recalcLine(row) {
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const price = parseFloat(row.find('.price-input').val()) || 0;
    row.find('.amount-cell').text((qty * price).toFixed(2));
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