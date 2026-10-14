<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $movement->movement_no }} — Gate Pass</title>
@php $m = $movement; @endphp
<style>
  :root { --g:#7a9563; --gl:#e9eee3; --ink:#2b2f33; --mute:#6b7280; }
  * { box-sizing:border-box; }
  body { font-family:"Segoe UI", Arial, sans-serif; color:var(--ink); font-size:12px; margin:0; background:#fff; }
  .page { max-width:820px; margin:0 auto; padding:24px 28px; }
  .top { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid var(--g); padding-bottom:10px; }
  .top img { height:48px; }
  .no { font-size:18px; font-weight:700; letter-spacing:1px; }
  h1 { text-align:center; font-size:19px; margin:16px 0 12px; }
  table { width:100%; border-collapse:collapse; }
  .kv td { border:1px solid #d7dccf; padding:5px 8px; }
  .kv td.k { background:var(--gl); font-weight:600; width:20%; }
  .lines { margin-top:14px; }
  .lines th { background:var(--g); color:#fff; padding:6px; font-size:11px; }
  .lines td { border:1px solid #d7dccf; padding:6px; }
  .r { text-align:right; } .c { text-align:center; }
  .sign { display:flex; gap:20px; margin-top:48px; }
  .sign div { flex:1; border-top:1px solid #999; text-align:center; padding-top:4px; color:var(--mute); }
  .stamp { margin-top:10px; padding:6px 10px; border:1px dashed var(--g); display:inline-block; color:var(--g); font-weight:600; }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A5 landscape; margin:8mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top">
    <img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex">
    <div class="no">{{ $m->movement_no }}</div>
  </div>
  <h1>GATE PASS / OUTWARD CHALLAN</h1>

  <table class="kv">
    <tr><td class="k">From</td><td>{{ $m->fromLocation->name ?? '' }}{{ $m->fromLocation?->vendor ? ' (' . $m->fromLocation->vendor->name . ')' : '' }}</td>
        <td class="k">Date</td><td>{{ $m->movement_date->format('d/m/Y') }}</td></tr>
    <tr><td class="k">To</td><td>{{ $m->toLocation->name ?? '' }}{{ $m->toLocation?->vendor ? ' (' . $m->toLocation->vendor->name . ')' : '' }}</td>
        <td class="k">Processing PO</td><td>{{ $m->purchaseOrder->order_no ?? '—' }}</td></tr>
    <tr><td class="k">Vehicle No.</td><td>{{ $m->vehicle_no ?? '' }}</td><td class="k">Driver</td><td>{{ collect([$m->driver_name, $m->driver_contact])->filter()->implode(' · ') }}</td></tr>
  </table>

  <table class="lines">
    <thead><tr><th>#</th><th>Item</th><th>Lot</th><th>Quantity</th></tr></thead>
    <tbody>
      @foreach($m->items as $n => $i)
        <tr><td class="c">{{ $n + 1 }}</td><td>{{ $i->product->name ?? '' }}</td><td>{{ $i->source_lot_no ?? '' }}</td>
          <td class="r">{{ rtrim(rtrim(number_format($i->quantity, 3), '0'), '.') }} {{ $i->product->measurementUnit->shortcode ?? '' }}</td></tr>
      @endforeach
    </tbody>
  </table>
  @if($m->remarks)<p><strong>Remarks:</strong> {{ $m->remarks }}</p>@endif

  <div>
    <span class="stamp">Approved by {{ $m->approver->name ?? '—' }} · {{ $m->approved_at?->format('d-M-Y H:i') }}</span>
    @if($m->dispatched_at)<span class="stamp">Left the gate {{ $m->dispatched_at->format('d-M-Y H:i') }} · {{ $m->dispatcher->name ?? '' }}</span>@endif
  </div>

  <div class="sign"><div>Issued by</div><div>Gate</div><div>Driver</div><div>Received by</div></div>
</div>
</body>
</html>
