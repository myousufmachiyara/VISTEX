@extends('layouts.app')
@section('title', 'Edit ' . $challan->challan_no)
@section('content')
@php
  $po = $challan->purchaseOrder;
  $isDirect = $challan->entry_type === 'direct';
  $keep = old('keep_images', $challan->challan_images ?? []);
  $directRows = old('direct_items', $challan->directItems->map(fn($i) => [
      'description' => $i->description, 'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_price' => (float) $i->unit_price,
  ])->all());
@endphp
<div class="row"><div class="col">
  <form action="{{ route('challans.update', $challan->id) }}" method="POST" id="editForm" onkeydown="return event.key != 'Enter' || event.target.tagName == 'TEXTAREA';" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Edit {{ $challan->challan_no }}</h2>
        <span class="badge bg-{{ $challan->status_badge }}">{{ $challan->status_label }}</span>
      </header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <div class="alert alert-secondary py-2 small">A challan can be edited until the category incharge inspects it. The incharge is notified of the change.</div>

        @if($isDirect)
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-control" required>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $challan->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3"><label>Vendor / Shop Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" class="form-control" required maxlength="191" value="{{ old('vendor_name', $challan->direct_vendor_name) }}"></div>
            <div class="col-md-4 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control" required value="{{ old('received_date', $challan->received_date->format('Y-m-d')) }}"></div>
          </div>

          <table class="table table-bordered">
            <thead><tr><th>Description</th><th width="12%">Qty</th><th width="12%">Unit</th><th width="15%">Unit Price</th><th width="15%" class="text-end">Amount</th><th></th></tr></thead>
            <tbody id="directItemsBody"></tbody>
          </table>
          <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addDirectRowBtn">Add Item</button>
        @else
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Purchase Order</label>
              <input type="text" class="form-control" readonly value="{{ $po->order_no }} — {{ $po->vendor->name ?? '' }} ({{ $po->category->name ?? '' }}, {{ ucfirst($po->type) }})">
              <small class="text-muted">To log against a different PO, ask the incharge to reject this one and log a new challan.</small>
            </div>
            <div class="col-md-3 mb-3"><label>Received Date</label><input type="date" name="received_date" class="form-control" required value="{{ old('received_date', $challan->received_date->format('Y-m-d')) }}"></div>
            <div class="col-md-3 mb-3"><label>Vendor's Challan #</label><input type="text" name="vendor_challan_no" class="form-control" maxlength="50" value="{{ old('vendor_challan_no', $challan->vendor_challan_no) }}"></div>
          </div>

          <table class="table table-bordered">
            <thead><tr><th>Item</th><th class="text-end" width="18%">Expected</th><th width="22%">Counted at Gate</th></tr></thead>
            <tbody>
              @forelse($challan->items as $i => $item)
                <tr>
                  <td>{{ $item->display_name }}<input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}"></td>
                  <td class="text-end">{{ number_format($item->expected_qty, 3) }}</td>
                  <td><input type="number" name="items[{{ $i }}][received_qty]" class="form-control" step="any" min="0" required value="{{ old("items.$i.received_qty", (float) $item->received_qty) }}"></td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted">No gate count on this challan.</td></tr>
              @endforelse
            </tbody>
          </table>

          <div class="form-check mb-2">
            <input type="hidden" name="has_objection" value="0">
            <input class="form-check-input" type="checkbox" name="has_objection" value="1" id="has_objection" @checked(old('has_objection', $challan->has_objection))>
            <label class="form-check-label" for="has_objection">Receive with objection (short, damaged, wrong item…)</label>
          </div>
          <div class="mb-3" id="objectionBox">
            <textarea name="objection_remarks" class="form-control" rows="2" placeholder="What is wrong?">{{ old('objection_remarks', $challan->objection_remarks) }}</textarea>
            @if($challan->objection_voice_note)
              <div class="mt-2 d-flex align-items-center flex-wrap gap-3">
                <audio controls preload="metadata"><source src="{{ \App\Support\Media::url($challan->objection_voice_note) }}" type="audio/mp4"></audio>
                <div class="form-check">
                  <input type="hidden" name="remove_voice" value="0">
                  <input class="form-check-input" type="checkbox" name="remove_voice" value="1" id="remove_voice" @checked(old('remove_voice'))>
                  <label class="form-check-label" for="remove_voice">Remove voice note</label>
                </div>
              </div>
            @endif
          </div>
        @endif

        @include('challans._transport', ['challan' => $challan])

        <h6 class="mb-2 text-dark" style="font-size:.95rem;font-weight:600">Challan Photo(s) <span class="text-danger">*</span> <small class="text-muted fw-normal">untick a photo to remove it</small></h6>
        <div class="d-flex flex-wrap gap-3 mb-2">
          @foreach($challan->challan_images ?? [] as $n => $img)
            <label class="text-center photo-keep" style="cursor:pointer">
              <img src="{{ \App\Support\Media::url($img) }}" style="height:100px" class="border rounded d-block mb-1">
              <input type="checkbox" name="keep_images[]" value="{{ $img }}" @checked(in_array($img, $keep))> Keep
            </label>
          @endforeach
        </div>
        <div class="row">
          <div class="col-md-6 mb-3"><label>Add Photo(s)</label><input type="file" name="challan_images[]" id="newImages" class="form-control" accept="image/*" multiple capture="environment"></div>
          <div class="col-md-6 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1">{{ old('remarks', $challan->remarks) }}</textarea></div>
        </div>
      </div>
      <footer class="card-footer d-flex justify-content-between">
        <a href="{{ route('challans.show', $challan->id) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">Save Changes</button>
      </footer>
    </section>
  </form>
</div></div>

<script>
  $(function () {
    $('#has_objection').on('change', function () { $('#objectionBox').toggle(this.checked); }).trigger('change');

    $('#editForm').on('submit', function () {
      const kept = $('input[name="keep_images[]"]:checked').length;
      const added = ($('#newImages')[0].files || []).length;
      if (!kept && !added) { alert('Keep at least one photo or add a new one.'); return false; }
      if ($('#directItemsBody').length && !$('#directItemsBody tr').length) { alert('Add at least one item.'); return false; }
    });

    @if($isDirect)
      const rows = @json(array_values($directRows));
      let idx = 0;
      const esc = v => $('<div>').text(v ?? '').html();
      function addDirectRow(r = {}) {
        const i = idx++;
        const amount = ((parseFloat(r.quantity) || 0) * (parseFloat(r.unit_price) || 0)).toFixed(2);
        $('#directItemsBody').append(`
          <tr>
            <td><input type="text" name="direct_items[${i}][description]" class="form-control" required value="${esc(r.description)}"></td>
            <td><input type="number" name="direct_items[${i}][quantity]" class="form-control d-qty" step="any" min="0.001" required value="${r.quantity ?? 1}"></td>
            <td><input type="text" name="direct_items[${i}][unit]" class="form-control" placeholder="pcs" value="${esc(r.unit)}"></td>
            <td><input type="number" name="direct_items[${i}][unit_price]" class="form-control d-price" step="any" min="0" required value="${r.unit_price ?? 0}"></td>
            <td class="d-amount text-end">${amount}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger d-remove">&times;</button></td>
          </tr>`);
      }
      rows.forEach(addDirectRow);
      if (!rows.length) addDirectRow();
      $('#addDirectRowBtn').on('click', () => addDirectRow());
      $(document).on('input', '.d-qty, .d-price', function () {
        const row = $(this).closest('tr');
        row.find('.d-amount').text(((parseFloat(row.find('.d-qty').val()) || 0) * (parseFloat(row.find('.d-price').val()) || 0)).toFixed(2));
      });
      $(document).on('click', '.d-remove', function () { $(this).closest('tr').remove(); });
    @endif
  });
</script>
@endsection
