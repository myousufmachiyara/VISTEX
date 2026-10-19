@extends('layouts.app')
@section('title', 'Log Challan')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('challans.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="entry_type" id="entry_type" value="{{ old('entry_type', 'po') }}">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Log Received Challan (Gate)</h2></header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="btn-group mb-3" role="group">
          <button type="button" class="btn entry-btn" data-type="po">Against Approved PO</button>
          <button type="button" class="btn entry-btn" data-type="direct">Without PO</button>
        </div>

        {{-- ── Against PO ── --}}
        <div id="poFields">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Purchase Order <span class="text-danger">*</span></label>
              <select name="purchase_order_id" id="po_select" class="form-control select2-js">
                <option value="">Select PO</option>
                @foreach($orders as $o)
                  <option value="{{ $o->id }}" @selected(old('purchase_order_id', $preselect) == $o->id)>{{ $o->order_no }} — {{ $o->vendor->name ?? '' }} ({{ $o->category->name ?? '' }}, {{ ucfirst($o->type) }}) — {{ $o->items_summary }}</option>
                @endforeach
              </select>
              <small class="text-muted">Only approved POs appear here. Weaving/processing POs appear once material has been issued.</small>
            </div>
            <div class="col-md-3 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control" value="{{ old('received_date', date('Y-m-d')) }}"></div>
            <div class="col-md-3 mb-3"><label>Vendor's Challan #</label><input type="text" name="vendor_challan_no" class="form-control" value="{{ old('vendor_challan_no') }}"></div>
          </div>

          <table class="table table-bordered" id="gateItems" style="display:none">
            <thead><tr><th>Item</th><th class="text-end" width="16%">Outstanding</th><th width="14%">Bags / Packs</th><th width="22%">Counted at Gate</th></tr></thead>
            <tfoot><tr class="fw-bold"><td colspan="2" class="text-end">Total bags / packs</td><td id="packsTotal">0</td><td></td></tr></tfoot>
            <tbody></tbody>
          </table>

          <div class="form-check mb-2">
            <input type="hidden" name="has_objection" value="0">
            <input class="form-check-input" type="checkbox" name="has_objection" value="1" id="has_objection" @checked(old('has_objection'))>
            <label class="form-check-label" for="has_objection">Receive with objection (short, damaged, wrong item…)</label>
          </div>
          <div class="mb-3" id="objectionBox" style="display:none">
            <textarea name="objection_remarks" class="form-control" rows="2" placeholder="What is wrong?">{{ old('objection_remarks') }}</textarea>
          </div>
        </div>

        {{-- ── Without PO ── --}}
        <div id="directFields" style="display:none">
          <div class="alert alert-secondary py-2">For purchases made without a PO. The category incharge decides stock vs. expense when reviewing.</div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-control">
                <option value="">Select Category</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3"><label>Vendor / Shop Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}"></div>
            <div class="col-md-4 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control direct-date" value="{{ old('received_date', date('Y-m-d')) }}" disabled></div>
          </div>
          <table class="table table-bordered">
            <thead><tr><th>Description</th><th width="12%">Qty</th><th width="12%">Unit</th><th width="15%">Unit Price</th><th width="15%" class="text-end">Amount</th><th></th></tr></thead>
            <tbody id="directItemsBody"></tbody>
          </table>
          <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addDirectRowBtn">Add Item</button>
        </div>

        @include('challans._transport', ['challan' => null])

        <div class="row">
          <div class="col-md-6 mb-3"><label>Photo(s) of Challan <span class="text-danger">*</span></label><input type="file" name="challan_images[]" class="form-control" accept="image/*" multiple capture="environment" required></div>
          <div class="col-md-6 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1">{{ old('remarks') }}</textarea></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Log Challan</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(function () {
    $('.select2-js').select2({ width: '100%' });

    function setEntry(type) {
      $('#entry_type').val(type);
      $('.entry-btn').removeClass('btn-primary').addClass('btn-outline-primary');
      $(`.entry-btn[data-type=${type}]`).removeClass('btn-outline-primary').addClass('btn-primary');
      const direct = type === 'direct';
      $('#directFields').toggle(direct);
      $('#poFields').toggle(!direct);
      // only one received_date should submit
      $('#poFields input[name=received_date]').prop('disabled', direct);
      $('.direct-date').prop('disabled', !direct);
      $('#poFields :input').not('[name=received_date]').prop('disabled', direct);
      $('#directFields :input').not('.direct-date').prop('disabled', !direct);
      if (direct && !$('#directItemsBody tr').length) addDirectRow();
    }
    $('.entry-btn').on('click', function () { setEntry($(this).data('type')); });
    setEntry($('#entry_type').val() || 'po');

    $('#has_objection').on('change', function () { $('#objectionBox').toggle(this.checked); }).trigger('change');

    $('#po_select').on('change', function () {
      const id = $(this).val();
      const body = $('#gateItems tbody').empty();
      if (!id) { $('#gateItems').hide(); return; }
      fetch(`{{ url('challans/po-items') }}/${id}`).then(r => r.json()).then(rows => {
        if (!rows.length) body.append('<tr><td colspan="4" class="text-muted">Nothing outstanding on this PO.</td></tr>');
        rows.forEach((r, i) => body.append(`
          <tr>
            <td>${r.product_name}
              <input type="hidden" name="items[${i}][purchase_order_item_id]" value="${r.purchase_order_item_id ?? ''}">
              <input type="hidden" name="items[${i}][product_id]" value="${r.product_id ?? ''}">
              <input type="hidden" name="items[${i}][expected_qty]" value="${r.quantity}">
            </td>
            <td class="text-end">${r.quantity} ${r.unit || ''}${r.held > 0 ? `<div class="small text-warning">${r.held} on challans awaiting inspection</div>` : ''}${r.packs_outstanding !== null && r.packs_outstanding !== undefined ? `<div class="small text-muted">${r.packs_outstanding} bags${r.qty_per_pack > 1 ? ' × ' + r.qty_per_pack : ''}</div>` : ''}</td>
            <td><input type="number" name="items[${i}][packs]" class="form-control packs" step="any" min="0" data-per="${r.qty_per_pack || ''}" value="${r.packs_outstanding ?? ''}" placeholder="${r.qty_per_pack ? r.qty_per_pack + ' per pack' : ''}"></td>
            <td>${r.graded
              ? `<div class="input-group input-group-sm grade-row">
                   <span class="input-group-text">A</span><input type="number" name="items[${i}][qty_a]" class="form-control g" step="any" min="0" value="${r.quantity}">
                   <span class="input-group-text">B</span><input type="number" name="items[${i}][qty_b]" class="form-control g" step="any" min="0" value="0">
                   <span class="input-group-text">C</span><input type="number" name="items[${i}][qty_c]" class="form-control g" step="any" min="0" value="0">
                 </div>
                 <input type="hidden" name="items[${i}][received_qty]" class="g-total" value="${r.quantity}" data-max="${r.max ?? ''}">
                 <small class="text-muted g-sum">Total ${r.quantity} m</small>`
              : `<input type="number" name="items[${i}][received_qty]" class="form-control rq" step="any" min="0" max="${r.max ?? ''}" value="${r.quantity}" title="Cannot be more than ${r.max} open on the PO">
                 <small class="text-muted">max ${r.max} ${r.unit || ''}</small>`}</td>
          </tr>`));
        $('#gateItems thead th:last').text(rows.some(r => r.graded) ? 'Counted at Gate (A / B / C quality, m)' : 'Counted at Gate');
        $('#gateItems').show();
        packsTotal();
      });
    });
    if ($('#po_select').val()) $('#po_select').trigger('change');

    // Not more than the PO has open (A + B + C rows are hidden inputs, so check them here)
    $('#gateItems').closest('form').on('submit', function (e) {
      let over = null;
      $('#gateItems .g-total').each(function () {
        const max = parseFloat($(this).data('max'));
        if (!isNaN(max) && parseFloat(this.value) > max + 0.0005) over = max;
      });
      if (over !== null) { e.preventDefault(); alert('Quantity is more than the PO has open (' + over + ' m). Correct the A / B / C counts.'); }
    });

    // Bags / packs: running total; with a known pack size, fill the quantity counted
    // Bags are pre-filled from the PO (purchase POs); bags ↔ quantity stay in step via the PO pack size
    function packsTotal() {
      let t = 0; $('#gateItems .packs').each(function () { t += parseFloat(this.value) || 0; });
      $('#packsTotal').text(Math.round(t * 100) / 100);
    }
    $(document).on('input', '#gateItems .packs', function () {
      const per = parseFloat($(this).data('per')), n = parseFloat(this.value);
      if (per && n >= 0) $(this).closest('tr').find('.rq').val(Math.round(n * per * 1000) / 1000);
      packsTotal();
    });
    $(document).on('input', '#gateItems .rq', function () {
      const p = $(this).closest('tr').find('.packs'), per = parseFloat(p.data('per')), q = parseFloat(this.value);
      if (per && q >= 0) { p.val(Math.round(q / per * 100) / 100); packsTotal(); }
    });

    // A + B + C = meters received
    $(document).on('input', '.grade-row .g', function () {
      const td = $(this).closest('td');
      const total = td.find('.g').toArray().reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
      td.find('.g-total').val(Math.round(total * 1000) / 1000);
      td.find('.g-sum').text('Total ' + (Math.round(total * 1000) / 1000) + ' m');
    });

    let idx = 0;
    function addDirectRow() {
      const i = idx++;
      $('#directItemsBody').append(`
        <tr>
          <td><input type="text" name="direct_items[${i}][description]" class="form-control" required></td>
          <td><input type="number" name="direct_items[${i}][quantity]" class="form-control d-qty" step="any" min="0.001" value="1"></td>
          <td><input type="text" name="direct_items[${i}][unit]" class="form-control" placeholder="pcs"></td>
          <td><input type="number" name="direct_items[${i}][unit_price]" class="form-control d-price" step="any" min="0" value="0"></td>
          <td class="d-amount text-end">0.00</td>
          <td><button type="button" class="btn btn-sm btn-outline-danger d-remove">&times;</button></td>
        </tr>`);
    }
    $('#addDirectRowBtn').on('click', addDirectRow);
    $(document).on('input', '.d-qty, .d-price', function () {
      const row = $(this).closest('tr');
      row.find('.d-amount').text(((parseFloat(row.find('.d-qty').val()) || 0) * (parseFloat(row.find('.d-price').val()) || 0)).toFixed(2));
    });
    $(document).on('click', '.d-remove', function () { $(this).closest('tr').remove(); });
  });
</script>
@endsection
