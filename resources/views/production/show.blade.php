@extends('layouts.app')
@section('title', $order->order_no)
@section('content')
@php
  $isCut = $order->type === 'cutting';
  $q = fn($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
  $yd = fn($m) => number_format(\App\Models\Issuance::toYards((float) $m), 2);
  // yards per piece of a put-up (stored as meters per piece)
  $ypu = function ($putUp) { $a = $putUp?->getAttribute('attributes'); if (is_string($a)) $a = json_decode($a, true);
      return \App\Models\Issuance::toYards((float) ($a['meters_per_unit'] ?? 0)); };
@endphp
<div class="row"><div class="col">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $order->type_label }} {{ $order->order_no }}</h2>
      <a href="{{ route('production.index') }}" class="btn btn-outline-primary">Back</a>
    </header>
    <div class="card-body">
      <div class="row small mb-3">
        <div class="col-md-3"><strong>Date:</strong> {{ $order->order_date->format('d-M-Y') }}</div>
        <div class="col-md-3"><strong>Warehouse:</strong> {{ $order->location->name ?? '' }}</div>
        <div class="col-md-3"><strong>Customer Order:</strong> {{ $order->job ? $order->job->job_no . ' — ' . ($order->job->customer->name ?? '') : '—' }}</div>
        <div class="col-md-3"><strong>By:</strong> {{ $order->creator->name ?? '' }}</div>
        @if($packedFrom)<div class="col-12 mt-1"><strong>Packed on:</strong> <a href="{{ route('production.show', $packedFrom->id) }}">{{ $packedFrom->order_no }}</a></div>@endif
        @if($order->remarks)<div class="col-12 mt-1"><strong>Remarks:</strong> {{ $order->remarks }}</div>@endif
      </div>
      <div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead class="table-light"><tr>
          <th>{{ $isCut ? 'Fabric' : 'Cut pieces' }}</th><th>Lot #</th><th>Grade</th><th class="text-end">{{ $isCut ? 'Yards used' : 'Pieces' }}</th>
          <th>Output</th><th class="text-end">Pieces</th><th class="text-end">Yards</th>
          @if($isCut)<th class="text-end">Leftover yd</th><th class="text-end">Rejected yd</th>@else<th>Packaging</th>@endif
          <th class="text-end">Value</th><th>Customer order line</th>
        </tr></thead>
        <tbody>
        @foreach($order->lines as $l)
          <tr>
            <td>{{ $l->inputProduct->name ?? '' }}</td><td>{{ $l->lot_no ?? '—' }}</td><td>{{ $l->grade ?? '—' }}</td>
            <td class="text-end">@if($isCut){{ $yd($l->input_qty) }}<div class="small text-muted">{{ $q($l->input_qty) }} m</div>@else{{ $q($l->input_qty) }}@endif</td>
            <td>{{ $l->outputProduct->name ?? '' }} <div class="small text-muted">{{ $l->outputProduct->sku ?? '' }}</div></td>
            <td class="text-end">{{ $q($l->output_qty) }}</td>
            <td class="text-end">{{ number_format((float) $l->output_qty * $ypu($l->putUp), 2) }}</td>
            @if($isCut)
              <td class="text-end">{{ $yd($l->leftover_qty) }}</td><td class="text-end">{{ $yd($l->rejected_qty) }}</td>
            @else
              <td class="small">
                @forelse($l->consumptions as $c){{ $c->product->name ?? '' }}: {{ $q($c->quantity) }}<br>@empty<span class="text-muted">none</span>@endforelse
              </td>
            @endif
            <td class="text-end">{{ number_format($l->output_amount, 2) }}@if(!$isCut && $l->packaging_amount > 0)<div class="small text-muted">incl. packaging {{ number_format($l->packaging_amount, 2) }}</div>@endif</td>
            <td class="small">{{ $l->jobItem ? trim($l->jobItem->pattern_code . ' ' . $l->jobItem->design) : '—' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </section>
</div></div>
@endsection
