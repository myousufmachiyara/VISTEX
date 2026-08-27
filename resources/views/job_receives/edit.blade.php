@extends('layouts.app')

@section('title', 'Job Receive | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('job_receives.update', $receive->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Job Receive — {{ $receive->receive_no }}</h2>
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

          <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Saving will reverse and redo this receive's stock/leftover/voucher entries against current data.
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Job Order</label>
              <input type="text" class="form-control" value="{{ $receive->jobOrder->job_no ?? 'N/A' }} — {{ $receive->jobOrder->vendor->name ?? '' }}" disabled>
              <small class="text-muted">Job order cannot be changed after creation.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receive Date</label>
              <input type="date" name="receive_date" class="form-control" value="{{ $receive->receive_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($receive->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($receive->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $receive->remarks }}</textarea>
            </div>
          </div>

          {{-- ── RAW MATERIALS CONSUMED ────────────────────────────── --}}
          <h5 class="mt-3">Raw Materials Consumed</h5>
          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="consumedTable">
              <thead>
                <tr>
                  <th>Raw Product</th>
                  <th>Qty Consumed</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="consumedBody">
                @foreach($receive->items as $i => $item)
                <tr class="consumed-row">
                  <td>
                    <select name="consumed[{{ $i }}][raw_product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $item->raw_product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="consumed[{{ $i }}][quantity_consumed]" class="form-control" step="any" min="0" value="{{ $item->quantity_consumed }}"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-consumed-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="addConsumedRowBtn">
              <i class="fas fa-plus"></i> Add Raw Product
            </button>
          </div>

          {{-- ── OUTPUTS PRODUCED ──────────────────────────────────── --}}
          <h5 class="mt-4">Output(s) Produced</h5>
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
                @foreach($receive->outputs as $i => $output)
                <tr class="output-row">
                  <td>
                    <select name="outputs[{{ $i }}][output_product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $output->output_product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="outputs[{{ $i }}][quantity_output]" class="form-control output-qty-input" step="any" min="0" value="{{ $output->quantity_output }}"></td>
                  <td><input type="number" name="outputs[{{ $i }}][conversion_rate]" class="form-control rate-input" step="any" min="0" value="{{ $output->conversion_rate }}"></td>
                  <td class="line-amount-cell text-end">{{ number_format($output->processing_amount, 2) }}</td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-output-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-bold">Calculated Total:</td>
                  <td class="fw-bold" id="calcTotal">{{ number_format($receive->outputs->sum('processing_amount'), 2) }}</td>
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
              <label>Processing Charge Override (optional)</label>
              <input type="number" name="processing_charge_override" class="form-control" step="any" min="0"
                     value="{{ $receive->processing_charge != $receive->outputs->sum('processing_amount') ? $receive->processing_charge : '' }}"
                     placeholder="Leave blank to use calculated total">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Receive</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let consumedIndex = {{ $receive->items->count() }};
  let outputIndex   = {{ $receive->outputs->count() }};

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
          <select name="consumed[${idx}][raw_product_id]" class="form-control select2-js" required>
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
    if ($('.consumed-row').length > 1) {
      $(this).closest('tr').remove();
    }
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