@extends('layouts.app')
@section('title', $issuance ? 'Edit ' . $issuance->issue_no : 'New Issuance')
@section('content')
@php $editing = (bool) $issuance; @endphp
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">{{ $editing ? 'Edit ' . $issuance->issue_no : 'New Issuance' }}</h2></header>
    <div class="card-body">
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
      @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif

      {{-- Step 1: what is being issued --}}
      @unless($editing)
      <label class="d-block mb-2"><strong>What are you issuing?</strong></label>
      <div class="row mb-3">
        @foreach($types as $key => $t)
        <div class="col-md-3 mb-2">
          @if($t['active'])
            <a href="{{ route('issuances.create', ['type' => $key]) }}" class="card h-100 text-decoration-none {{ $type === $key ? 'border-primary border-2' : '' }}">
              <div class="card-body py-2">
                <strong class="{{ $type === $key ? 'text-primary' : 'text-dark' }}">{{ $t['label'] }}</strong>
                <div class="small text-muted">against {{ $t['against'] }}</div>
              </div>
            </a>
          @else
            <div class="card h-100 bg-light" title="{{ $t['help'] }}">
              <div class="card-body py-2">
                <strong class="text-muted">{{ $t['label'] }}</strong> <span class="badge bg-secondary">Locked</span>
                <div class="small text-muted">Pending client discussion</div>
              </div>
            </div>
          @endif
        </div>
        @endforeach
      </div>
      @endunless

      @if($type)
      <form action="{{ $editing ? route('issuances.update', $issuance->id) : route('issuances.store') }}" method="POST" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';" id="issForm">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="issue_type" value="{{ $type }}">
        <div class="alert alert-light border small">{{ $types[$type]['help'] }}</div>

        <div class="row">
          <div class="col-md-5 mb-3">
            <label>{{ $types[$type]['against'] }} <span class="text-danger">*</span></label>
            <select name="purchase_order_id" id="po_select" class="form-control select2-js" {{ $editing ? 'disabled' : '' }} required>
              <option value="">Select</option>
              @foreach($orders as $o)
                <option value="{{ $o->id }}" @selected(old('purchase_order_id', $preselectPo) == $o->id)>{{ $o->order_no }} — {{ $o->vendor->name ?? '' }} ({{ $o->status_label }})</option>
              @endforeach
            </select>
            @if($editing)<input type="hidden" name="purchase_order_id" value="{{ $issuance->purchase_order_id }}">@endif
            @if($orders->isEmpty())<small class="text-muted">No approved {{ strtolower($types[$type]['against']) }}s are open.</small>@endif
          </div>
          <div class="col-md-3 mb-3">
            <label>Issue From (Warehouse) <span class="text-danger">*</span></label>
            <select name="source_location_id" id="source_select" class="form-control" required>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" @selected(old('source_location_id', $issuance->source_location_id ?? null) == $w->id)>{{ $w->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 mb-3"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', $issuance?->issue_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required></div>
          @if($type === 'greige_processing')
          <div class="col-md-2 mb-3">
            <label>Send To (Mill) <span class="text-danger">*</span></label>
            <select name="destination_location_id" id="dest_select" class="form-control" required data-selected="{{ old('destination_location_id', $issuance->destination_location_id ?? '') }}"></select>
          </div>
          @endif
        </div>

        <div id="linesWrap" style="display:none">
          <table class="table table-bordered align-middle" id="linesTable">
            <thead id="linesHead"></thead>
            <tbody></tbody>
          </table>
          @if($type === 'greige_processing')
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addGreigeRow">Add Greige</button>
          @endif
        </div>

        <div class="row">
          <div class="col-md-8 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1">{{ old('remarks', $issuance->remarks ?? '') }}</textarea></div>
          <div class="col-md-4 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
        </div>

        <div class="text-end"><button type="submit" class="btn btn-success">{{ $editing ? 'Update Issuance' : 'Post Issuance' }}</button></div>
      </form>
      @endif
    </div>
  </section>
</div></div>

@if($type)
@php
  $existingItems = $editing
      ? $issuance->items->map(fn($i) => ['product_id' => $i->product_id, 'quantity' => (float) $i->quantity, 'lot_no' => $i->lot_no, 'purchase_order_item_id' => $i->purchase_order_item_id])->values()
      : [];
@endphp
<script>
$(function () {
  $('.select2-js').select2({ width: '100%' });
  const TYPE = @json($type);
  const EXCLUDE = @json($issuance->id ?? null);
  const EXISTING = @json($existingItems);
  const OLD = @json(old('items', []));
  let greige = null, rowIdx = 0;

  function lotOptions(lots, selected) {
    let h = '<option value="">Any lot</option>';
    Object.entries(lots || {}).forEach(([lot, q]) => h += `<option value="${lot}" ${lot === selected ? 'selected' : ''}>${lot} (${(+q).toFixed(3)})</option>`);
    return h;
  }

  function load() {
    const po = $('#po_select').val(), src = $('#source_select').val();
    if (!po) { $('#linesWrap').hide(); return; }
    const q = new URLSearchParams({ source_location_id: src || '' }); if (EXCLUDE) q.set('exclude', EXCLUDE);
    fetch(`{{ url('issuances/po-details') }}/${po}?` + q).then(r => r.json()).then(d => {
      const body = $('#linesTable tbody').empty();
      const prefill = OLD.length ? OLD : EXISTING;
      if (d.type === 'weaving') {
        $('#linesHead').html('<tr><th>Yarn</th><th class="text-end">Required</th><th class="text-end">Issued</th><th class="text-end">Remaining</th><th class="text-end">In Warehouse</th><th width="18%">Lot</th><th width="16%">Issue Qty</th></tr>');
        d.lines.forEach((l, i) => {
          const pre = prefill.find(p => +p.product_id === +l.product_id) || {};
          body.append(`<tr>
            <td>${l.product_name} <small class="text-muted">(${l.role})</small><input type="hidden" name="items[${i}][product_id]" value="${l.product_id}"></td>
            <td class="text-end">${l.required.toFixed(3)}</td><td class="text-end">${l.issued.toFixed(3)}</td>
            <td class="text-end"><strong>${l.remaining.toFixed(3)}</strong></td>
            <td class="text-end ${l.available < l.remaining ? 'text-danger' : ''}">${l.available.toFixed(3)}</td>
            <td><select name="items[${i}][lot_no]" class="form-control">${lotOptions(l.lots, pre.lot_no)}</select></td>
            <td><input type="number" name="items[${i}][quantity]" class="form-control" step="any" min="0" max="${Math.max(l.remaining, 0)}" value="${pre.quantity ?? ''}" placeholder="max ${Math.max(l.remaining,0).toFixed(3)}"></td>
          </tr>`);
        });
        if (!d.lines.length) body.append('<tr><td colspan="7" class="text-muted">This PO has no warp/weft yarn set.</td></tr>');
      } else {
        greige = d;
        const dest = $('#dest_select'); const sel = dest.data('selected');
        dest.html(d.destinations.length ? d.destinations.map(l => `<option value="${l.id}" ${+l.id === +sel ? 'selected' : ''}>${l.name}</option>`).join('') : '<option value="">No location set up for this mill</option>');
        $('#linesHead').html('<tr><th>Greige</th><th class="text-end">In Warehouse</th><th width="20%">Lot</th><th width="22%">For PO Line</th><th width="15%">Qty</th><th></th></tr>');
        rowIdx = 0;
        (prefill.length ? prefill : [{}]).forEach(p => addGreigeRow(p));
      }
      $('#linesWrap').show();
    });
  }

  function addGreigeRow(pre = {}) {
    if (!greige) return;
    const i = rowIdx++;
    const prodOpts = '<option value="">Select greige</option>' + greige.stock.map(s => `<option value="${s.product_id}" ${+s.product_id === +pre.product_id ? 'selected' : ''}>${s.product_name}</option>`).join('');
    const poOpts = '<option value="">—</option>' + greige.po_items.map(p => `<option value="${p.id}" ${+p.id === +pre.purchase_order_item_id ? 'selected' : ''}>${p.label} (${p.quantity})</option>`).join('');
    const row = $(`<tr>
      <td><select name="items[${i}][product_id]" class="form-control g-prod">${prodOpts}</select></td>
      <td class="text-end g-avail">—</td>
      <td><select name="items[${i}][lot_no]" class="form-control g-lot"><option value="">Any lot</option></select></td>
      <td><select name="items[${i}][purchase_order_item_id]" class="form-control">${poOpts}</select></td>
      <td><input type="number" name="items[${i}][quantity]" class="form-control" step="any" min="0" value="${pre.quantity ?? ''}"></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger g-remove">&times;</button></td>
    </tr>`);
    $('#linesTable tbody').append(row);
    row.find('.g-prod').on('change', function () {
      const s = greige.stock.find(x => +x.product_id === +this.value);
      row.find('.g-avail').text(s ? s.available.toFixed(3) : '—');
      row.find('.g-lot').html(lotOptions(s ? s.lots : {}, pre.lot_no));
    }).trigger('change');
  }

  $('#po_select, #source_select').on('change', load);
  $(document).on('click', '#addGreigeRow', () => addGreigeRow());
  $(document).on('click', '.g-remove', function () { $(this).closest('tr').remove(); });
  if ($('#po_select').val()) load();
});
</script>
@endif
@endsection
