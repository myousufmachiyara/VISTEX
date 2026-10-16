@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
  $pkr = fn($v) => number_format((float) $v, 0);
  $m = $d['money']; $p = $d['production'];
  $trendMax = $d['trend'] ? max(1, collect($d['trend'])->max(fn($t) => max($t['sales'], $t['cogs']))) : 1;
@endphp
<style>
  .kpi { border-left: 4px solid var(--bs-primary); }
  .kpi .v { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
  .kpi .l { font-size: .8rem; color: #6c757d; text-transform: uppercase; letter-spacing: .03em; }
  .bar { height: 10px; border-radius: 3px; }
  .q-item { display: flex; justify-content: space-between; align-items: center; padding: .45rem .25rem; border-bottom: 1px solid rgba(0,0,0,.06); }
  .q-item:last-child { border-bottom: 0; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
  <h2 class="text-dark mb-0"><strong>{{ now()->format('l, d F Y') }}</strong></h2>
  <span class="text-muted small">Hello, {{ auth()->user()->name }}</span>
</div>

@if($m)
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#198754"><div class="card-body"><div class="l">Invoiced this month</div><div class="v">{{ $pkr($m['sales_month']) }}</div><div class="small text-muted">PKR</div></div></section></div>
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#0d6efd"><div class="card-body"><div class="l">Received this month</div><div class="v">{{ $pkr($m['received_month']) }}</div><div class="small text-muted">PKR</div></div></section></div>
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#6f42c1"><div class="card-body"><div class="l">Receivable</div><div class="v">{{ $pkr($m['receivable']) }}</div>
    <div class="small {{ $m['overdue_count'] ? 'text-danger' : 'text-muted' }}">{{ $m['overdue_count'] ? $pkr($m['overdue']) . ' overdue (' . $m['overdue_count'] . ')' : 'nothing overdue' }}</div></div></section></div>
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#fd7e14"><div class="card-body"><div class="l">Payable to vendors</div><div class="v">{{ $pkr($m['payable']) }}</div><div class="small text-muted">PKR</div></div></section></div>
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#dc3545"><div class="card-body"><div class="l">PDC due in 7 days</div><div class="v">{{ $pkr($m['pdc_week']) }}</div><div class="small text-muted">not yet cleared</div></div></section></div>
  <div class="col-6 col-lg-2"><section class="card kpi h-100" style="border-color:#20c997"><div class="card-body"><div class="l">Stock value</div><div class="v">{{ $pkr(collect($d['stock'])->sum(fn($s) => $s['own'] + $s['mills'] + $s['transit'])) }}</div><div class="small text-muted">all locations</div></div></section></div>
</div>
@endif

<div class="row g-3">
  <div class="col-lg-4">
    <section class="card h-100">
      <header class="card-header"><h2 class="card-title">Waiting on you</h2></header>
      <div class="card-body">
        @forelse($d['queues'] as $q)
          <a href="{{ $q['url'] }}" class="q-item text-decoration-none text-dark"><span>{{ $q['label'] }}</span><span class="badge bg-{{ $q['tone'] }} {{ $q['tone'] === 'warning' ? 'text-dark' : '' }}">{{ $q['n'] }}</span></a>
        @empty
          <p class="text-muted mb-0">Nothing waiting. 👍</p>
        @endforelse
      </div>
    </section>
  </div>

  @if($p)
  <div class="col-lg-4">
    <section class="card h-100">
      <header class="card-header"><h2 class="card-title">Production — {{ now()->format('F') }}</h2></header>
      <div class="card-body">
        <div class="q-item"><span>Processed fabric received</span><strong>{{ number_format($p['received_m'], 1) }} m</strong></div>
        <div class="q-item"><span>Fabric cut</span><strong>{{ number_format($p['cut_m'], 1) }} m</strong></div>
        <div class="q-item"><span>Pieces packed</span><strong>{{ number_format($p['packed_pcs']) }}</strong></div>
        <div class="q-item"><span>Pieces dispatched</span><strong>{{ number_format($p['dispatched_pcs']) }}</strong></div>
        <div class="q-item"><span class="text-muted">Greige in process at mills (now)</span><strong>{{ number_format($p['greige_at_mills'], 1) }} m</strong></div>
        <div class="q-item"><span class="text-muted">Reprocessing at mills (now)</span><strong>{{ number_format($p['reprocess_at_mills'], 1) }} m</strong></div>
      </div>
    </section>
  </div>
  @endif

  @if($d['trend'])
  <div class="col-lg-4">
    <section class="card h-100">
      <header class="card-header d-flex justify-content-between align-items-center"><h2 class="card-title">Sales vs cost of goods</h2>
        @can('reports.processing')<a href="{{ route('reports.show', ['processing', 'order_margin']) }}" class="small">Margins →</a>@endcan</header>
      <div class="card-body">
        @foreach($d['trend'] as $t)
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span>{{ $t['label'] }}</span><span>{{ $pkr($t['sales']) }} <span class="text-muted">/ {{ $pkr($t['cogs']) }}</span></span></div>
            <div class="bar bg-success mb-1" style="width: {{ max(0.5, $t['sales'] / $trendMax * 100) }}%"></div>
            <div class="bar bg-secondary opacity-50" style="width: {{ max(0.5, $t['cogs'] / $trendMax * 100) }}%"></div>
          </div>
        @endforeach
        <div class="small text-muted"><span class="badge bg-success">&nbsp;</span> invoiced &nbsp; <span class="badge bg-secondary opacity-50">&nbsp;</span> cost of goods dispatched</div>
      </div>
    </section>
  </div>
  @endif
</div>

<div class="row g-3 mt-0">
  @if($d['orders'] !== null)
  <div class="col-lg-7">
    <section class="card h-100">
      <header class="card-header"><h2 class="card-title">Open customer orders</h2></header>
      <div class="card-body">
        @if(empty($d['orders']))<p class="text-muted mb-0">No open approved orders.</p>@else
        <table class="table table-sm mb-0">
          <thead><tr><th>Order</th><th>Customer</th><th>Due</th><th class="text-end">Packed pcs</th><th style="width:30%">Dispatched (m)</th></tr></thead>
          <tbody>
          @foreach($d['orders'] as $o)
            <tr>
              <td><a href="{{ route('jobs.show', $o['id']) }}">{{ $o['job_no'] }}</a></td><td class="small">{{ $o['customer'] }}</td>
              <td class="small {{ $o['late'] ? 'text-danger fw-bold' : '' }}">{{ $o['due'] ? date('d-M', strtotime($o['due'])) : '—' }}</td>
              <td class="text-end">{{ number_format($o['packed_pcs']) }}</td>
              <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar {{ $o['late'] ? 'bg-danger' : '' }}" style="width: {{ $o['pct'] }}%"></div></div>
                <span class="small text-nowrap">{{ number_format($o['sent_m']) }}/{{ number_format($o['ordered_m']) }}</span></div></td>
            </tr>
          @endforeach
          </tbody>
        </table>
        @endif
      </div>
    </section>
  </div>
  @endif

  @if($d['stock'])
  <div class="col-lg-5">
    <section class="card h-100">
      <header class="card-header"><h2 class="card-title">Stock value (PKR)</h2></header>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <thead><tr><th>Category</th><th class="text-end">Our stores</th><th class="text-end">At mills</th><th class="text-end">In transit</th></tr></thead>
          <tbody>
          @foreach($d['stock'] as $s)
            <tr><td>{{ $s['category'] }}</td><td class="text-end">{{ $pkr($s['own']) }}</td><td class="text-end">{{ $s['mills'] ? $pkr($s['mills']) : '' }}</td><td class="text-end">{{ $s['transit'] ? $pkr($s['transit']) : '' }}</td></tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </div>
  @endif
</div>
@endsection
