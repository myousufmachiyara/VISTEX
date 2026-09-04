@extends('layouts.app')
@section('title', 'Job / Customer Order | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('jobs.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Job / Customer Order</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <h6>Buyer Information</h6>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Customer <span class="text-danger">*</span></label>
            <select name="customer_id" id="customer_select" class="form-control select2-js" required>
              <option value="">Select Customer</option>
              @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3"><label>Buyer Name</label><input type="text" name="buyer_name" class="form-control"></div>
          <div class="col-md-4 mb-3"><label>Shipping Address</label><input type="text" name="shipping_address" class="form-control"></div>
        </div>

        <h6>Order Information</h6>
        <div class="row">
          <div class="col-md-3 mb-3"><label>Customer Order Reference</label><input type="text" name="customer_reference" class="form-control"></div>
          <div class="col-md-3 mb-3"><label>Customer PO Number</label><input type="text" name="customer_po_number" class="form-control"></div>
          <div class="col-md-3 mb-3"><label>Order Date <span class="text-danger">*</span></label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-3 mb-3"><label>Expected Delivery Date</label><input type="date" name="expected_date" class="form-control"></div>

          <div class="col-md-3 mb-3">
            <label>Payment Term <span class="text-danger">*</span></label>
            <select name="payment_term_type" id="payment_term_type" class="form-control" required>
              <option value="cash" selected>Cash</option>
              <option value="credit">Credit</option>
              <option value="pdc">PDC</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-3 mb-3" id="paymentDaysField" style="display:none">
            <label>Days</label>
            <select name="payment_term_days" class="form-control">
              <option value="30">30 days</option><option value="60">60 days</option><option value="90">90 days</option>
            </select>
          </div>
          <div class="col-md-4 mb-3" id="paymentNoteField" style="display:none">
            <label>Note</label><input type="text" name="payment_term_note" class="form-control">
          </div>

          <div class="col-md-6 mb-3"><label>Attachments <span class="text-muted">(scanned customer PO)</span></label><input type="file" name="attachments[]" class="form-control" multiple></div>
          <div class="col-md-6 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
        </div>

        <h6>Order Line Items</h6>
        <div class="table-responsive mb-3">
          <table class="table table-bordered" id="itemsTable">
            <thead>
              <tr>
                <th width="24%">SKU / Product</th><th>Unit</th><th>Quantity</th><th>Unit Price</th>
                <th>Discount %</th><th>Tax</th><th>Amount</th><th></th>
              </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
            <tfoot>
              <tr><td colspan="6" class="text-end">Subtotal:</td><td colspan="2" class="text-end" id="subtotalDisplay">0.00</td></tr>
              <tr><td colspan="6" class="text-end">Discount:</td><td colspan="2" class="text-end" id="discountDisplay">0.00</td></tr>
              <tr><td colspan="6" class="text-end">Tax:</td><td colspan="2" class="text-end" id="taxDisplay">0.00</td></tr>
              <tr class="fw-bold"><td colspan="6" class="text-end">Grand Total:</td><td colspan="2" class="text-end" id="totalDisplay">0.00</td></tr>
            </tfoot>
          </table>
          <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Item</button>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Job</button></footer>
    </section>
  </form>
</div></div>

<script>
  let rowIndex = 0;
  const units = @json($units->map(fn($u) => ['id' => $u->id, 'label' => $u->name . ' (' . $u->shortcode . ')']));
  const taxes = @json($taxes->map(fn($t) => ['id' => $t->id, 'label' => $t->name, 'rate' => $t->rate]));

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); addRow(); });

  $('#payment_term_type').on('change', function () {
    const val = $(this).val();
    $('#paymentDaysField').toggle(val === 'credit' || val === 'pdc');
    $('#paymentNoteField').toggle(val === 'other');
  });

  function unitOptionsHtml() { let h = '<option value="">Unit</option>'; units.forEach(u => h += `<option value="${u.id}">${u.label}</option>`); return h; }
  function taxOptionsHtml() { let h = '<option value="">No Tax</option>'; taxes.forEach(t => h += `<option value="${t.id}" data-rate="${t.rate}">${t.label}</option>`); return h; }
  function productOptionsHtml() {
    let h = '<option value="">Select Product</option>';
    @foreach($products as $p)
      h += `<option value="{{ $p->id }}" data-unit="{{ $p->measurement_unit }}">{{ $p->name }} — {{ $p->sku }}</option>`;
    @endforeach
    return h;
  }

  function addRow() {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>${productOptionsHtml()}</select></td>
        <td><select name="items[${idx}][measurement_unit]" class="form-control unit-select">${unitOptionsHtml()}</select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0.001" value="0"></td>
        <td><input type="number" name="items[${idx}][unit_price]" class="form-control price-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][discount_pct]" class="form-control disc-input" step="any" min="0" max="100" value="0"></td>
        <td><select name="items[${idx}][tax_id]" class="form-control tax-select">${taxOptionsHtml()}</select></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }
  $('#addRowBtn').on('click', addRow);

  $(document).on('change', '.product-select', function () {
    const row = $(this).closest('tr');
    row.find('.unit-select').val($(this).find('option:selected').data('unit'));

    const customerId = $('#customer_select').val();
    const productId = $(this).val();
    if (!customerId || !productId) return;

    fetch(`{{ route('jobs.suggest_rate') }}?customer_id=${customerId}&product_id=${productId}`)
      .then(r => r.json()).then(data => { if (data.rate !== null) row.find('.price-input').val(data.rate).trigger('input'); });
  });

  $(document).on('input change', '.qty-input, .price-input, .disc-input, .tax-select', function () { recalcRow($(this).closest('tr')); recalcTotals(); });

  function recalcRow(row) {
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const price = parseFloat(row.find('.price-input').val()) || 0;
    const discPct = parseFloat(row.find('.disc-input').val()) || 0;
    const taxRate = parseFloat(row.find('.tax-select').find('option:selected').data('rate')) || 0;

    const lineSubtotal = qty * price;
    const discountAmt = lineSubtotal * (discPct / 100);
    const afterDiscount = lineSubtotal - discountAmt;
    const taxAmt = afterDiscount * (taxRate / 100);
    row.find('.amount-cell').text((afterDiscount + taxAmt).toFixed(2));
  }

  function recalcTotals() {
    let subtotal = 0, discount = 0, tax = 0, total = 0;
    $('.item-row').each(function () {
      const qty = parseFloat($(this).find('.qty-input').val()) || 0;
      const price = parseFloat($(this).find('.price-input').val()) || 0;
      const discPct = parseFloat($(this).find('.disc-input').val()) || 0;
      const taxRate = parseFloat($(this).find('.tax-select').find('option:selected').data('rate')) || 0;
      const lineSubtotal = qty * price;
      const discountAmt = lineSubtotal * (discPct / 100);
      const afterDiscount = lineSubtotal - discountAmt;
      const taxAmt = afterDiscount * (taxRate / 100);
      subtotal += lineSubtotal; discount += discountAmt; tax += taxAmt; total += (afterDiscount + taxAmt);
    });
    $('#subtotalDisplay').text(subtotal.toFixed(2));
    $('#discountDisplay').text(discount.toFixed(2));
    $('#taxDisplay').text(tax.toFixed(2));
    $('#totalDisplay').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-row', function () { if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotals(); } });
</script>
@endsection