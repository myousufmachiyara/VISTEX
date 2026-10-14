@extends('layouts.app')
@section('title', 'Gate Out')
@section('content')
<div class="row"><div class="col">
  <section class="card mb-3">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Gate Out — Passes Waiting to Leave</h2></header>
    <div class="card-body">
      @forelse($waiting as $m)
        <div class="border rounded p-2 mb-2 d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <div>
            <strong>{{ $m->movement_no }}</strong> · {{ $m->fromLocation->name ?? '' }} → {{ $m->toLocation->name ?? '' }}
            <div class="small text-muted">{{ $m->items->map(fn($i) => ($i->product->name ?? '') . ' ' . rtrim(rtrim(number_format($i->quantity, 3), '0'), '.'))->implode(' · ') }} · approved {{ $m->approved_at?->format('d-M H:i') }}</div>
          </div>
          <a href="{{ route('stock_movements.show', $m->id) }}#gateOut" class="btn btn-primary btn-sm">Check &amp; Let Out</a>
        </div>
      @empty
        <p class="text-muted mb-0">No gate passes waiting.</p>
      @endforelse
    </div>
  </section>
  <section class="card">
    <header class="card-header"><h2 class="card-title" style="font-size:15px">Left Today</h2></header>
    <div class="card-body">
      @forelse($today as $m)
        <div class="small mb-1"><a href="{{ route('stock_movements.show', $m->id) }}">{{ $m->movement_no }}</a> · {{ $m->fromLocation->name ?? '' }} → {{ $m->toLocation->name ?? '' }} · {{ $m->dispatched_at?->format('H:i') }} · {{ $m->vehicle_no ?? 'no vehicle #' }} · by {{ $m->dispatcher->name ?? '' }}</div>
      @empty
        <p class="text-muted mb-0">Nothing has left today.</p>
      @endforelse
    </div>
  </section>
</div></div>
@endsection
