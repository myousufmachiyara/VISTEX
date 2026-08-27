@extends('layouts.app')

@section('title', 'Reprocessing Issue | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('reprocessing_issues.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Reprocessing Issue (FOC)</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-5 mb-3">
              <label>Original GRN (Greige Processing Receiving) <span class="text-danger">*</span></label>
              <select name="greige_processing_receiving_id" id="grn_select" class="form-control select2-js" required>
                <option value="">Select GRN</option>
                @foreach ($receivings as $r)
                  <option value="{{ $r->id }}">{{ $r->receiving_no }} — Challan: {{ $r->vendor_challan_no }} — {{ $r->greigeProcessingOrder->vendor->name ?? '' }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Issue Date</label>
              <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-4 mb-3">
              <label>Attachments <span class="text-muted">(email/photo evidence)</span></label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-12 mb-3">
              <label>Reason for Reprocessing <span class="text-danger">*</span></label>
              <textarea name="issue_reason" class="form-control" rows="2" required placeholder="Describe the quality issue found..."></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">Select a GRN to see its items.</div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered">
              <thead><tr><th>Product</th><th>Received Qty</th><th>Quantity to Reprocess</th></tr></thead>
              <tbody id="itemsBody"></tbody>
            </table>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Issue</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  const receivingsData = @json($receivings->map(fn($r) => [
      'id' => $r->id,
      'items' => $r->items->map(fn($i) => [
          'product_id' => $i->product_id,
          'product_name' => $i->product->name ?? '',
          'quantity' => (float) $i->quantity,
      ]),
  ]));

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#grn_select').on('change', function () {
    const id = parseInt($(this).val());
    $('#itemsBody').empty();

    const grn = receivingsData.find(r => r.id === id);
    if (!grn || grn.items.length === 0) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('No items found for this GRN.');
      return;
    }

    $('#loadingMsg').hide();
    $('#itemsSection').show();

    grn.items.forEach((item, idx) => {
      $('#itemsBody').append(`
        <tr>
          <td>${item.product_name}<input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}"></td>
          <td>${item.quantity}</td>
          <td><input type="number" name="items[${idx}][quantity]" class="form-control" value="0" step="any" min="0" max="${item.quantity}"></td>
        </tr>
      `);
    });
  });
</script>
@endsection