@extends('layouts.app')

@section('title', 'Packaging Order | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('packaging_orders.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Packaging Order</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Contractor <span class="text-danger">*</span></label>
              <select name="contractor_id" class="form-control select2-js" required>
                <option value="">Select Contractor</option>
                @foreach ($contractors as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Packaging Date</label>
              <input type="date" name="packaging_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          {{-- ── FABRIC CONSUMED ────────────────────────────────────── --}}
          <h5 class="mt-3">Fabric Consumed</h5>
          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="consumedTable">
              <thead>
                <tr>
                  <th>Fabric Product</th>
                  <th>Quantity Consumed</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="consumedBody">
                <tr class="consumed-row">
                  <td>
                    <select name="consumed[0][fabric_product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="consumed[0][quantity_consumed]" class="form-control" step="any" min="0" value="0"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-consumed-row">&times;</button></td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="addConsumedRowBtn">
              <i class="fas fa-plus"></i> Add Fabric
            </button>
          </div>

          {{-- ── PACKAGED OUTPUTS ──────────────────────────────────── --}}
          <h5 class="mt-4">Packaged Output(s)</h5>
          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="outputsTable">
              <thead>
                <tr>
                  <th>Output Product</th>
                  <th>Quantity</th>
                  <th>Rate / Unit</th>
                  <th>Amount</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="outputsBody">
                <tr class="output-row">
                  <td>
                    <select name="outputs[0][output_product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="outputs[0][quantity_output]" class="form-control output-qty-input" step="any" min="0" value="0"></td>
                  <td><input type="number" name="outputs[0][conversion_rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
                  <td class="line-amount-cell text-end">0.00</td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-output-row">&times;</button></td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-bold">Calculated Total:</td>
                  <td class="fw-bold" id="calcTotal">0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addOutputRowBtn">
              <i class="fas fa-plus"></i> Add Output Product
            </button>
          </div>

          <div class="row mt-3">
            <div class="col-md-4">
              <label>Labour Charge Override (optional)</label>
              <input type="number" name="labour_charge_override" class="form-control" step="any" min="0" placeholder="Leave blank to use calculated total">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Packaging Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let consumedIndex = 1;
  let outputIndex = 1;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    @foreach ($products as $p)
      html += `<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#addConsumedRowBtn').on('click', function () {
    const idx = consumedIndex++;
    const row = $(`
      <tr class="consumed-row">
        <td>
          <select name="consumed[${idx}][fabric_product_id]" class="form-control select2-js" required>
            ${productOptionsHtml()}
          </select>
        </td>
        <td><input type="number" name="consumed[${idx}][quantity_consumed]" class="form-control" step="any" min="0" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-consumed-row">&times;</button></td>
      </tr>
    `);
    $('#consumedBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('click', '.remove-consumed-row', function () {
    if ($('.consumed-row').length > 1) $(this).closest('tr').remove();
  });

  $('#addOutputRowBtn').on('click', function () {
    const idx = outputIndex++;
    const row = $(`
      <tr class="output-row">
        <td>
          <select name="outputs[${idx}][output_product_id]" class="form-control select2-js" required>
            ${productOptionsHtml()}
          </select>
        </td>
        <td><input type="number" name="outputs[${idx}][quantity_output]" class="form-control output-qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="outputs[${idx}][conversion_rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="line-amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-output-row">&times;</button></td>
      </tr>
    `);
    $('#outputsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.output-qty-input, .rate-input', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.output-qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    row.find('.line-amount-cell').text((qty * rate).toFixed(2));
    recalcTotal();
  });

  function recalcTotal() {
    let total = 0;
    $('.output-row').each(function () {
      total += parseFloat($(this).find('.line-amount-cell').text()) || 0;
    });
    $('#calcTotal').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-output-row', function () {
    if ($('.output-row').length > 1) {
      $(this).closest('tr').remove();
      recalcTotal();
    }
  });
</script>
@endsection