@extends('layouts.app')

@section('title', 'Greige Return | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('greige_returns.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Greige Return</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Greige Receive (GRN) <span class="text-danger">*</span></label>
              <select name="greige_receive_id" id="grn_select" class="form-control select2-js" required>
                <option value="">Select GRN</option>
                @foreach ($receivings as $r)
                  <option value="{{ $r->id }}">{{ $r->receiving_no }} — {{ $r->yarnToGreigeOrder->cpo->vendor->name ?? '' }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-12 mb-3">
              <label>Reason</label>
              <textarea name="reason" class="form-control" rows="2" placeholder="Defect details..."></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">Select a GRN to see its greige outputs.</div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered">
              <thead><tr><th>Product</th><th>Received Qty</th><th>Quantity Returning</th></tr></thead>
              <tbody id="itemsBody"></tbody>
            </table>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Return</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  const receivingsData = @json($receivings->map(fn($r) => [
      'id' => $r->id,
      'outputs' => $r->outputs->map(fn($o) => [
          'product_id' => $o->greige_product_id,
          'product_name' => $o->greigeProduct->name ?? '',
          'quantity' => (float) $o->quantity_output,
      ]),
  ]));

  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#grn_select').on('change', function () {
    const id = parseInt($(this).val());
    $('#itemsBody').empty();

    const grn = receivingsData.find(r => r.id === id);
    if (!grn || grn.outputs.length === 0) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('No greige outputs found for this GRN.');
      return;
    }

    $('#loadingMsg').hide();
    $('#itemsSection').show();

    grn.outputs.forEach((o, idx) => {
      $('#itemsBody').append(`
        <tr>
          <td>${o.product_name}<input type="hidden" name="items[${idx}][product_id]" value="${o.product_id}"></td>
          <td>${o.quantity}</td>
          <td><input type="number" name="items[${idx}][quantity]" class="form-control" value="0" step="any" min="0" max="${o.quantity}"></td>
        </tr>
      `);
    });
  });
</script>
@endsection