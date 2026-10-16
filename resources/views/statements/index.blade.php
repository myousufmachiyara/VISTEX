@extends('layouts.app')
@section('title', 'Party Statement')
@section('content')
@php
  $types = \App\Services\StatementService::TYPES;
  $owes = $type === 'customer' ? 'Customer owes us' : 'We owe';
@endphp
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Party Statement</h2>
      @if($result)<a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="fa fa-print"></i> Print</a>@endif
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-md-2"><label class="small">Party type</label>
          <select name="party_type" class="form-control" onchange="this.form.party_id.value=''; this.form.submit()">
            @foreach($types as $k => $label)<option value="{{ $k }}" @selected($type === $k)>{{ $label }}</option>@endforeach
          </select></div>
        <div class="col-md-4"><label class="small">{{ $types[$type] }}</label>
          <select name="party_id" class="form-control select2-js"><option value="">Select…</option>
            @foreach($parties as $p)<option value="{{ $p->id }}" @selected(request('party_id') == $p->id)>{{ $p->name }}</option>@endforeach
          </select></div>
        <div class="col-md-2"><label class="small">From</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
        <div class="col-md-2"><label class="small">To</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Show</button></div>
      </form>
      @if($result)
        @include('statements._table')
      @else
        <p class="text-muted mb-0">Pick a party to see every posting against it, with running balance.</p>
      @endif
    </div>
  </section>
</div></div>
@endsection
