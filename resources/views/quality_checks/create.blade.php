@extends('layouts.app')

@section('title', 'Quality Check | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('quality_checks.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Quality Check</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Received Batch (Job Receive Output) <span class="text-danger">*</span></label>
              <select name="batch_key" id="batch_select" class="form-control select2-js" required>
                <option value="">Select a batch pending QC</option>
                @foreach ($pending as $row)
                  <option value="{{ $row['job_order_receive_id'] }}|{{ $row['product_id'] }}"
                          data-remaining="{{ $row['remaining_to_qc'] }}">
                    {{ $row['receive_no'] }} — {{ $row['job_no'] }} — {{ $row['vendor_name'] }} —
                    {{ $row['product_name'] }} (pending: {{ $row['remaining_to_qc'] }})
                  </option>
                @endforeach
              </select>
              <input type="hidden" name="job_order_receive_id" id="hidden_receive_id">
              <input type="hidden" name="product_id" id="hidden_product_id">
            </div>

            <div class="col-md-3 mb-3">
              <label>QC Date</label>
              <input type="date" name="qc_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Pending Quantity</label>
              <input type="text" id="pendingDisplay" class="form-control" disabled>
            </div>

            <div class="col-md-4 mb-3">
              <label>Quantity Inspected <span class="text-danger">*</span></label>
              <input type="number" name="quantity_inspected" id="qtyInspected" class="form-control" step="any" min="0.001" required>
            </div>

            <div class="col-md-4 mb-3">
              <label>Quantity Passed <span class="text-danger">*</span></label>
              <input type="number" name="quantity_passed" id="qtyPassed" class="form-control" step="any" min="0" required>
            </div>

            <div class="col-md-4 mb-3">
              <label>Quantity Rejected (auto)</label>
              <input type="text" id="qtyRejected" class="form-control" disabled>
            </div>

            <div class="col-md-6 mb-3">
              <label>Rejection Reason</label>
              <input type="text" name="rejection_reason" class="form-control" placeholder="e.g. weaving defect, colour mismatch">
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save QC</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#batch_select').on('change', function () {
    const val = $(this).val();
    if (!val) return;

    const [receiveId, productId] = val.split('|');
    const remaining = $(this).find('option:selected').data('remaining');

    $('#hidden_receive_id').val(receiveId);
    $('#hidden_product_id').val(productId);
    $('#pendingDisplay').val(remaining);
    $('#qtyInspected').attr('max', remaining);
  });

  function recalcRejected() {
    const inspected = parseFloat($('#qtyInspected').val()) || 0;
    const passed = parseFloat($('#qtyPassed').val()) || 0;
    const rejected = Math.max(0, inspected - passed);
    $('#qtyRejected').val(rejected.toFixed(3));
  }

  $('#qtyInspected, #qtyPassed').on('input', recalcRejected);
</script>
@endsection