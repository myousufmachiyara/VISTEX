@extends('layouts.app')
@section('title', 'Log Challan')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('challans.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Log Received Challan</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <label class="mb-2 d-block">What is this receiving for?</label>
        <div class="btn-group mb-3" role="group" id="typeButtons">
          <button type="button" class="btn btn-outline-primary type-btn" data-type="purchase">🛒<br>Purchasing</button>
          <button type="button" class="btn btn-outline-primary type-btn" data-type="weaving">🧵<br>Weaving</button>
          <button type="button" class="btn btn-outline-primary type-btn" data-type="processing">🏭<br>Processing</button>
          <button type="button" class="btn btn-outline-secondary type-btn" data-type="direct">📦<br>Other (No PO)</button>
        </div>
        <input type="hidden" name="entry_type" id="entry_type" required>

        <div id="poBasedFields" style="display:none">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select id="vendor_select" class="form-control select2-js">
                <option value="">Select Vendor</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label>Purchase Order <span class="text-danger">*</span></label>
              <select name="purchase_order_id" id="po_select" class="form-control select2-js" disabled required>
                <option value="">Select Vendor First</option>
              </select>
              <small class="text-muted" id="poHint"></small>
            </div>
            <div class="col-md-4 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          </div>
        </div>

        <div id="directFields" style="display:none">
          <div class="alert alert-secondary py-2">For items received without a Purchase Order — stationery, food, office supplies, etc.</div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="direct_vendor_id" class="form-control select2-js">
                <option value="">Select Vendor</option>
                @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3"><label>Received Date</label><input type="date" name="received_date_direct" class="form-control" value="{{ date('Y-m-d') }}"></div>
          </div>
          <table class="table table-bordered" id="directItemsTable">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Expense Account</th><th>Amount</th><th></th></tr></thead>
            <tbody id="directItemsBody"></tbody>
          </table>
          <button type="button" class="btn btn-outline-primary btn-sm" id="addDirectRowBtn">Add Item</button>
        </div>

        <div class="row mt-3" id="commonFields" style="display:none">
          <div class="col-md-6 mb-3"><label>Vendor's Challan #</label><input type="text" name="vendor_challan_no" class="form-control"></div>
          <div class="col-md-6 mb-3"><label>Photo(s) of Challan <span class="text-danger">*</span></label><input type="file" name="challan_images[]" class="form-control" accept="image/*" multiple capture="environment"></div>
          <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Log Challan</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('.type-btn').on('click', function () {
    $('.type-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
    $(this).removeClass('btn-outline-primary').addClass('active btn-primary');
    const type = $(this).data('type');
    $('#entry_type').val(type);

    if (type === 'direct') {
      $('#poBasedFields').hide();
      $('#directFields, #commonFields').show();
    } else {
      $('#directFields').hide();
      $('#poBasedFields, #commonFields').show();
      loadVendorsForType(type);
    }
  });

  function loadVendorsForType(type) {
    $('#vendor_select').html('<option value="">Loading...</option>');
    $('#po_select').prop('disabled', true).html('<option value="">Select Vendor First</option>');
    fetch(`/challans/vendors-for-type?type=${type}`).then(r => r.json()).then(vendors => {
      let html = '<option value="">Select Vendor</option>';
      vendors.forEach(v => html += `<option value="${v.id}">${v.name}</option>`);
      $('#vendor_select').html(html).trigger('change.select2');
    });
  }

  $('#vendor_select').on('change', function () {
    const vendorId = $(this).val();
    const type = $('#entry_type').val();
    $('#po_select').prop('disabled', true).html('<option value="">Loading...</option>');
    if (!vendorId) { $('#po_select').html('<option value="">Select Vendor First</option>'); return; }

    fetch(`/challans/pos-for-vendor?type=${type}&vendor_id=${vendorId}`).then(r => r.json()).then(orders => {
      let html = '<option value="">Select PO</option>';
      orders.forEach(o => {
        const disabled = o.selectable ? '' : 'disabled';
        const label = o.selectable ? o.order_no : `${o.order_no} — Pending Approval (view only)`;
        html += `<option value="${o.id}" ${disabled}>${label}</option>`;
      });
      $('#po_select').prop('disabled', false).html(html).trigger('change.select2');
      $('#poHint').text('Pending POs are shown for reference only and cannot be selected.');
    });
  });

  // Direct items rows
  let directRowIndex = 0;
  function addDirectRow() {
    const idx = directRowIndex++;
    const row = $(`
      <tr>
        <td><input type="text" name="direct_items[${idx}][description]" class="form-control" required></td>
        <td><input type="number" name="direct_items[${idx}][quantity]" class="form-control direct-qty" step="any" min="0.001" value="1"></td>
        <td><input type="number" name="direct_items[${idx}][unit_price]" class="form-control direct-price" step="any" min="0" value="0"></td>
        <td><select name="direct_items[${idx}][expense_account_id]" class="form-control">@foreach($vendors->first()?->expenseAccounts ?? [] as $a)<option value="{{ $a->id ?? '' }}"></option>@endforeach</select></td>
        <td class="direct-amount text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-direct-row">&times;</button></td>
      </tr>
    `);
    $('#directItemsBody').append(row);
  }
  $('#addDirectRowBtn').on('click', addDirectRow);
  $(document).on('input', '.direct-qty, .direct-price', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.direct-qty').val()) || 0;
    const price = parseFloat(row.find('.direct-price').val()) || 0;
    row.find('.direct-amount').text((qty * price).toFixed(2));
  });
  $(document).on('click', '.remove-direct-row', function () { $(this).closest('tr').remove(); });
</script>
@endsection