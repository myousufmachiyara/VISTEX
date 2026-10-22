@extends('layouts.app')
@section('title', 'New Stock Movement')
@section('content')
@php
  $locJs = $locations->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'vendor_id' => $l->vendor_id, 'vendor' => optional($l->vendor)->name])->values();
  $prodJs = $products->map(fn($p) => ['id' => $p->id, 'label' => $p->name . ($p->category ? ' — ' . $p->category->name : '')])->values();
  $oldJs = ['from' => old('from_location_id'), 'to' => old('to_location_id'), 'items' => array_values(old('items', []))];
@endphp
<div class="row"><div class="col">
  <form action="{{ route('stock_movements.store') }}" method="POST" enctype="multipart/form-data" onkeydown="return event.key != 'Enter' || event.target.tagName == 'TEXTAREA';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Stock Movement / Gate Pass Request</h2></header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Movement Type <span class="text-danger">*</span></label>
            <select name="movement_type" id="movement_type" class="form-control" required>
              @foreach(\App\Models\StockMovement::TYPES as $k => $label)
                <option value="{{ $k }}" @selected(old('movement_type', $preselectPo ? 'warehouse_to_vendor' : 'warehouse_to_warehouse') === $k)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label>From <span class="text-danger">*</span></label>
            <select name="from_location_id" id="from_location" class="form-control" required></select>
          </div>
          <div class="col-md-4 mb-3">
            <label>To <span class="text-danger">*</span></label>
            <select name="to_location_id" id="to_location" class="form-control" required></select>
          </div>
          <div class="col-md-3 mb-3">
            <label>Date <span class="text-danger">*</span></label>
            <input type="date" name="movement_date" class="form-control" value="{{ old('movement_date', date('Y-m-d')) }}" required>
          </div>
          <div class="col-md-5 mb-3" id="poField">
            <label>For Processing PO <small class="text-muted">(optional)</small></label>
            <select name="purchase_order_id" id="po_select" class="form-control">
              <option value="">—</option>
              @foreach($processingPos as $po)
                <option value="{{ $po->id }}" data-vendor="{{ $po->vendor_id }}" @selected(old('purchase_order_id', $preselectPo) == $po->id)>{{ $po->order_no }} — {{ $po->vendor->name ?? '' }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="alert alert-secondary py-2 small" id="flowHint"></div>

        <h6>Items</h6>
        <div class="table-responsive">
          <table class="table table-bordered table-sm" id="itemsTable">
            <thead class="table-light"><tr><th>#</th><th style="min-width:220px">Item</th><th style="min-width:200px">Lot</th><th class="text-end">Available</th><th class="text-end" style="min-width:110px">Quantity</th><th></th></tr></thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addRowBtn"><i class="fa fa-plus"></i> Add Item</button>

        <div class="row">
          <div class="col-md-6 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea></div>
          <div class="col-md-6 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
        </div>
      </div>
      <footer class="card-footer d-flex justify-content-between">
        <a href="{{ route('stock_movements.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">Send for Approval</button>
      </footer>
    </section>
  </form>
</div></div>

<script>
$(function () {
  const locations = @json($locJs);
  const products = @json($prodJs);
  const old = @json($oldJs);
  const hints = {
    warehouse_to_warehouse: 'Source in-charge approves → gate checks the goods out → receiving warehouse in-charge confirms.',
    warehouse_to_vendor: 'Source in-charge approves → gate checks the goods out → the mill\'s location in-charge receives and enters the mill\'s lot #.',
    vendor_to_warehouse: 'Mill location in-charge approves (goods leave the mill) → our warehouse in-charge receives them.',
  };
  const esc = s => $('<div>').text(s ?? '').html();
  let idx = 0;

  function fillLocations() {
    const t = $('#movement_type').val();
    const fromVendor = t === 'vendor_to_warehouse', toVendor = t === 'warehouse_to_vendor';
    const opts = (vendor, sel) => '<option value="">Select</option>' + locations.filter(l => !!l.vendor_id === vendor)
      .map(l => `<option value="${l.id}" data-vendor="${l.vendor_id ?? ''}" ${String(sel) === String(l.id) ? 'selected' : ''}>${esc(l.vendor)}${l.name ? ' (' + esc(l.name) + ')' : ''}</option>`).join('');
    $('#from_location').html(opts(fromVendor, old.from)); $('#to_location').html(opts(toVendor, old.to));
    $('#poField').toggle(toVendor); if (!toVendor) $('#po_select').val('');
    $('#flowHint').text(hints[t]);
    $('#itemsBody tr').each(function () { loadLots($(this)); });
  }

  function addRow(r = {}) {
    const i = idx++;
    $('#itemsBody').append(`
      <tr>
        <td class="text-muted no"></td>
        <td><select name="items[${i}][product_id]" class="form-control form-control-sm product">
          <option value="">Select item</option>${products.map(p => `<option value="${p.id}" ${String(r.product_id) === String(p.id) ? 'selected' : ''}>${esc(p.label)}</option>`).join('')}
        </select></td>
        <td><select name="items[${i}][source_lot_no]" class="form-control form-control-sm lot" data-sel="${esc(r.source_lot_no)}"><option value="">Any lot (oldest first)</option></select></td>
        <td class="text-end avail text-muted">—</td>
        <td><input type="number" name="items[${i}][quantity]" class="form-control form-control-sm text-end qty" step="any" min="0" value="${r.quantity ?? ''}"></td>
        <td class="text-center"><a href="#" class="text-danger del"><i class="fa fa-times"></i></a></td>
      </tr>`);
    const tr = $('#itemsBody tr:last');
    tr.find('.product').select2({ width: '100%' });
    renumber(); loadLots(tr);
  }
  const renumber = () => $('#itemsBody .no').each((i, td) => $(td).text(i + 1));

  function loadLots(tr) {
    const loc = $('#from_location').val(), product = tr.find('.product').val();
    const lot = tr.find('.lot'), keep = lot.val() || lot.data('sel') || '';
    if (!loc || !product) { lot.html('<option value="">Any lot (oldest first)</option>'); tr.find('.avail').text('—'); return; }
    fetch(`{{ route('stock_movements.available_lots') }}?location_id=${loc}&product_id=${product}`).then(r => r.json()).then(d => {
      lot.html(`<option value="" data-qty="${d.total}">Any lot (oldest first)</option>` + d.lots.map(l =>
        `<option value="${esc(l.lot_no)}" data-qty="${l.quantity}" ${l.lot_no === keep ? 'selected' : ''}>${esc(l.lot_no)} — ${l.quantity}</option>`).join(''));
      showAvail(tr);
    });
  }
  function showAvail(tr) {
    const q = parseFloat(tr.find('.lot option:selected').data('qty'));
    tr.find('.avail').text(isNaN(q) ? '—' : q.toLocaleString('en-US', { maximumFractionDigits: 3 }));
  }

  $('#movement_type').on('change', () => { old.from = old.to = null; fillLocations(); });
  $('#from_location').on('change', () => $('#itemsBody tr').each(function () { loadLots($(this)); }));
  // Picking a processing PO picks that mill's location
  $('#po_select').on('change', function () {
    const v = $(this).find('option:selected').data('vendor');
    const loc = locations.find(l => String(l.vendor_id) === String(v));
    if (loc) $('#to_location').val(loc.id);
  });
  $(document).on('change', '.product', function () { loadLots($(this).closest('tr')); });
  $(document).on('change', '.lot', function () { showAvail($(this).closest('tr')); });
  $(document).on('click', '.del', function (e) { e.preventDefault(); if ($('#itemsBody tr').length > 1) { $(this).closest('tr').remove(); renumber(); } });
  $('#addRowBtn').on('click', () => addRow());

  fillLocations();
  (old.items.length ? old.items : [{}]).forEach(addRow);
  if ($('#po_select').val()) $('#po_select').trigger('change');
});
</script>
@endsection
