@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $purchase->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Invoice — {{ $purchase->purchase_no }}</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="{{ $purchase->items->count() }}">

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ $purchase->purchase_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" @selected($vendor->id == $purchase->vendor_id)>{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control" value="{{ $purchase->bill_no }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control" value="{{ $purchase->ref_no }}">
            </div>

            <div class="col-md-3 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($purchase->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($purchase->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $purchase->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="purchaseTable">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Item</th>
                  <th>Variation</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                @foreach($purchase->items as $i => $item)
                <tr>
                  <td class="serial-no">{{ $i + 1 }}</td>
                  <td>
                    <select name="items[{{ $i }}][item_id]" id="item_name{{ $i + 1 }}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-unit-id="{{ $product->measurement_unit }}" @selected($product->id == $item->product_id)>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select name="items[{{ $i }}][variation_id]" id="variation{{ $i + 1 }}" class="form-control select2-js variation-select">
                      <option value="">Select Variation</option>
                      @if($item->variation)
                        <option value="{{ $item->variation->id }}" selected>{{ $item->variation->sku }}</option>
                      @endif
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" id="pur_qty{{ $i + 1 }}" class="form-control quantity" value="{{ $item->quantity }}" step="any" onchange="rowTotal({{ $i + 1 }})"></td>
                  <td>
                    <select name="items[{{ $i }}][unit]" id="unit{{ $i + 1 }}" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected($unit->id == $item->product?->measurement_unit)>{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][price]" id="pur_price{{ $i + 1 }}" class="form-control" value="{{ $item->unit_price }}" step="any" onchange="rowTotal({{ $i + 1 }})"></td>
                  <td><input type="number" id="amount{{ $i + 1 }}" class="form-control" value="{{ $item->amount }}" step="any" disabled></td>
                  <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" value="{{ number_format($purchase->subtotal, 2) }}" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" value="{{ $purchase->items->sum('quantity') }}" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">{{ number_format($purchase->total_amount, 2) }}</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Update Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = {{ $purchase->items->count() + 1 }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    updateSerialNumbers();
    tableTotal();
  });

  function updateSerialNumbers() {
    $('#Purchase1Table tr').each(function (index) {
      $(this).find('.serial-no').text(index + 1);
    });
  }

  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
      updateSerialNumbers();
    }
  }

  function addNewRow_btn() {
    addNewRow();
    $('#item_name' + (index - 1)).focus();
  }

  function addNewRow() {
      let table = $('#Purchase1Table');
      let rowIndex = index - 1;

      let newRow = `
        <tr>
          <td class="serial-no"></td>
          <td>
            <select name="items[${rowIndex}][item_id]" id="item_name${index}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
              <option value="">Select Item</option>
              ${products.map(product =>
                `<option value="${product.id}" data-unit-id="${product.measurement_unit}">
                  ${product.name}
                </option>`).join('')}
            </select>
          </td>
          <td>
            <select name="items[${rowIndex}][variation_id]" id="variation${index}" class="form-control select2-js variation-select">
              <option value="">Select Variation</option>
            </select>
          </td>
          <td><input type="number" name="items[${rowIndex}][quantity]" id="pur_qty${index}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${index})"></td>
          <td>
            <select name="items[${rowIndex}][unit]" id="unit${index}" class="form-control" required>
              <option value="">-- Select --</option>
              @foreach ($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="items[${rowIndex}][price]" id="pur_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
          <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
          <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
          </td>
        </tr>
      `;
      table.append(newRow);
      $('#itemCount').val(index);
      $(`#item_name${index}`).select2();
      $(`#variation${index}`).select2();
      $(`#unit${index}`).select2();
      index++;
      updateSerialNumbers();
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty' + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0;
    let qty = 0;

    $('#Purchase1Table tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      qty += parseFloat($(this).find('input.quantity').val()) || 0;
    });

    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));

    netTotal();
  }

  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    $('#netTotal').text(formatNumberWithCommas(total.toFixed(2)));
    $('#net_amount').val(total.toFixed(2));
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function onItemNameChange(selectElement) {
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const rowIndex = idMatch[0];

    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const unitId = selectedOption.getAttribute('data-unit-id');
    $(`#unit${rowIndex}`).val(String(unitId)).trigger('change.select2');

    const variationSelect = $(`#variation${rowIndex}`);
    const itemId = selectElement.value;

    if (itemId) {
        variationSelect.html('<option value="">Loading...</option>').trigger('change.select2');

        fetch(`/product/${itemId}/variations`)
            .then(res => res.json())
            .then(data => {
                variationSelect.html('<option value="">Select Variation</option>');
                if (data.success && data.variation.length > 0) {
                    data.variation.forEach(v => {
                        variationSelect.append(`<option value="${v.id}">${v.sku}</option>`);
                    });
                } else {
                    variationSelect.html('<option value="">No Variations Found</option>');
                }
                variationSelect.trigger('change.select2');
            })
            .catch(() => {
                variationSelect.html('<option value="">Error loading</option>').trigger('change.select2');
            });
    }
  }
</script>
@endsection