@extends('layouts.app')

@section('title', 'Reprocessing Receive | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('reprocessing_receives.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Reprocessing Receive</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Reprocessing Issue <span class="text-danger">*</span></label>
              <select name="reprocessing_issue_id" id="issue_select" class="form-control select2-js" required>
                <option value="">Select Issue</option>
                @foreach ($issues as $issue)
                  <option value="{{ $issue->id }}">{{ $issue->issue_no }} — {{ $issue->originalReceiving->greigeProcessingOrder->vendor->name ?? '' }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor Challan # <span class="text-danger">*</span></label>
              <input type="text" name="vendor_challan_no" class="form-control" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receive Date</label>
              <input type="date" name="receive_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">Select an issue to see outstanding items.</div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered">
              <thead><tr><th>Product</th><th>Outstanding</th><th>Quantity Received</th></tr></thead>
              <tbody id="itemsBody"></tbody>
            </table>
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
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#issue_select').on('change', function () {
    const id = $(this).val();
    $('#itemsBody').empty();

    if (!id) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('Select an issue to see outstanding items.');
      return;
    }

    fetch(`/reprocessing-receives/outstanding/${id}`)
      .then(res => res.json())
      .then(data => {
        if (data.length === 0) {
          $('#itemsSection').hide();
          $('#loadingMsg').show().text('This issue has been fully received.');
          return;
        }
        $('#loadingMsg').hide();
        $('#itemsSection').show();

        data.forEach((item, idx) => {
          $('#itemsBody').append(`
            <tr>
              <td>${item.product_name}<input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}"></td>
              <td>${item.outstanding}</td>
              <td><input type="number" name="items[${idx}][quantity_received]" class="form-control" value="0" step="any" min="0" max="${item.outstanding}"></td>
            </tr>
          `);
        });
      });
  });
</script>
@endsection