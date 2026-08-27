@extends('layouts.app')

@section('title', 'Greige Issue | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('greige_issues.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Greige Issue (to Processing Mill)</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Processing PO <span class="text-danger">*</span></label>
              <select name="greige_processing_order_id" id="ppo_select" class="form-control select2-js" required>
                <option value="">Select Processing PO</option>
                @foreach ($orders as $order)
                  <option value="{{ $order->id }}" data-product-id="{{ $order->greige_product_id }}" data-product-name="{{ $order->greigeProduct->name ?? '' }}">
                    {{ $order->gppo_no }} — {{ $order->vendor->name ?? '' }} — {{ $order->greigeProduct->name ?? '' }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>From Location <span class="text-danger">*</span></label>
              <select name="location_id" id="location_select" class="form-control select2-js" required>
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Lot # <span class="text-muted">(from mill)</span></label>
              <input type="text" name="lot_no" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Issue Date</label>
              <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-6 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">
            Select a Processing PO and Location to see available greige.
          </div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered">
              <thead><tr><th>Product</th><th>Available</th><th>Quantity to Issue</th></tr></thead>
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
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  function checkAndRender() {
    const ppoOpt = $('#ppo_select').find('option:selected');
    const locationId = $('#location_select').val();
    const productId = ppoOpt.data('product-id');
    const productName = ppoOpt.data('product-name');

    if (!productId || !locationId) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('Select a Processing PO and Location to see available greige.');
      return;
    }

    fetch(`/greige-issues/available-stock?location_id=${locationId}&product_id=${productId}`)
      .then(res => res.json())
      .then(data => {
        $('#loadingMsg').hide();
        $('#itemsSection').show();
        $('#itemsBody').html(`
          <tr>
            <td>${productName}<input type="hidden" name="items[0][product_id]" value="${productId}"></td>
            <td>Fresh: ${data.fresh} | Leftover: ${data.leftover} | <strong>Total: ${data.total}</strong></td>
            <td><input type="number" name="items[0][quantity]" class="form-control" step="any" min="0.001" max="${data.total}" value="0"></td>
          </tr>
        `);
      });
  }

  $('#ppo_select, #location_select').on('change', checkAndRender);
</script>
@endsection