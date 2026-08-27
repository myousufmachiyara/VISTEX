@extends('layouts.app')
@section('title', 'Greige Receive | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('greige_receives.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Greige Receive (from Weaving Mill)</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Conversion PO <span class="text-danger">*</span></label>
            <select name="cpo_id" id="cpo_select" class="form-control select2-js" required>
              <option value="">Select CPO</option>
              @foreach ($cpos as $cpo)
                <option value="{{ $cpo->id }}">{{ $cpo->cpo_no }} — {{ $cpo->vendor->name ?? '' }} — {{ $cpo->item_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3"><label>Receive Date</label><input type="date" name="receive_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-2 mb-3"><label>Vendor Challan # <span class="text-danger">*</span></label><input type="text" name="vendor_challan_no" class="form-control" required></div>
          <div class="col-md-3 mb-3"><label>Attachments <span class="text-danger">*</span></label><input type="file" name="attachments[]" class="form-control" multiple required></div>
          <div class="col-md-8 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
          <div class="col-md-4 mb-3 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" name="is_final_receiving" value="1" class="form-check-input" id="finalCheck">
              <label class="form-check-label" for="finalCheck">Final Receiving — clear remaining yarn balance for this CPO</label>
            </div>
          </div>
        </div>

        <div class="alert alert-info py-2" id="yarnBalanceMsg" style="display:none"></div>

        <h6>Greige Output(s)</h6>
        <div class="table-responsive mb-3">
          <table class="table table-bordered" id="outputsTable">
            <thead><tr><th>Greige Product</th><th>Quantity</th><th>Weaving Rate / Unit</th><th>Weaving Charge</th><th></th></tr></thead>
            <tbody id="outputsBody">
              <tr class="output-row">
                <td><select name="outputs[0][greige_product_id]" class="form-control select2-js">
                  <option value="">Select Product</option>
                  @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                </select></td>
                <td><input type="number" name="outputs[0][quantity_output]" class="form-control qty-input" step="any" min="0" value="0"></td>
                <td><input type="number" name="outputs[0][weaving_rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
                <td class="charge-cell text-end">0.00</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
              </tr>
            </tbody>
            <tfoot><tr><td colspan="3" class="text-end fw-bold">Total Weaving Charge:</td><td class="fw-bold text-end" id="totalCharge">0.00</td><td></td></tr></tfoot>
          </table>
          <button type="button" class="btn btn-outline-primary" id="addRowBtn">Add Output</button>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Receive</button></footer>
    </section>
  </form>
</div></div>

<script>
  let idx = 1;
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#cpo_select').on('change', function () {
    const cpoId = $(this).val();
    if (!cpoId) { $('#yarnBalanceMsg').hide(); return; }
    fetch(`/greige-receives/cpo-yarn-balance/${cpoId}`).then(r => r.json()).then(data => {
      let html = 'Yarn currently at this mill: ';
      data.yarn_balance.forEach(b => html += `${b.product_name}: <strong>${b.quantity}</strong> (Rs. ${b.amount}) &nbsp; `);
      $('#yarnBalanceMsg').show().html(html || 'No yarn currently tracked for this CPO.');
    });
  });

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    @foreach ($products as $p)
      html += `<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const i = idx++;
    const row = $(`
      <tr class="output-row">
        <td><select name="outputs[${i}][greige_product_id]" class="form-control select2-js">${productOptionsHtml()}</select></td>
        <td><input type="number" name="outputs[${i}][quantity_output]" class="form-control qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="outputs[${i}][weaving_rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="charge-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#outputsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.qty-input, .rate-input', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    row.find('.charge-cell').text((qty * rate).toFixed(2));
    let total = 0;
    $('.charge-cell').each(function () { total += parseFloat($(this).text()) || 0; });
    $('#totalCharge').text(total.toFixed(2));
  });

  $(document).on('click', '.remove-row', function () { if ($('.output-row').length > 1) $(this).closest('tr').remove(); });
</script>
@endsection