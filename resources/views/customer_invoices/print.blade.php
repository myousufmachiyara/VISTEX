<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $invoice->invoice_no }} — Invoice</title>
@php
  $i = $invoice;
  $q = fn($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
@endphp
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
  .kv td.k { background:var(--gl); font-weight:600; width:18%; }
  .lines { margin-top:14px; }
  .lines th { background:var(--g); color:#fff; padding:6px; font-size:11px; }
  .lines td { border:1px solid #d7dccf; padding:6px; }
  .r { text-align:right; }
  .sign { display:flex; gap:20px; margin-top:48px; }
  .sign div { flex:1; border-top:1px solid #999; text-align:center; padding-top:4px; color:var(--mute); }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A4; margin:10mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top"><img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex"><div class="no">{{ $i->invoice_no }}</div></div>
  <h1>{{ $i->tax_amount > 0 ? 'SALES TAX INVOICE' : 'INVOICE' }}</h1>
  <table class="kv">
    <tr><td class="k">Bill to</td><td>{{ $i->customer->name ?? '' }}<br><small>{{ $i->customer->address ?? '' }}</small></td><td class="k">Invoice date</td><td>{{ $i->invoice_date->format('d-M-Y') }}</td></tr>
    <tr><td class="k">NTN / STRN</td><td>{{ $i->customer->ntn_number ?? $i->customer->tax_id_number ?? '—' }}</td><td class="k">Due date</td><td>{{ $i->due_date?->format('d-M-Y') }}</td></tr>
  </table>
  <table class="lines">
    <thead><tr><th>#</th><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc %</th><th class="r">Amount</th><th class="r">Tax</th></tr></thead>
    <tbody>
    @foreach($i->items as $n => $it)
      <tr><td>{{ $n + 1 }}</td><td>{{ $it->product->name ?? '' }}<br><small>{{ $it->description }}{{ $it->unit === 'm' ? ' · ' . $q($it->delivered_qty) . ' pcs' : '' }}</small></td>
        <td class="r">{{ $q($it->quantity) }} {{ $it->unit }}</td><td class="r">{{ rtrim(rtrim(number_format($it->rate, 4), '0'), '.') }}</td>
        <td class="r">{{ $it->discount_pct ? $q($it->discount_pct) : '' }}</td><td class="r">{{ number_format($it->amount, 2) }}</td><td class="r">{{ $it->tax_amount ? number_format($it->tax_amount, 2) : '' }}</td></tr>
    @endforeach
      <tr><td colspan="5" class="r">Subtotal</td><td class="r">{{ number_format($i->subtotal, 2) }}</td><td></td></tr>
      @if($i->tax_amount > 0)<tr><td colspan="5" class="r">Sales Tax</td><td class="r">{{ number_format($i->tax_amount, 2) }}</td><td></td></tr>@endif
      <tr><td colspan="5" class="r"><strong>Total {{ $i->currency }}</strong></td><td class="r"><strong>{{ number_format($i->total, 2) }}</strong></td><td></td></tr>
    </tbody>
  </table>
  @if($i->remarks)<p><strong>Remarks:</strong> {{ $i->remarks }}</p>@endif
  <div class="sign"><div>Prepared by {{ $i->creator->name ?? '' }}</div><div>Authorised signature</div></div>
</div>
</body>
</html>
