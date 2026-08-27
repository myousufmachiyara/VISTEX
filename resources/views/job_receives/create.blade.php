@extends('layouts.app')

@section('title', 'Job Receive | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('job_receives.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Job Receive</h2>
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
              <label>Job Order <span class="text-danger">*</span></label>
              <select name="job_order_id" id="job_order_select" class="form-control select2-js" required>
                <option value="">Select Job Order</option>
                @foreach ($jobOrders as $job)
                  <option value="{{ $job->id }}">
                    {{ $job->job_no }} — {{ $job->vendor->name ?? '' }} ({{ $job->status }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receive Date</label>
              <input type="date" name="receive_date" class="form-control" value="{{ date('Y-m-d') }}" required>
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

          <div class="alert alert-info py-2" id="loadingMsg">
            Select a job order to see outstanding raw material.
          </div>

          {{-- ── RAW MATERIALS CONSUMED ────────────────────────────── --}}
          <div id="consumedSection" style="display:none">
            <h5 class="mt-3">Raw Materials Consumed</h5>
            <div class="table-responsive mb-3">
              <table class="table table-bordered" id="consumedTable">
                <thead>
                  <tr>
                    <th>Raw Product</th>
                    <th>Outstanding</th>
                    <th>Qty Consumed</th>
                    <th>Leftover</th>
                  </tr>
                </thead>
                <tbody id="consumedBody"></tbody>
              </table>
            </div>
          </div>

          {{-- ── OUTPUTS PRODUCED ──────────────────────────────────── --}}
          <div id="outputsSection" style="display:none">
            <h5>Output(s) Produced</h5>
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
                <tbody id="outputsBody"></tbody>
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
                <label>Processing Charge Override (optional)</label>
                <input type="number" name="processing_charge_override" class="form-control" step="any" min="0" placeholder="Leave blank to use calculated total">
                <small class="text-muted">Only fill this if the vendor's actual invoice differs from the calculated total.</small>
              </div>
            </div>
          </div>

        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Receive</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  const outputProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name]));
  let consumedIndex = 0;
  let outputIndex = 0;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#job_order_select').on('change', function () {
    const jobId = $(this).val();
    $('#consumedBody').empty();
    $('#outputsBody').empty();
    consumedIndex = 0;
    outputIndex = 0;
    recalcTotal();

    if (!jobId) {
      $('#consumedSection, #outputsSection').hide();
      $('#loadingMsg').show().text('Select a job order to see outstanding raw material.');
      return;
    }

    $('#loadingMsg').show().text('Loading outstanding stock...');

    fetch(`/job-receives/outstanding/${jobId}`)
      .then(res => res.json())
      .then(data => {
        if (data.length === 0) {
          $('#consumedSection, #outputsSection').hide();
          $('#loadingMsg').show().text('No outstanding raw material for this job order — it may already be fully received.');
          return;
        }

        $('#loadingMsg').hide();
        $('#consumedSection, #outputsSection').show();

        // Every outstanding raw product gets its own consumption row
        data.forEach(item => addConsumedRow(item.product_id, item.product_name, item.outstanding));

        // Start with one blank output row
        addOutputRow();
      });
  });

  function addConsumedRow(productId, productName, outstanding) {
    const idx = consumedIndex++;
    const row = $(`
      <tr>
        <td>
          ${productName}
          <input type="hidden" name="consumed[${idx}][raw_product_id]" value="${productId}">
        </td>
        <td>${outstanding}</td>
        <td>
          <input type="number" name="consumed[${idx}][quantity_consumed]" class="form-control consumed-input"
                 value="0" step="any" min="0" max="${outstanding}" data-outstanding="${outstanding}">
        </td>
        <td class="leftover-cell">${outstanding}</td>
      </tr>
    `);
    $('#consumedBody').append(row);
  }

  function outputProductOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    outputProducts.forEach(p => {
      html += `<option value="${p.id}">${p.name}</option>`;
    });
    return html;
  }

  function addOutputRow() {
    const idx = outputIndex++;
    const row = $(`
      <tr class="output-row">
        <td>
          <select name="outputs[${idx}][output_product_id]" class="form-control select2-js" required>
            ${outputProductOptionsHtml()}
          </select>
        </td>
        <td><input type="number" name="outputs[${idx}][quantity_output]" class="form-control output-qty-input" value="0" step="any" min="0"></td>
        <td><input type="number" name="outputs[${idx}][conversion_rate]" class="form-control rate-input" value="0" step="any" min="0"></td>
        <td class="line-amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-output-row">&times;</button></td>
      </tr>
    `);
    $('#outputsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }

  $('#addOutputRowBtn').on('click', function () {
    addOutputRow();
  });

  $(document).on('input', '.consumed-input', function () {
    const outstanding = parseFloat($(this).data('outstanding')) || 0;
    const consumed = parseFloat($(this).val()) || 0;
    const leftover = Math.max(0, outstanding - consumed);
    $(this).closest('tr').find('.leftover-cell').text(leftover.toFixed(3));
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