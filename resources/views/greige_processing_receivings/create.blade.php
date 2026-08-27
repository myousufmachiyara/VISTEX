@extends('layouts.app')

@section('title', 'Greige Processing Receiving | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('greige_processing_receivings.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Greige Processing Receiving</h2>
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
              <label>Processing PO # <span class="text-danger">*</span></label>
              <select name="greige_processing_order_id" id="order_select" class="form-control select2-js" required>
                <option value="">Select PO / Request Number</option>
                @foreach ($orders as $order)
                  <option value="{{ $order->id }}">{{ $order->gppo_no }} — {{ $order->vendor->name ?? '' }} — {{ $order->greigeProduct->name ?? '' }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor Challan # <span class="text-danger">*</span></label>
              <input type="text" name="vendor_challan_no" class="form-control" required>
              <small class="text-muted">Vendor's own delivery reference — required.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receiving Date</label>
              <input type="date" name="receiving_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">
            Select a Processing PO to see its item details.
          </div>

          <div id="orderDetails" class="alert alert-secondary py-2" style="display:none"></div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Grade</th>
                  <th>Quantity</th>
                  <th>Rate</th>
                  <th>Multiplier</th>
                  <th>Amount</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody"></tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="text-end fw-bold">Total Processing Charge:</td>
                  <td class="fw-bold" id="calcTotal">0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Another Grade Line
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Receiving</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  const gradeMultipliers = { 'A': 1.00, 'B': 0.50, 'C': 0.00, 'CP': 0.00, 'Sampling': 0.00 };
  let orderProduct = null;
  let orderRate = 0;
  let rowIndex = 0;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#order_select').on('change', function () {
    const orderId = $(this).val();
    $('#itemsBody').empty();
    rowIndex = 0;
    recalcTotal();

    if (!orderId) {
      $('#itemsSection, #orderDetails').hide();
      $('#loadingMsg').show().text('Select a Processing PO to see its item details.');
      return;
    }

    fetch(`/greige-processing-receivings/order-items/${orderId}`)
      .then(res => res.json())
      .then(data => {
        orderProduct = { id: data.product_id, name: data.product_name };
        orderRate = data.rate;

        $('#orderDetails').show().html(`
          <strong>${data.order_no}</strong> — ${data.vendor_name} — ${data.product_name}<br>
          Planned: ${data.quantity_planned} | Requested so far: ${data.quantity_requested} | Remaining: ${data.quantity_remaining} | Base Rate: ${data.rate}
        `);

        $('#loadingMsg').hide();
        $('#itemsSection').show();
        addRow();
      });
  });

  function addRow() {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td>${orderProduct.name}<input type="hidden" name="items[${idx}][product_id]" value="${orderProduct.id}"></td>
        <td>
          <select name="items[${idx}][quality_grade]" class="form-control grade-select">
            <option value="A">A (Full Price)</option>
            <option value="B">B (50% Price)</option>
            <option value="C">C (No Charge)</option>
            <option value="CP">CP - Cut Piece (No Charge)</option>
            <option value="Sampling">Sampling (No Charge)</option>
          </select>
        </td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][rate]" class="form-control rate-input" step="any" min="0" value="${orderRate}"></td>
        <td class="multiplier-cell">1.00</td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
  }

  $('#addRowBtn').on('click', addRow);

  $(document).on('change', '.grade-select', function () {
    recalcLine($(this).closest('tr'));
  });
  $(document).on('input', '.qty-input, .rate-input', function () {
    recalcLine($(this).closest('tr'));
  });

  function recalcLine(row) {
    const grade = row.find('.grade-select').val();
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    const multiplier = gradeMultipliers[grade] ?? 0;
    const amount = qty * rate * multiplier;

    row.find('.multiplier-cell').text(multiplier.toFixed(2));
    row.find('.amount-cell').text(amount.toFixed(2));
    recalcTotal();
  }

  function recalcTotal() {
    let total = 0;
    $('.item-row').each(function () {
      total += parseFloat($(this).find('.amount-cell').text()) || 0;
    });
    $('#calcTotal').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
      recalcTotal();
    }
  });
</script>
@endsection