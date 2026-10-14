{{-- Processing PO fields, shared by create and edit. $order: PurchaseOrder on edit, null on create. --}}
@php
  $ppo = $order ?? null;
  $spec = old('fabric_specs', $ppo?->fabric_specs ?? []);
@endphp
<div id="processingSection" style="display:none">
  <hr>
  <h6>Customer Order &amp; Program</h6>
  <div class="row">
    <div class="col-md-5 mb-3">
      <label>Customer Order <span class="text-danger">*</span></label>
      <select name="job_id" id="job_select" class="form-control select2-js" required>
        <option value="">Select approved customer order</option>
        @foreach($approvedJobs as $job)
          <option value="{{ $job->id }}" @selected(old('job_id', $ppo?->job_id) == $job->id)>{{ $job->job_no }} — {{ $job->customer->name ?? '' }}{{ $job->customer_po_number ? ' — PO ' . $job->customer_po_number : '' }}</option>
        @endforeach
      </select>
      <small class="text-muted" id="jobHint">Only approved customer orders are listed.</small>
    </div>
    <div class="col-md-3 mb-3">
      <label>PO Type</label>
      <select name="fabric_specs[po_kind]" class="form-control">
        @foreach(['Outsource Processing', 'In-house Processing'] as $k)<option @selected(($spec['po_kind'] ?? '') === $k)>{{ $k }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-2 mb-3">
      <label>Sample / Bulk</label>
      <select name="fabric_specs[is_sample]" class="form-control">
        <option value="0" @selected(empty($spec['is_sample']))>Bulk</option>
        <option value="1" @selected(!empty($spec['is_sample']))>Sample</option>
      </select>
    </div>
    <div class="col-md-2 mb-3">
      <label>Program</label>
      <input type="text" name="program" class="form-control" maxlength="255" placeholder="e.g. 100% Cotton" value="{{ old('program', $ppo?->program) }}">
    </div>
  </div>

  <h6 class="mt-2">Fabric Details</h6>
  <div class="row">
    <div class="col-md-4 mb-3">
      <label>Fabric Quality (Greige) <span class="text-danger">*</span></label>
      <select name="greige_product_id" class="form-control select2-js" required>
        <option value="">Select greige fabric</option>
        @foreach($greigeProducts as $g)<option value="{{ $g->id }}" @selected(old('greige_product_id', $ppo?->greige_product_id) == $g->id)>{{ $g->name }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-4 mb-3">
      <label>Process Type <span class="text-danger">*</span></label>
      <select name="service_type_id" class="form-control select2-js" required>
        <option value="">Select process</option>
        @foreach($serviceTypes as $st)<option value="{{ $st->id }}" @selected(old('service_type_id', $ppo?->service_type_id) == $st->id)>{{ $st->name }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-2 mb-3"><label>Greige Width</label><input type="text" name="fabric_specs[greige_width]" class="form-control" maxlength="30" placeholder='120"' value="{{ $spec['greige_width'] ?? '' }}"></div>
    <div class="col-md-2 mb-3"><label>Finished Width</label><input type="text" name="fabric_specs[finished_width]" class="form-control" maxlength="30" placeholder='108"' value="{{ $spec['finished_width'] ?? '' }}"></div>
    <div class="col-md-2 mb-3"><label>Finished GSM</label><input type="text" name="fabric_specs[finished_gsm]" class="form-control" maxlength="30" placeholder="155" value="{{ $spec['finished_gsm'] ?? '' }}"></div>
    <div class="col-md-3 mb-3"><label>Dyestuff</label><input type="text" name="fabric_specs[dyestuff]" class="form-control" maxlength="60" placeholder="Reactive" value="{{ $spec['dyestuff'] ?? '' }}"></div>
    <div class="col-md-3 mb-3"><label>Finish</label><input type="text" name="fabric_specs[finish]" class="form-control" maxlength="60" placeholder="Sanforized" value="{{ $spec['finish'] ?? '' }}"></div>
  </div>

  <h6 class="mt-2">Line Items</h6>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <button type="button" class="btn btn-sm btn-outline-primary" id="addProcRowBtn"><i class="fa fa-plus"></i> Add Row</button>
    <span class="ms-auto small d-flex flex-wrap gap-2 align-items-center">
      Allowance over ordered
      <input type="number" id="procAllow" class="form-control form-control-sm" value="15" min="0" max="100" style="width:70px"> %
      <label class="mb-0"><input type="checkbox" id="procRound10" checked> round up to 10 m</label>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="procApplyAllow">Apply</button>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm" id="procItemsTable">
      <thead class="table-light">
        <tr>
          <th>#</th><th style="min-width:85px">Collection</th><th style="min-width:150px">Pattern #</th><th style="min-width:140px">Design</th>
          <th style="min-width:100px">Colour</th><th style="min-width:130px">Packaging</th>
          <th class="text-end">Ordered (m)</th><th class="text-end" style="min-width:90px">Qty (m)</th><th class="text-end">+%</th>
          <th class="text-end" style="min-width:80px">Rate</th><th class="text-end">Amount</th><th></th>
        </tr>
      </thead>
      <tbody id="procItemsBody"></tbody>
      <tfoot class="fw-bold">
        <tr><td colspan="6" class="text-end">Total</td><td class="text-end" id="procOrdered"></td><td class="text-end" id="procQty"></td><td></td><td></td><td class="text-end" id="procSubtotalDisplay">0.00</td><td></td></tr>
        <tr><td colspan="10" class="text-end fw-normal">GST</td><td class="text-end fw-normal" id="procGstDisplay">0.00</td><td></td></tr>
        <tr><td colspan="10" class="text-end">Total</td><td class="text-end" id="procTotalDisplay">0.00</td><td></td></tr>
      </tfoot>
    </table>
  </div>
  <small class="text-muted d-block mb-3">Ordered = quantity on the customer order. Lines not on the order (e.g. made for stock) can be added with Add Row.</small>

  <div class="row">
    <div class="col-lg-6">
      <h6>Lab Requirements</h6>
      <table class="table table-bordered table-sm"><tbody id="labReqBody"></tbody></table>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addLabReqBtn"><i class="fa fa-plus"></i> Add Requirement</button>
    </div>
    <div class="col-lg-6">
      <h6>Notes &amp; Special Instructions <small class="text-muted">(printed on the PO)</small></h6>
      <table class="table table-borderless table-sm mb-1"><tbody id="procNotesBody"></tbody></table>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addProcNoteBtn"><i class="fa fa-plus"></i> Add Note</button>
    </div>
  </div>
</div>
@include('purchase_orders._processing_js')
