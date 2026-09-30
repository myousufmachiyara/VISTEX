@extends('layouts.app')
@section('title', 'Inspect ' . $challan->challan_no)
@section('content')
@php($c = $data['challan'])
@php($po = $data['po'])
@php($isWeaving = $po['type'] === 'weaving')
<div class="row"><div class="col">
  <form action="{{ route('challans.review', $challan->id) }}" method="POST" id="reviewForm" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Inspect {{ $c['challan_no'] }} — {{ $po['order_no'] }}</h2>
        <a href="{{ route('challans.show', $challan->id) }}" class="btn btn-sm btn-outline-secondary">View Challan</a>
      </header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row mb-3">
          <div class="col-md-3"><strong>Vendor:</strong> {{ $po['vendor_name'] }}</div>
          <div class="col-md-3"><strong>Category:</strong> {{ $po['category_name'] }}</div>
          <div class="col-md-3"><strong>Arrived:</strong> {{ \Carbon\Carbon::parse($c['received_date'])->format('d-M-Y') }} ({{ $c['received_by'] }})</div>
          <div class="col-md-3"><strong>Vendor Challan #:</strong> {{ $c['vendor_challan_no'] ?? '—' }}</div>
        </div>

        @if($c['has_objection'])
          <div class="alert alert-warning"><strong>Gatekeeper objection:</strong> {{ $c['objection_remarks'] }}
            @if($c['objection_voice_url'])<div class="mt-2"><audio controls preload="metadata"><source src="{{ $c['objection_voice_url'] }}" type="audio/mp4"></audio></div>@endif
          </div>
        @endif
        @if($c['last_amendment'])
          <div class="alert alert-info">
            <strong>Amendment #{{ $c['last_amendment']['amendment_no'] }} {{ strtolower($c['last_amendment']['status']) }}.</strong>
            @foreach($c['last_amendment']['changes'] as $line)<div class="small">{{ $line }}</div>@endforeach
            @if($c['last_amendment']['rejection_reason'])<div class="small">Reason: {{ $c['last_amendment']['rejection_reason'] }}</div>@endif
            <div class="small mt-1">Figures below reflect the PO as it stands now.</div>
          </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-3">
          @foreach($c['images'] as $img)
            <a href="{{ $img }}" target="_blank"><img src="{{ $img }}" style="height:90px" class="border rounded"></a>
          @endforeach
        </div>

        @if($isWeaving && !empty($data['weaving']['yarn_at_mill']))
          <p class="small text-muted mb-2">Yarn still at mill:
            @foreach($data['weaving']['yarn_at_mill'] as $y){{ $y['product_name'] }}: {{ number_format($y['balance_at_mill'], 3) }} lbs @if(!$loop->last) · @endif @endforeach
          </p>
        @endif

        <table class="table table-bordered align-middle" id="linesTable">
          <thead>
            <tr>
              <th>Item</th><th class="text-end">Ordered</th><th class="text-end">Received Before</th><th class="text-end">Outstanding</th>
              <th class="text-end">Gate Count</th><th width="12%">Accept</th><th width="12%">Reject</th>
              <th width="11%" class="amend-col">New PO Qty</th>@unless($isWeaving)<th width="11%" class="amend-col">New Rate</th>@endunless
            </tr>
          </thead>
          <tbody>
            @foreach($data['lines'] as $i => $l)
            <tr>
              <td>{{ $l['description'] }} <small class="text-muted">{{ $l['unit'] }}</small>
                <input type="hidden" name="lines[{{ $i }}][purchase_order_item_id]" value="{{ $l['purchase_order_item_id'] }}">
                <div class="small text-muted">Rate: {{ number_format($l['rate'], 2) }}</div>
                <input type="text" name="lines[{{ $i }}][note]" class="form-control form-control-sm mt-1 line-note" placeholder="Note (e.g. 5 bags wet)" value="{{ old("lines.$i.note") }}">
              </td>
              <td class="text-end">{{ number_format($l['ordered_qty'], 3) }}</td>
              <td class="text-end">{{ number_format($l['already_received'], 3) }}</td>
              <td class="text-end outstanding" data-v="{{ $l['outstanding'] }}"><strong>{{ number_format($l['outstanding'], 3) }}</strong></td>
              <td class="text-end">{{ number_format($l['gate_qty'], 3) }}</td>
              <td><input type="number" step="any" min="0" name="lines[{{ $i }}][accepted_qty]" class="form-control acc" value="{{ old("lines.$i.accepted_qty", $l['accepted_qty']) }}"></td>
              <td><input type="number" step="any" min="0" name="lines[{{ $i }}][rejected_qty]" class="form-control rej" value="{{ old("lines.$i.rejected_qty", $l['rejected_qty']) }}"></td>
              <td class="amend-col">
                @if($isWeaving)
                  <input type="number" step="any" min="0" name="amend[total_meters_required]" class="form-control" placeholder="{{ $l['ordered_qty'] }}" value="{{ old('amend.total_meters_required') }}">
                @else
                  <input type="number" step="any" min="0" name="lines[{{ $i }}][new_quantity]" class="form-control" placeholder="{{ $l['ordered_qty'] }}" value="{{ old("lines.$i.new_quantity") }}">
                @endif
              </td>
              @unless($isWeaving)
              <td class="amend-col"><input type="number" step="any" min="0" name="lines[{{ $i }}][new_rate]" class="form-control" placeholder="{{ $l['rate'] }}" value="{{ old("lines.$i.new_rate") }}"></td>
              @endunless
            </tr>
            @endforeach
          </tbody>
        </table>
        <div id="overWarn" class="alert alert-warning py-2" style="display:none">Accepted quantity is more than what is outstanding on the PO. Choose <strong>Request PO amendment</strong> to raise the PO quantity first.</div>

        <div class="row amend-col">
          <div class="col-md-3 mb-3"><label>New Expected Date</label><input type="date" name="amend[expected_date]" class="form-control" value="{{ old('amend.expected_date') }}"></div>
          @if($isWeaving)
          <div class="col-md-3 mb-3"><label>New Rate per Pick</label><input type="number" step="any" name="amend[rate_per_pick]" class="form-control" placeholder="{{ $data['weaving']['rate_per_pick'] }}" value="{{ old('amend.rate_per_pick') }}"></div>
          @endif
          <div class="col-md-3 mb-3"><label>New Payment Term</label>
            <select name="amend[payment_term_type]" class="form-control">
              <option value="">No change ({{ $po['payment_term_type'] }})</option>
              @foreach(['cash','credit','pdc','other'] as $t)<option value="{{ $t }}" @selected(old('amend.payment_term_type')==$t)>{{ ucfirst($t) }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3 mb-3"><label>Payment Days</label><input type="number" name="amend[payment_term_days]" class="form-control" placeholder="{{ $po['payment_term_days'] }}" value="{{ old('amend.payment_term_days') }}"></div>
        </div>

        <hr>
        <label class="d-block mb-2"><strong>Decision</strong></label>
        <div class="btn-group flex-wrap mb-3" role="group">
          @foreach($data['decisions'] as $key => $label)
            <input type="radio" class="btn-check" name="decision" id="d_{{ $key }}" value="{{ $key }}" autocomplete="off" @checked(old('decision', 'accept') === $key)>
            <label class="btn btn-outline-{{ ['accept'=>'success','accept_with_objection'=>'warning','amend'=>'info','reject'=>'danger'][$key] }}" for="d_{{ $key }}">{{ $label }}</label>
          @endforeach
        </div>

        <div class="row">
          <div class="col-md-3 mb-3 post-col"><label>Receiving Date</label><input type="date" name="receiving_date" class="form-control" value="{{ old('receiving_date', $c['received_date']) }}"></div>
          @if($isWeaving)
          <div class="col-md-3 mb-3 post-col d-flex align-items-end">
            <div class="form-check">
              <input type="hidden" name="is_final_receiving" value="0">
              <input class="form-check-input" type="checkbox" name="is_final_receiving" value="1" id="is_final" @checked(old('is_final_receiving'))>
              <label class="form-check-label" for="is_final">Final receiving — close PO and consume all yarn left at mill</label>
            </div>
          </div>
          @endif
          <div class="col-md-12 mb-3">
            <label>Remarks <span class="text-danger remarks-req" style="display:none">*</span></label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Required for objection, amendment or rejection">{{ old('remarks') }}</textarea>
          </div>
        </div>
      </div>
      <footer class="card-footer text-end">
        <button type="submit" class="btn btn-success" id="submitReview">Confirm Decision</button>
      </footer>
    </section>
  </form>
</div></div>

<script>
$(function () {
  const labels = {accept: 'Accept & Post GRN', accept_with_objection: 'Accept with Objection & Post GRN', amend: 'Send Amendment for Approval', reject: 'Reject Consignment'};
  const btnClass = {accept: 'btn-success', accept_with_objection: 'btn-warning', amend: 'btn-info', reject: 'btn-danger'};

  function refresh() {
    const d = $('input[name=decision]:checked').val();
    $('.amend-col').toggle(d === 'amend');
    $('.post-col').toggle(d === 'accept' || d === 'accept_with_objection');
    $('.remarks-req').toggle(d !== 'accept');
    $('#submitReview').text(labels[d]).attr('class', 'btn ' + btnClass[d]);

    let over = false;
    $('#linesTable tbody tr').each(function () {
      const acc = parseFloat($(this).find('.acc').val()) || 0;
      const out = parseFloat($(this).find('.outstanding').data('v')) || 0;
      $(this).find('.acc').toggleClass('is-invalid', acc > out + 0.001);
      if (acc > out + 0.001) over = true;
    });
    $('#overWarn').toggle(over && d !== 'amend' && d !== 'reject');
  }
  $(document).on('change input', 'input[name=decision], .acc, .rej', refresh);
  refresh();

  $('#reviewForm').on('submit', function () {
    const d = $('input[name=decision]:checked').val();
    const hasRejected = $('.rej').toArray().some(el => (parseFloat(el.value) || 0) > 0);
    if (d === 'accept' && hasRejected) { alert('Some quantity is rejected — choose "Accept with objection".'); return false; }
    if (d !== 'accept' && !$('textarea[name=remarks]').val().trim()) { alert('Please add remarks for this decision.'); return false; }
    return confirm(labels[d] + '?');
  });
});
</script>
@endsection
