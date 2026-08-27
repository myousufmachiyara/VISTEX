@extends('layouts.app')
@section('title', 'Purchase Receiving | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_receivings.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Purchase Receiving (GRN)</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="alert alert-warning py-2">
          Partial receiving is allowed. Rates are locked to the PO — you cannot change them. If what arrived
          doesn't match at all, don't enter it — <a href="#" id="objectLink">report an issue</a> instead.
          This receiving needs category in-charge approval before it affects stock or accounts.
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Purchase Order <span class="text-danger">*</span></label>
            <select name="purchase_order_id" id="po_select" class="form-control select2-js" required>
              <option value="">Select Purchase Order</option>
              @foreach ($purchaseOrders as $po)
                <option value="{{ $po->id }}" data-location-id="{{ $po->drop_off_location_id }}" data-location-name="{{ $po->dropOffLocation->name ?? '' }}">
                  {{ $po->order_no }} — {{ $po->vendor->name ?? '' }} ({{ $po->status }})
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label>Receiving Location</label>
            <input type="text" id="location_display" class="form-control" disabled>
            <input type="hidden" name="location_id" id="location_id">
          </div>
          <div class="col-md-2 mb-3">
            <label>Receiving Date</label>
            <input type="date" name="receiving_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-md-3 mb-3">
            <label>Vendor Challan # <span class="text-danger">*</span></label>
            <input type="text" name="vendor_challan_no" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label>Attachments <span class="text-danger">*</span></label>
            <input type="file" name="attachments[]" class="form-control" multiple required>
          </div>
          <div class="col-md-6 mb-3">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="1"></textarea>
          </div>
        </div>

        <div id="historyPanel" style="display:none" class="mb-3">
          <h6>Previous Receivings Against This PO</h6>
          <table class="table table-sm table-bordered"><thead><tr><th>GRN #</th><th>Date</th><th>Status</th><th>Items</th></tr></thead><tbody id="historyBody"></tbody></table>
        </div>

        <div class="alert alert-info py-2" id="loadingMsg">Select a Purchase Order to see outstanding items.</div>

        <div class="table-responsive mb-3" id="itemsSection" style="display:none">
          <table class="table table-bordered">
            <thead><tr><th>Product</th><th>Ordered</th><th>Already Received</th><th>Outstanding</th><th width="18%">Receiving Now</th></tr></thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success" id="submitBtn" disabled>Save Receiving</button></footer>
    </section>
  </form>
</div></div>

<script>
  let currentPoId = null;
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#po_select').on('change', function () {
    const poId = $(this).val();
    currentPoId = poId;
    $('#itemsBody, #historyBody').empty();
    $('#submitBtn').prop('disabled', true);

    const opt = $(this).find('option:selected');
    $('#location_id').val(opt.data('location-id') || '');
    $('#location_display').val(opt.data('location-name') || '');

    if (!poId) { $('#itemsSection, #historyPanel').hide(); $('#loadingMsg').show().text('Select a Purchase Order to see outstanding items.'); return; }

    $('#loadingMsg').show().text('Loading...');

    fetch(`/purchase-receivings/history/${poId}`).then(r => r.json()).then(history => {
      if (history.length > 0) {
        $('#historyPanel').show();
        history.forEach(h => $('#historyBody').append(`<tr><td>${h.receiving_no}</td><td>${h.date}</td><td>${h.status}</td><td>${h.items}</td></tr>`));
      } else { $('#historyPanel').hide(); }
    });

    fetch(`/purchase-receivings/outstanding/${poId}`)
      .then(res => { if (res.status === 403) throw new Error('You are not authorized to receive this PO.'); return res.json(); })
      .then(data => {
        if (data.length === 0) { $('#itemsSection').hide(); $('#loadingMsg').show().text('This PO has been fully received.'); return; }
        $('#loadingMsg').hide(); $('#itemsSection').show();
        const tbody = $('#itemsBody');
        data.forEach((item, idx) => {
          tbody.append(`
            <tr>
              <td>${item.product_name}
                <input type="hidden" name="items[${idx}][purchase_order_item_id]" value="${item.purchase_order_item_id}">
                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
              </td>
              <td>${item.ordered}</td><td>${item.already_received}</td><td><strong>${item.outstanding}</strong></td>
              <td><input type="number" name="items[${idx}][quantity_received]" class="form-control qty-input" value="0" step="any" min="0" max="${item.outstanding}" data-outstanding="${item.outstanding}"></td>
            </tr>`);
        });
        checkSubmitEnabled();
      })
      .catch(err => { $('#itemsSection').hide(); $('#loadingMsg').show().text(err.message); });
  });

  $(document).on('input', '.qty-input', function () {
    const max = parseFloat($(this).data('outstanding'));
    let val = parseFloat($(this).val()) || 0;
    if (val > max) $(this).val(max);
    checkSubmitEnabled();
  });

  function checkSubmitEnabled() {
    let any = false;
    $('.qty-input').each(function () { if ((parseFloat($(this).val()) || 0) > 0) any = true; });
    $('#submitBtn').prop('disabled', !any);
  }

  $('#objectLink').on('click', function (e) {
    e.preventDefault();
    if (!currentPoId) { alert('Select a Purchase Order first.'); return; }
    window.location.href = `/purchase-orders/${currentPoId}/object`;
  });
</script>
@endsection