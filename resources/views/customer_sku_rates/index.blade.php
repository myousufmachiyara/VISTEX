@extends('layouts.app')
@section('title', 'Customer SKU Rates')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header"><h2 class="card-title">Customer SKU Rates</h2></header>

    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
          <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
            <option value="">Select Customer</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}" @selected($selectedCustomerId == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
      </form>

      @if($selectedCustomerId)
        <table class="table table-bordered table-striped">
          <thead><tr><th>SKU</th><th class="text-end">Current Rate</th><th>History</th><th>Update</th></tr></thead>
          <tbody>
            @foreach($rates as $row)
            <tr>
              <td>{{ $row['product']->name }} ({{ $row['product']->sku }})</td>
              <td class="text-end">{{ $row['rate'] !== null ? number_format($row['rate'], 2) : '—' }}</td>
              <td>
                <a href="javascript:void(0)" onclick="showHistory({{ $selectedCustomerId }}, {{ $row['product']->id }}, '{{ $row['product']->name }}')">View History</a>
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showUpdateForm({{ $selectedCustomerId }}, {{ $row['product']->id }}, '{{ $row['product']->name }}')">Update Rate</button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted">Select a customer to view/manage SKU rates.</p>
      @endif
    </div>
  </section>

  {{-- Update Rate Modal --}}
  <div id="updateRateModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" action="{{ route('customer_sku_rates.store') }}">
        @csrf
        <header class="card-header"><h2 class="card-title">Update Rate — <span id="urm_product_name"></span></h2></header>
        <div class="card-body">
          <input type="hidden" name="customer_id" id="urm_customer_id">
          <input type="hidden" name="product_id" id="urm_product_id">
          <div class="mb-2"><label>New Rate</label><input type="number" name="rate" class="form-control" step="any" min="0" required></div>
          <div class="mb-2"><label>Effective Date</label><input type="date" name="effective_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="mb-2"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
        </div>
        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- History Modal --}}
  <div id="historyModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Rate History — <span id="hm_product_name"></span></h2></header>
      <div class="card-body">
        <table class="table table-sm table-bordered">
          <thead><tr><th>Rate</th><th>Effective Date</th><th>Remarks</th><th>By</th></tr></thead>
          <tbody id="hm_body"></tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="button" class="btn btn-default modal-dismiss">Close</button></footer>
    </section>
  </div>
</div></div>

<script>
$(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

function showUpdateForm(customerId, productId, productName) {
  $('#urm_customer_id').val(customerId);
  $('#urm_product_id').val(productId);
  $('#urm_product_name').text(productName);
  $.magnificPopup.open({ items: { src: '#updateRateModal' }, type: 'inline' });
}

function showHistory(customerId, productId, productName) {
  $('#hm_product_name').text(productName);
  $('#hm_body').html('<tr><td colspan="4">Loading...</td></tr>');

  fetch(`/customer-sku-rates/history/${customerId}/${productId}`)
    .then(r => r.json())
    .then(data => {
      if (data.length === 0) {
        $('#hm_body').html('<tr><td colspan="4" class="text-muted">No rate history yet.</td></tr>');
        return;
      }
      let html = '';
      data.forEach(row => {
        html += `<tr><td>${row.rate.toFixed(2)}</td><td>${row.effective_date}</td><td>${row.remarks ?? ''}</td><td>${row.created_by}</td></tr>`;
      });
      $('#hm_body').html(html);
    });

  $.magnificPopup.open({ items: { src: '#historyModal' }, type: 'inline' });
}
</script>
@endsection