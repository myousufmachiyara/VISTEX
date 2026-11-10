@extends('layouts.app')
@section('title', 'QC ' . $qc->qc_no)
@section('content')
@php
  $editable = $qc->isOpen() && auth()->user()->can('qc_inspections.create');
  $canClose = $editable && auth()->user()->can('qc_inspections.edit');
  $po = $qc->purchaseOrder;
  $fmt = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
  $old = fn($id, $field, $default) => old("items.$id.$field", $default);
  $req = '<span class="text-danger">*</span>';
@endphp
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('qc_inspections.update', $qc->id) }}" id="qcForm">
  @csrf @method('PUT')
  <section class="card">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">QC {{ $qc->qc_no }} <span class="badge bg-{{ $qc->status_badge }} ms-2">{{ $qc->status }}</span></h2>
      <div>
        @can('qc_inspections.print')<a href="{{ route('qc_inspections.print', $qc->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="fa fa-print"></i> Print</a>@endcan
        <a href="{{ route('qc_inspections.index') }}" class="btn btn-outline-primary">Back</a>
      </div>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Processing PO / Lot:</strong> {{ $po->order_no ?? '' }}</div>
        <div class="col-md-3"><strong>Mill:</strong> {{ $po->vendor->name ?? '' }}</div>
        <div class="col-md-2"><strong>GRN:</strong> <a href="{{ route('purchase_receivings.show', $qc->purchase_receiving_id) }}">{{ $qc->receiving->receiving_no ?? '' }}</a></div>
        <div class="col-md-2"><strong>Challan:</strong> {{ $qc->receiving->challan->challan_no ?? '—' }}</div>
        <div class="col-md-2"><strong>Stock at:</strong> {{ $qc->location->name ?? '' }}</div>
      </div>

      @foreach($qc->items as $l)
      <div class="border rounded p-3 mb-3 qc-line" data-received="{{ $l->received_total }}" data-rate="{{ $l->rate }}">
        <div class="d-flex flex-wrap justify-content-between">
          <div><strong>{{ $l->product->name ?? '' }}</strong> <span class="text-muted small">Lot {{ $l->lot_no }}</span></div>
          <div class="small text-muted">Received (kanta): <strong>{{ $fmt($l->received_total) }} m</strong> · Cost {{ number_format($l->rate, 2) }}/m</div>
        </div>

        <h6 class="mt-3 mb-2 text-uppercase small text-muted">Measurement</h6>
        <div class="row g-2">
          <div class="col-6 col-md-2"><label class="small">Rolls {!! $req !!}</label><input type="number" min="1" class="form-control form-control-sm" name="items[{{ $l->id }}][rolls]" value="{{ $old($l->id, 'rolls', $l->rolls) }}" required @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">Measured meters {!! $req !!}</label><input type="number" step="any" min="0" class="form-control form-control-sm k-meters" name="items[{{ $l->id }}][measured_meters]" value="{{ $old($l->id, 'measured_meters', $fmt($l->measured_meters ?? $l->received_total)) }}" required @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">Width (inch) {!! $req !!}</label><input type="number" step="any" min="0" class="form-control form-control-sm" name="items[{{ $l->id }}][width_inch]" value="{{ $old($l->id, 'width_inch', $fmt($l->width_inch)) }}" required @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">Gross kg</label><input type="number" step="any" min="0" class="form-control form-control-sm" name="items[{{ $l->id }}][gross_kg]" value="{{ $old($l->id, 'gross_kg', $fmt($l->gross_kg)) }}" @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">GSM</label><input type="number" step="any" min="0" class="form-control form-control-sm" name="items[{{ $l->id }}][expected_gsm]" value="{{ $old($l->id, 'expected_gsm', $fmt($l->expected_gsm)) }}" @disabled(!$editable)></div>
        </div>
        <div class="small text-danger mt-1 k-short"></div>

        <h6 class="mt-3 mb-2 text-uppercase small text-muted">Status</h6>
        <div class="row g-2">
          <div class="col-12 col-md-3"><label class="small">Status {!! $req !!}</label>
            <select class="form-control form-control-sm" name="items[{{ $l->id }}][status]" required @disabled(!$editable)>
              <option value="">— select —</option>
              @foreach(array_keys(\App\Models\QcInspectionItem::STATUSES) as $st)
                <option value="{{ $st }}" @selected($old($l->id, 'status', $l->status) === $st)>{{ $st }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-9"><label class="small">Status remarks {!! $req !!}</label><input class="form-control form-control-sm" name="items[{{ $l->id }}][status_remarks]" value="{{ $old($l->id, 'status_remarks', $l->status_remarks) }}" placeholder="Why this status (e.g. width OK, 2 rolls shade off)" required @disabled(!$editable)></div>
        </div>

        <h6 class="mt-3 mb-2 text-uppercase small text-muted">Result (meters)</h6>
        <div class="row g-2 align-items-end">
          <div class="col-4 col-md-2"><label class="small">A quality</label><input type="number" step="any" min="0" class="form-control form-control-sm r-q" name="items[{{ $l->id }}][final_a]" value="{{ $old($l->id, 'final_a', $fmt($l->final_a)) }}" @disabled(!$editable)></div>
          <div class="col-4 col-md-2"><label class="small">B quality</label><input type="number" step="any" min="0" class="form-control form-control-sm r-q" name="items[{{ $l->id }}][final_b]" value="{{ $old($l->id, 'final_b', $fmt($l->final_b)) }}" @disabled(!$editable)></div>
          <div class="col-4 col-md-2"><label class="small">C quality</label><input type="number" step="any" min="0" class="form-control form-control-sm r-q" name="items[{{ $l->id }}][final_c]" value="{{ $old($l->id, 'final_c', $fmt($l->final_c)) }}" @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">Send for reprocess</label><input type="number" step="any" min="0" class="form-control form-control-sm r-q" name="items[{{ $l->id }}][reprocess_qty]" value="{{ $old($l->id, 'reprocess_qty', $fmt($l->reprocess_qty)) }}" @disabled(!$editable)></div>
          <div class="col-6 col-md-2"><label class="small">Shortage</label><input type="number" step="any" min="0" class="form-control form-control-sm r-q r-short" name="items[{{ $l->id }}][shortage_qty]" value="{{ $old($l->id, 'shortage_qty', $fmt($l->shortage_qty)) }}" @disabled(!$editable)></div>
          <div class="col-12 col-md-2"><div class="small r-sum fw-bold"></div></div>
        </div>

        <h6 class="mt-3 mb-2 text-uppercase small text-muted">Defects &amp; remarks</h6>
        @php
          $picked = old("items.{$l->id}.defects", $l->defects ?? []);
        @endphp
        <div class="d-flex flex-wrap gap-3 mb-2">
          @foreach(\App\Models\QcInspectionItem::DEFECTS as $k => $label)
            <label class="small"><input type="checkbox" name="items[{{ $l->id }}][defects][]" value="{{ $k }}" @checked(in_array($k, (array) $picked)) @disabled(!$editable)> {{ $label }}</label>
          @endforeach
        </div>
        <input class="form-control form-control-sm" name="items[{{ $l->id }}][defect_notes]" placeholder="Remarks (optional)" value="{{ $old($l->id, 'defect_notes', $l->defect_notes) }}" @disabled(!$editable)>
      </div>
      @endforeach

      <div class="row g-3">
        <div class="col-md-2"><label>Inspection Date</label><input type="date" name="inspection_date" class="form-control" value="{{ old('inspection_date', $qc->inspection_date->format('Y-m-d')) }}" @disabled(!$editable)></div>
        <div class="col-md-10"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks', $qc->remarks) }}" @disabled(!$editable)></div>
      </div>

      @if(!$qc->isOpen())
      <div class="alert alert-light border mt-3 mb-0 small">
        Closed by {{ $qc->closer->name ?? '' }} on {{ $qc->closed_at?->format('d-M-Y H:i') }}.
        Shortage charged to mill: <strong>{{ number_format($qc->shortage_amount, 2) }}</strong>
        @if($qc->claim_amount > 0) · Quality claim (old): <strong>{{ number_format($qc->claim_amount, 2) }}</strong>@endif
        @if($qc->pdc_reduced_by > 0) · PDC reduced by <strong>{{ number_format($qc->pdc_reduced_by, 2) }}</strong>@endif
        @if($qc->items->sum('reprocess_qty') > 0) · <strong>{{ $fmt($qc->items->sum('reprocess_qty')) }} m</strong> on hold for reprocessing @endif
      </div>
      @endif

      <h6 class="mt-4 mb-2 text-uppercase small text-muted">Status log</h6>
      @if($qc->statusLogs->isEmpty())
        <div class="small text-muted">No status recorded yet.</div>
      @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered small mb-0">
          <thead><tr><th>When</th><th>Item</th><th>From</th><th>To</th><th>Remarks</th><th>By</th></tr></thead>
          <tbody>
          @foreach($qc->statusLogs as $g)
            <tr>
              <td class="text-nowrap">{{ $g->created_at->format('d-M-Y H:i') }}</td>
              <td>{{ $g->item->product->name ?? '' }}</td>
              <td>@if($g->from_status)<span class="badge bg-{{ \App\Models\QcInspectionItem::STATUSES[$g->from_status] ?? 'secondary' }}">{{ $g->from_status }}</span>@else — @endif</td>
              <td><span class="badge bg-{{ \App\Models\QcInspectionItem::STATUSES[$g->to_status] ?? 'secondary' }}">{{ $g->to_status }}</span></td>
              <td>{{ $g->remarks }}</td>
              <td>{{ $g->user->name ?? '' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>
    @if($editable)
    <footer class="card-footer d-flex justify-content-end gap-2">
      <button name="action" value="save" class="btn btn-outline-primary">Save</button>
      @if($canClose)
      <button name="action" value="close" class="btn btn-success" onclick="return confirm('Close QC? Stock will be regraded and any shortage claimed from the mill. This cannot be undone.')">Save &amp; Close QC</button>
      @endif
    </footer>
    @endif
  </section>
  </form>
</div></div>

<script>
(function () {
  const n = v => parseFloat(v) || 0;
  const r3 = v => Math.round(v * 1000) / 1000;
  function refresh(box) {
    const received = n(box.dataset.received);
    const measured = n(box.querySelector('.k-meters').value);
    box.querySelector('.k-short').textContent = (measured && measured < received - 0.001)
      ? 'Measured ' + r3(received - measured) + ' m less than received. Enter it as shortage to charge the mill.' : '';
    let sum = 0; box.querySelectorAll('.r-q').forEach(i => sum += n(i.value));
    const s = box.querySelector('.r-sum'), ok = Math.abs(sum - received) < 0.001;
    s.textContent = r3(sum) + ' / ' + r3(received) + ' m' + (ok ? ' ✓' : ' — must match');
    s.className = 'small r-sum fw-bold ' + (ok ? 'text-success' : 'text-danger');
  }
  document.querySelectorAll('.qc-line').forEach(box => {
    box.addEventListener('input', () => refresh(box));
    refresh(box);
  });
})();
</script>
@endsection
