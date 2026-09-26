@extends('layouts.app')
@section('title', 'Yarn Issue | New')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('yarn_issues.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">New Yarn Issue</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- ═══ Type selector ═══ --}}
        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Issue Type <span class="text-danger">*</span></label>
            <select id="issue_type_select" class="form-control" required>
              <option value="weaving" selected>Weaving (issue to weaving mill)</option>
              <option value="sell">Sell (issue against a Yarn Sale Order)</option>
            </select>
          </div>
        </div>

        {{-- ═══ WEAVING ISSUE (existing, unchanged) ═══ --}}
        <div id="weavingIssueSection">
          <div class="row">
            <div class="col-md-5 mb-3">
              <label>Weaving PO <span class="text-danger">*</span></label>
              <select name="purchase_order_id" id="po_select" class="form-control select2-js">
                <option value="">Select PO</option>
                @foreach ($cpos as $cpo)
                  <option value="{{ $cpo->id }}">{{ $cpo->order_no }} — {{ $cpo->vendor->name ?? '' }} — {{ $cpo->item_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 mb-3"><label>Issue Date</label><input type="date" name="issue_date" id="weaving_issue_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
            <div class="col-md-4 mb-3"><label>Attachments</label><input type="file" name="attachments[]" class="form-control weaving-field" multiple></div>
            <div class="col-md-12 mb-3"><label>Remarks</label><textarea name="remarks" id="weaving_remarks" class="form-control" rows="1"></textarea></div>
          </div>

          <div class="alert alert-info py-2" id="poInfo" style="display:none"></div>
          <div class="alert alert-secondary py-2" id="loadingMsg">Select a PO to see yarn requirement and stock.</div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered"><thead><tr><th>Yarn</th><th>Available in Warehouse</th><th>Quantity to Issue</th></tr></thead>
              <tbody id="itemsBody"></tbody>
            </table>
          </div>
        </div>

        {{-- ═══ SELL ISSUE (prototype — UI only, backend not yet wired) ═══ --}}
        <div id="sellIssueSection" style="display:none">
          <div class="alert alert-warning py-2">
            <strong>Prototype:</strong> this "Yarn Sale" flow is a UI mock-up for client review. Saving is not yet
            implemented for this type — the Purchase Order dropdown lists real POs with remaining balance, but
            nothing will be persisted until the backend is built and this design is approved.
          </div>

          <div class="row">
            <div class="col-md-5 mb-3">
              <label>Sale Order <span class="text-danger">*</span></label>
              <select id="sell_job_select" class="form-control select2-js">
                <option value="">Select Sale Order</option>
                @foreach ($approvedJobs ?? [] as $job)
                  <option value="{{ $job->id }}">{{ $job->job_no }} — {{ $job->customer->name ?? 'General' }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 mb-3"><label>Issue Date</label><input type="date" id="sell_issue_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
            <div class="col-md-4 mb-3"><label>Attachments</label><input type="file" class="form-control sell-field" multiple></div>
            <div class="col-md-12 mb-3"><label>Remarks</label><textarea id="sell_remarks" class="form-control" rows="1"></textarea></div>
          </div>

          <div class="alert alert-secondary py-2" id="sellLoadingMsg">Select a Sale Order to see the yarn items ordered.</div>
          <div id="sellItemsWrap" style="display:none">
            <div id="sellItemsAccordion"></div>
          </div>
        </div>
      </div>
      <footer class="card-footer text-end">
        <button type="submit" class="btn btn-success" id="weavingSubmitBtn">Save Issue</button>
        <button type="button" class="btn btn-success" id="sellSubmitBtn" style="display:none" disabled title="Backend not built yet">Save Issue (prototype only)</button>
      </footer>
    </section>
  </form>
</div></div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

  // ── Type toggle ──
  $('#issue_type_select').on('change', function () {
    const type = $(this).val();
    const isWeaving = type === 'weaving';
    $('#weavingIssueSection').toggle(isWeaving);
    $('#sellIssueSection').toggle(!isWeaving);
    $('#weavingSubmitBtn').toggle(isWeaving);
    $('#sellSubmitBtn').toggle(!isWeaving);

    // Disable the hidden section's named inputs so only the active type posts data.
    $('#weavingIssueSection input, #weavingIssueSection select, #weavingIssueSection textarea').prop('disabled', !isWeaving);
    $('#po_select').prop('disabled', !isWeaving);
  }).trigger('change');

  // ── Weaving flow (unchanged) ──
  $('#po_select').on('change', function () {
    const poId = $(this).val();
    $('#itemsBody').empty();
    if (!poId) { $('#itemsSection, #poInfo').hide(); $('#loadingMsg').show(); return; }

    fetch(`/yarn-issues/cpo-details/${poId}`).then(r => r.json()).then(data => {
      $('#loadingMsg').hide();
      $('#poInfo').show().html(
        `Total Yarn Required: <strong>${data.total_yarn_required}</strong> | Already Issued: <strong>${data.already_issued}</strong> | ` +
        `Remaining Allowed: <strong class="text-success">${data.outstanding}</strong>`
      );
      $('#itemsSection').show();

      // Build item rows from warp + weft yarn data returned by the controller.
      // If warp and weft are the SAME product, only show one row (avoid
      // asking the user to split quantity across two identical rows).
      const items = [];
      items.push({
        product_id: data.warp_product_id,
        product_name: data.warp_product_name,
        available: data.warp_available,
      });

      if (data.weft_product_id !== data.warp_product_id) {
        items.push({
          product_id: data.weft_product_id,
          product_name: data.weft_product_name,
          available: data.weft_available,
        });
      }

      items.forEach((item, idx) => {
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

  // ── Sell flow (prototype) ──
  let sellItemIndex = 0;
  let sellPoRowIndex = 0;

  $('#sell_job_select').on('change', function () {
    const jobId = $(this).val();
    $('#sellItemsAccordion').empty();
    sellItemIndex = 0;
    if (!jobId) { $('#sellItemsWrap').hide(); $('#sellLoadingMsg').show(); return; }

    fetch(`/yarn-issues/job-items/${jobId}`).then(r => r.json()).then(data => {
      $('#sellLoadingMsg').hide();
      if (!data.items || data.items.length === 0) {
        $('#sellItemsWrap').hide();
        $('#sellLoadingMsg').show().text('No yarn items were found on this Sale Order.');
        return;
      }
      $('#sellItemsWrap').show();
      data.items.forEach(addSellItemBlock);
    });
  });

  function addSellItemBlock(item) {
    const blockIdx = sellItemIndex++;
    const block = $(`
      <div class="card mb-2 sell-item-block" data-allowed="${item.outstanding}">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div><strong>${item.product_name}</strong> <span class="text-muted">(${item.sku})</span></div>
            <div>Allowed: <strong>${item.outstanding}</strong> &nbsp; Allocated: <strong class="sell-allocated-display">0</strong>
              <span class="sell-allocated-warning text-danger ms-2" style="display:none">Exceeds allowed quantity</span>
            </div>
          </div>
          <table class="table table-sm table-bordered mb-1">
            <thead><tr><th width="55%">Purchase PO (item's remaining balance shown)</th><th width="30%">Quantity</th><th></th></tr></thead>
            <tbody class="sell-po-body" data-product-id="${item.product_id}"></tbody>
          </table>
          <button type="button" class="btn btn-sm btn-outline-primary add-sell-po-row">Add PO Source</button>
        </div>
      </div>
    `);
    $('#sellItemsAccordion').append(block);
    addSellPoRow(block.find('.sell-po-body'));
  }

  $(document).on('click', '.add-sell-po-row', function () {
    addSellPoRow($(this).closest('.sell-item-block').find('.sell-po-body'));
  });

  function addSellPoRow($tbody) {
    const idx = sellPoRowIndex++;
    const productId = $tbody.data('product-id');
    const row = $(`
      <tr class="sell-po-row">
        <td><select class="form-control sell-po-select" data-row="${idx}"><option value="">Loading POs…</option></select></td>
        <td><input type="number" class="form-control sell-po-qty" step="any" min="0" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-sell-po-row">&times;</button></td>
      </tr>
    `);
    $tbody.append(row);

    fetch(`/yarn-issues/product-pos/${productId}`).then(r => r.json()).then(pos => {
      const $select = row.find('.sell-po-select');
      let html = '<option value="">Select PO</option>';
      pos.forEach(po => html += `<option value="${po.id}" data-balance="${po.balance}">${po.order_no} — ${po.vendor_name} (Bal: ${po.balance})</option>`);
      $select.html(html || '<option value="">No PO with remaining balance</option>');
    });
  }

  $(document).on('click', '.remove-sell-po-row', function () {
    const $block = $(this).closest('.sell-item-block');
    $(this).closest('tr').remove();
    recalcSellAllocated($block);
  });

  $(document).on('change', '.sell-po-select', function () {
    // Cap the quantity input to the selected PO's remaining balance.
    const balance = $(this).find('option:selected').data('balance');
    const $qty = $(this).closest('tr').find('.sell-po-qty');
    if (balance !== undefined) $qty.attr('max', balance);
  });

  $(document).on('input', '.sell-po-qty', function () {
    recalcSellAllocated($(this).closest('.sell-item-block'));
  });

  function recalcSellAllocated($block) {
    let total = 0;
    $block.find('.sell-po-qty').each(function () { total += parseFloat($(this).val()) || 0; });
    const allowed = parseFloat($block.data('allowed')) || 0;
    $block.find('.sell-allocated-display').text(total.toFixed(3));
    $block.find('.sell-allocated-warning').toggle(total > allowed + 0.0005);
  }
</script>
@endsection
