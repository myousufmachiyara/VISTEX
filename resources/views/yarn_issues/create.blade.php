@extends('layouts.app')
@section('title', 'Yarn Issue | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('yarn_issues.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Yarn Issue (to Weaving Mill)</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row">
          <div class="col-md-5 mb-3">
            <label>Conversion PO <span class="text-danger">*</span></label>
            <select name="cpo_id" id="cpo_select" class="form-control select2-js" required>
              <option value="">Select CPO</option>
              @foreach ($cpos as $cpo)
                <option value="{{ $cpo->id }}">{{ $cpo->cpo_no }} — {{ $cpo->vendor->name ?? '' }} — {{ $cpo->item_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
          <div class="col-md-4 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
          <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1"></textarea></div>
        </div>

        <div class="alert alert-info py-2" id="cpoInfo" style="display:none"></div>
        <div class="alert alert-secondary py-2" id="loadingMsg">Select a CPO to see yarn requirement and stock.</div>

        <div class="table-responsive mb-3" id="itemsSection" style="display:none">
          <table class="table table-bordered"><thead><tr><th>Yarn</th><th>Available in Warehouse</th><th>Quantity to Issue</th></tr></thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Save Issue</button></footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  $('#cpo_select').on('change', function () {
    const cpoId = $(this).val();
    $('#itemsBody').empty();
    if (!cpoId) { $('#itemsSection, #cpoInfo').hide(); $('#loadingMsg').show(); return; }

    fetch(`/yarn-issues/cpo-details/${cpoId}`).then(r => r.json()).then(data => {
      $('#loadingMsg').hide();
      $('#cpoInfo').show().html(
        `Total Required: <strong>${data.total_required}</strong> | Already Issued: <strong>${data.already_issued}</strong> | ` +
        `Remaining Allowed: <strong class="text-success">${data.remaining_allowed}</strong>`
      );
      $('#itemsSection').show();
      data.items.forEach((item, idx) => {
        $('#itemsBody').append(`
          <tr>
            <td>${item.product_name}<input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}"></td>
            <td>${item.available}</td>
            <td><input type="number" name="items[${idx}][quantity]" class="form-control" value="0" step="any" min="0" max="${item.available}"></td>
          </tr>
        `);
      });
    });
  });
</script>
@endsection