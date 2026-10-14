<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $job->job_no }} — Customer Order</title>
@php $cur = $job->currency ?? 'PKR'; $pcsTotal = $job->items->sum(fn($i) => $i->pieces ?? 0); @endphp
<style>
  :root { --g:#7a9563; --gl:#e9eee3; --ink:#2b2f33; --mute:#6b7280; }
  * { box-sizing:border-box; }
  body { font-family: "Segoe UI", Arial, sans-serif; color:var(--ink); font-size:12px; margin:0; background:#fff; }
  .page { max-width:1050px; margin:0 auto; padding:24px 28px; }
  .top { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid var(--g); padding-bottom:10px; }
  .top img { height:48px; }
  h1 { text-align:center; font-size:19px; margin:16px 0 4px; letter-spacing:.5px; }
  .sub { text-align:center; color:var(--mute); margin-bottom:14px; }
  .label { color:var(--g); font-weight:700; letter-spacing:3px; font-size:11px; margin:16px 0 6px; text-transform:uppercase; }
  table { width:100%; border-collapse:collapse; }
  .kv td { border:1px solid #d7dccf; padding:5px 8px; }
  .kv td.k { background:var(--gl); font-weight:600; width:17%; }
  .lines th { background:var(--g); color:#fff; padding:6px; font-size:11px; border:1px solid var(--g); }
  .lines td { border:1px solid #d7dccf; padding:5px 6px; }
  .lines tbody tr:nth-child(even) td { background:#f7f8f5; }
  .lines tfoot td { background:var(--g); color:#fff; font-weight:700; }
  .r { text-align:right; } .c { text-align:center; } .mono { font-family: Consolas, monospace; font-weight:600; }
  .terms li { margin-bottom:4px; }
  .foot { margin-top:28px; color:var(--mute); text-align:center; font-size:10.5px; border-top:1px solid #e5e7eb; padding-top:8px; }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A4 landscape; margin:12mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top">
    <img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex">
    <div style="text-align:right;color:var(--mute)">{{ $job->job_no }}<br>{{ $job->status }}</div>
  </div>

  <h1>CUSTOMER ORDER</h1>
  <div class="sub">{{ $job->customer->name ?? '' }} · PO {{ $job->customer_po_number }}</div>

  <table class="kv">
    <tr><td class="k">Order #</td><td>{{ $job->job_no }}</td><td class="k">Order Date</td><td>{{ $job->order_date->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Customer</td><td>{{ $job->customer->name ?? '' }}</td><td class="k">Delivery Date</td><td>{{ $job->expected_date?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><td class="k">Customer PO #</td><td>{{ $job->customer_po_number }}</td><td class="k">Order Reference</td><td>{{ $job->customer_reference ?? '—' }}</td></tr>
    <tr><td class="k">Buyer</td><td>{{ $job->buyer_name ?? '—' }}</td><td class="k">Payment</td><td>{{ ucfirst($job->payment_term_type) }}{{ $job->payment_term_days ? " — {$job->payment_term_days} days" : '' }}</td></tr>
    <tr><td class="k">Ship To</td><td colspan="3">{{ collect([$job->consignee_name, $job->shipping_address])->filter()->implode(', ') ?: '—' }}</td></tr>
  </table>

  <div class="label">Item Details</div>
  <table class="lines">
    <thead><tr><th>S.No</th><th>Collection</th><th>Pattern #</th><th>Design</th><th>Colour Scheme</th><th>Packaging</th><th>Qty (m)</th><th>Pcs</th><th>Rate</th><th>Disc %</th><th>Amount ({{ $cur }})</th></tr></thead>
    <tbody>
      @foreach($job->items as $n => $i)
      <tr>
        <td class="c">{{ $n + 1 }}</td><td class="c">{{ $i->collection }}</td><td class="c mono">{{ $i->pattern_code }}</td>
        <td>{{ $i->design }}</td><td>{{ $i->color_scheme }}</td><td>{{ $i->product->name ?? '' }}</td>
        <td class="r">{{ number_format($i->quantity, 2) }}</td><td class="r">{{ $i->pieces !== null ? number_format($i->pieces) : '' }}</td>
        <td class="r">{{ rtrim(rtrim(number_format($i->unit_price, 4), '0'), '.') }}</td>
        <td class="r">{{ (float) $i->discount_pct ? number_format($i->discount_pct, 2) : '' }}</td>
        <td class="r">{{ number_format($i->amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot><tr><td colspan="6" class="r">TOTAL</td><td class="r">{{ number_format($job->total_meters, 2) }}</td><td class="r">{{ $pcsTotal ? number_format($pcsTotal) : '' }}</td><td></td><td></td><td class="r">{{ $cur }} {{ number_format($job->total_amount, 2) }}</td></tr></tfoot>
  </table>

  @if((float) $job->discount_amount || (float) $job->tax_amount || $cur !== 'PKR')
    <table class="kv" style="width:40%;margin-left:auto;margin-top:8px">
      <tr><td class="k">Gross</td><td class="r">{{ number_format($job->subtotal, 2) }}</td></tr>
      @if((float) $job->discount_amount)<tr><td class="k">Discount</td><td class="r">− {{ number_format($job->discount_amount, 2) }}</td></tr>@endif
      @if((float) $job->tax_amount)<tr><td class="k">Tax</td><td class="r">{{ number_format($job->tax_amount, 2) }}</td></tr>@endif
      <tr><td class="k">Total</td><td class="r"><strong>{{ $cur }} {{ number_format($job->total_amount, 2) }}</strong></td></tr>
      @if($cur !== 'PKR')<tr><td class="k">In PKR @ {{ rtrim(rtrim(number_format($job->exchange_rate, 4), '0'), '.') }}</td><td class="r">Rs {{ number_format($job->total_pkr) }}</td></tr>@endif
    </table>
  @endif

  @if($job->terms->isNotEmpty())
    <div class="label">Terms &amp; Conditions</div>
    <ol class="terms">@foreach($job->terms as $t)<li><strong>{{ $t->title }}</strong>@if($t->description) — {{ $t->description }}@endif</li>@endforeach</ol>
  @endif
  @if($job->remarks)<div class="label">Remarks</div><div>{{ $job->remarks }}</div>@endif

  @if($job->approver)
    <div class="label">Approved By</div>
    <div><strong>{{ $job->approver->name }}</strong><br><span style="color:var(--mute)">{{ $job->approved_at?->format('d F Y | H:i') }}</span></div>
  @endif

  <div class="foot">Vistex (Private) Limited • F-128, Hub River Road, SITE Area, Karachi, Pakistan</div>
</div>
</body>
</html>
