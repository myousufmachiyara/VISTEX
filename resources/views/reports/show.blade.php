@extends('layouts.app')
@section('title', $meta['title'])
@section('content')
@php
  $fmt = function ($v, $type) {
      if ($v === null || $v === '') return '';
      return match ($type) {
          'date'   => date('d-M-Y', strtotime($v)),
          'money'  => number_format((float) $v, 2),
          'rate'   => number_format((float) $v, 2),
          'weight' => number_format((float) $v, 3),
          'qty'    => rtrim(rtrim(number_format((float) $v, 3), '0'), '.'),
          default  => $v,
      };
  };
  $numeric = ['money', 'rate', 'weight', 'qty'];
  $totals = [];
  foreach ($result['rows'] as $row) foreach ($result['columns'] as $c) {
      if ($c['total'] && is_numeric($row[$c['key']] ?? null)) $totals[$c['key']] = ($totals[$c['key']] ?? 0) + $row[$c['key']];
  }
  $period = match ($meta['filter']) {
      'range' => date('d-M-Y', strtotime($f['from'])) . ($f['from'] !== $f['to'] ? ' to ' . date('d-M-Y', strtotime($f['to'])) : ''),
      'asof'  => 'As on ' . date('d-M-Y', strtotime($f['asof'])),
      'days'  => 'Not moved in ' . $f['days'] . ' days',
      default => 'As on ' . now()->format('d-M-Y'),
  };
  $badge = ['OK' => 'success', 'Low' => 'warning text-dark', 'Out of Stock' => 'danger', 'Slow Moving' => 'warning text-dark', 'Dead Stock' => 'danger'];
@endphp
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 class="card-title mb-0">{{ $meta['title'] }}</h2>
        <small class="text-muted">{{ $familyTitle }} · {{ $period }}</small>
      </div>
      <div class="no-print d-flex gap-1">
        <select class="form-select form-select-sm" style="width:auto" onchange="if(this.value) location.href=this.value">
          @foreach($catalog as $k => $r)
            <option value="{{ route('reports.show', [$group, $k]) }}" @selected($k === $key)>{{ $r['title'] }}</option>
          @endforeach
        </select>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-success">Excel</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button>
        <a href="{{ route('reports.index') }}#{{ $group }}" class="btn btn-sm btn-outline-secondary">All Reports</a>
      </div>
    </header>
    <div class="card-body">

      {{-- ── Filters ── --}}
      <form method="GET" class="row g-2 align-items-end mb-3 no-print">
        @if($meta['filter'] === 'range')
          <div class="col-6 col-md-2"><label class="small">From</label><input type="date" name="from" class="form-control form-control-sm" value="{{ $f['from'] }}"></div>
          <div class="col-6 col-md-2"><label class="small">To</label><input type="date" name="to" class="form-control form-control-sm" value="{{ $f['to'] }}"></div>
        @elseif($meta['filter'] === 'asof')
          <div class="col-6 col-md-2"><label class="small">As on</label><input type="date" name="asof" class="form-control form-control-sm" value="{{ $f['asof'] }}"></div>
        @elseif($meta['filter'] === 'days')
          <div class="col-6 col-md-2"><label class="small">Not moved in (days)</label><input type="number" min="1" name="days" class="form-control form-control-sm" value="{{ $f['days'] }}"></div>
        @endif

        @if($options['categories']->isNotEmpty())
          <div class="col-6 col-md-2"><label class="small">Category</label>
            <select name="category_id" class="form-select form-select-sm">
              @foreach($options['categories'] as $c)
                <option value="{{ $c->id }}" @selected(($f['category_id'] ?: $options['categories']->firstWhere('code', 'packaging')?->id) == $c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        @if($options['vendors']->isNotEmpty())
          <div class="col-6 col-md-2"><label class="small">{{ $group === 'yarn' ? 'Mill / Vendor' : 'Supplier / Vendor' }}</label>
            <select name="vendor_id" class="form-select form-select-sm select2-js"><option value="">All</option>
              @foreach($options['vendors'] as $v)<option value="{{ $v->id }}" @selected($f['vendor_id'] == $v->id)>{{ $v->name }}</option>@endforeach
            </select>
          </div>
        @endif
        <div class="col-6 col-md-2"><label class="small">Item</label>
          <select name="product_id" class="form-select form-select-sm select2-js"><option value="">All</option>
            @foreach($options['products'] as $p)<option value="{{ $p->id }}" @selected($f['product_id'] == $p->id)>{{ $p->name }}</option>@endforeach
          </select>
        </div>
        @if($group !== 'yarn' || $key === 'stock')
        @if($group === 'packaging' || in_array($key, ['stock', 'fresh_stock', 'rejection_stock']))
          <div class="col-6 col-md-2"><label class="small">Warehouse</label>
            <select name="location_id" class="form-select form-select-sm"><option value="">{{ $group === 'greige' ? 'Per "Where"' : 'All our warehouses' }}</option>
              @foreach($options['locations'] as $l)<option value="{{ $l->id }}" @selected($f['location_id'] == $l->id)>{{ $l->name }}</option>@endforeach
              @foreach($options['mills'] as $l)<option value="{{ $l->id }}" @selected($f['location_id'] == $l->id)>{{ $l->name }} (mill)</option>@endforeach
            </select>
          </div>
        @endif
        @endif
        @if($group === 'greige' && in_array($key, ['fresh_stock', 'rejection_stock']))
          <div class="col-6 col-md-2"><label class="small">Where</label>
            <select name="location_scope" class="form-select form-select-sm">
              <option value="own" @selected($f['location_scope'] === 'own')>Our warehouses</option>
              <option value="mills" @selected($f['location_scope'] === 'mills')>At processing mills</option>
              <option value="all" @selected($f['location_scope'] === 'all')>Everywhere</option>
            </select>
          </div>
        @endif
        <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button></div>
        @if($meta['filter'] === 'range')
        <div class="col-auto small">
          @php($d = now())
          <a href="{{ request()->fullUrlWithQuery(['from' => $d->toDateString(), 'to' => $d->toDateString()]) }}">Today</a> ·
          <a href="{{ request()->fullUrlWithQuery(['from' => $d->copy()->subDay()->toDateString(), 'to' => $d->copy()->subDay()->toDateString()]) }}">Yesterday</a> ·
          <a href="{{ request()->fullUrlWithQuery(['from' => $d->copy()->startOfMonth()->toDateString(), 'to' => $d->toDateString()]) }}">This month</a> ·
          <a href="{{ request()->fullUrlWithQuery(['from' => $d->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => $d->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}">Last month</a>
        </div>
        @endif
      </form>

      @if(!empty($result['pending']))
        <div class="alert alert-secondary"><strong>Not available yet.</strong> {{ $result['pending'] }}</div>
      @endif
      @foreach($result['notes'] as $n)<div class="small text-muted mb-1">• {{ $n }}</div>@endforeach

      <div class="table-responsive mt-2">
        <table class="table table-bordered table-striped table-sm report-table" id="reportTable">
          <thead class="table-light"><tr>
            <th>#</th>
            @foreach($result['columns'] as $c)<th class="{{ in_array($c['type'], $numeric) ? 'text-end' : '' }}">{{ $c['label'] }}</th>@endforeach
          </tr></thead>
          <tbody>
            @foreach($result['rows'] as $i => $row)
            <tr>
              <td>{{ $i + 1 }}</td>
              @foreach($result['columns'] as $c)
                @php($v = $row[$c['key']] ?? null)
                @if($c['type'] === 'link')
                  <td>@if(!empty($row[$c['key'] . '_link']))<a href="{{ $row[$c['key'] . '_link'] }}" target="_blank">{{ $v }}</a>@else{{ $v }}@endif</td>
                @elseif($c['type'] === 'badge')
                  <td><span class="badge bg-{{ $badge[$v] ?? 'secondary' }}">{{ $v }}</span></td>
                @elseif($c['type'] === 'date')
                  <td data-order="{{ $v }}">{{ $fmt($v, 'date') }}</td>
                @else
                  <td class="{{ in_array($c['type'], $numeric) ? 'text-end' : '' }}" @if(in_array($c['type'], $numeric)) data-order="{{ $v ?? -1 }}" @endif>{{ $fmt($v, $c['type']) }}</td>
                @endif
              @endforeach
            </tr>
            @endforeach
          </tbody>
          @if($result['rows'] && $totals)
          <tfoot><tr class="fw-bold table-light">
            <td></td>
            @foreach($result['columns'] as $c)
              <td class="text-end">{{ $c['total'] ? $fmt($totals[$c['key']] ?? 0, $c['type']) : ($loop->first ? 'Total' : '') }}</td>
            @endforeach
          </tr></tfoot>
          @endif
        </table>
        @if(empty($result['rows']) && empty($result['pending']))
          <p class="text-muted text-center my-4">Nothing to show for these filters.</p>
        @endif
      </div>
    </div>
  </section>
</div></div>

<style>
  .report-table td, .report-table th { white-space: nowrap; font-size: .85rem; }
  @media print {
    .no-print, .sidebar-left, header.header, .page-header, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate { display: none !important; }
    .content-body, .inner-wrapper { padding: 0 !important; margin: 0 !important; }
    .card { border: 0 !important; box-shadow: none !important; }
    .report-table td, .report-table th { font-size: 9px; padding: 2px 4px !important; }
    @page { size: landscape; margin: 8mm; }
  }
</style>
<script>
  $(function () {
    $('.select2-js').select2({ width: '100%' });
    @if(count($result['rows']) > 0)
    $('#reportTable').DataTable({ paging: {{ count($result['rows']) > 200 ? 'true' : 'false' }}, pageLength: 200, info: false, order: [], autoWidth: false });
    @endif
  });
</script>
@endsection
