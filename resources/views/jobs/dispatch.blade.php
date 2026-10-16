@extends('layouts.app')
@section('title', 'Dispatch ' . $job->job_no)
@section('content')
@php
  $q = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
  $n = 0;
@endphp
<div class="row"><div class="col">
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('jobs.dispatch.store', $job->id) }}">
  @csrf
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Dispatch — {{ $job->job_no }} · {{ $job->customer->name ?? '' }}</h2>
      <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-outline-primary">Back</a>
    </header>
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-md-3"><label>From warehouse</label><select name="location_id" id="loc" class="form-control" required>
          @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('location_id') == $w->id)>{{ $w->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label>Date</label><input type="date" name="dispatch_date" class="form-control" value="{{ old('dispatch_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-7"><label>Remarks</label><input name="remarks" class="form-control" value="{{ old('remarks') }}" placeholder="container #, vehicle, shipping mark…"></div>
      </div>
      <div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th>Order line</th><th class="text-end">Ordered</th><th class="text-end">Packed</th><th class="text-end">Dispatched</th><th class="text-end">On gate pass</th>
          <th>Packed stock (lot · grade)</th><th class="text-end">Free</th><th style="width:120px">Dispatch pcs</th></tr></thead>
        <tbody>
        @foreach($lines as $l)
          @php
            $i = $l['item'];
            $rows = count($l['stock']) ?: 1;
          @endphp
          @forelse($l['stock'] as $k => $s)
            <tr class="stock-row" data-loc="{{ $s['location_id'] }}">
              @if($k === 0)
                <td rowspan="{{ $rows }}"><span class="font-monospace">{{ $i->pattern_code }}</span> {{ $i->design }} {{ $i->color_scheme }}<div class="small text-muted">{{ $i->product->name ?? '' }}</div></td>
                <td rowspan="{{ $rows }}" class="text-end">{{ $q($l['ordered']) }}</td><td rowspan="{{ $rows }}" class="text-end">{{ $q($l['packed']) }}</td>
                <td rowspan="{{ $rows }}" class="text-end">{{ $q($l['dispatched']) }}</td><td rowspan="{{ $rows }}" class="text-end">{{ $q($l['on_gate_pass']) }}</td>
              @endif
              <td class="small">{{ $s['location'] }} · lot {{ $s['lot_no'] ?? '—' }} · grade {{ $s['grade'] ?? '—' }}</td>
              <td class="text-end">{{ $q($s['free']) }}</td>
              <td>
                <input type="hidden" name="lines[{{ $n }}][job_item_id]" value="{{ $i->id }}"><input type="hidden" name="lines[{{ $n }}][product_id]" value="{{ $s['product_id'] }}">
                <input type="hidden" name="lines[{{ $n }}][lot_no]" value="{{ $s['lot_no'] }}"><input type="hidden" name="lines[{{ $n }}][grade]" value="{{ $s['grade'] }}">
                <input type="hidden" name="lines[{{ $n }}][location_id]" value="{{ $s['location_id'] }}">
                <input type="number" step="1" min="0" max="{{ $s['free'] }}" name="lines[{{ $n }}][pieces]" class="form-control form-control-sm" value="{{ old("lines.$n.pieces") }}">
                @php
                  $n++;
                @endphp
              </td>
            </tr>
          @empty
            <tr><td><span class="font-monospace">{{ $i->pattern_code }}</span> {{ $i->design }} {{ $i->color_scheme }}<div class="small text-muted">{{ $i->product->name ?? '' }}</div></td>
              <td class="text-end">{{ $q($l['ordered']) }}</td><td class="text-end">{{ $q($l['packed']) }}</td><td class="text-end">{{ $q($l['dispatched']) }}</td><td class="text-end">{{ $q($l['on_gate_pass']) }}</td>
              <td colspan="3" class="text-muted small">No packed stock for this line.</td></tr>
          @endforelse
        @endforeach
        </tbody>
      </table>
      </div>
      <div class="small text-muted">Only stock in the warehouse chosen above is sent. The warehouse in-charge approves the gate pass; stock leaves when the gatekeeper checks it out.</div>
    </div>
    <footer class="card-footer text-end"><button class="btn btn-primary">Create Dispatch Gate Pass</button></footer>
  </section>
  </form>
</div></div>
<script>
(function () {
  const sel = document.getElementById('loc');
  const apply = () => document.querySelectorAll('.stock-row').forEach(r => {
    const on = r.dataset.loc === sel.value;
    r.querySelectorAll('input[type=number]').forEach(i => { i.disabled = !on; });
    r.classList.toggle('text-muted', !on);
  });
  sel.addEventListener('change', apply); apply();
})();
</script>
@endsection
