<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $order->order_no }} — Mill Purchase Order</title>
@php
  $spec = $order->fabric_specs ?? [];
  $sample = !empty($spec['is_sample']);
  $collections = $order->items->pluck('collection')->filter()->unique()->implode(' / ');
  $fabric = [
    ['Fabric Quality', $order->greigeProduct->name ?? '—'], ['Process', $order->serviceType->name ?? '—'],
    ['Greige Width', $spec['greige_width'] ?? '—'], ['Finished Width', $spec['finished_width'] ?? '—'],
    ['Finished GSM', $spec['finished_gsm'] ?? '—'], ['Dyestuff', $spec['dyestuff'] ?? '—'],
  ];
  if (!empty($spec['finish'])) $fabric[] = ['Finish', $spec['finish']];
  $lab = $spec['lab_requirements'] ?? [];
@endphp
<style>
  :root { --g:#7a9563; --gl:#e9eee3; --ink:#2b2f33; --mute:#6b7280; }
  * { box-sizing:border-box; }
  body { font-family:"Segoe UI", Arial, sans-serif; color:var(--ink); font-size:12px; margin:0; background:#fff; }
  .page { max-width:900px; margin:0 auto; padding:24px 28px; }
  .top { border-bottom:2px solid var(--g); padding-bottom:10px; }
  .top img { height:52px; }
  .tag { text-align:center; color:#d11; font-weight:700; font-size:15px; margin-top:16px; }
  h1 { text-align:center; font-size:19px; margin:4px 0 14px; }
  .label { color:var(--g); font-weight:700; letter-spacing:3px; font-size:11px; margin:16px 0 6px; text-transform:uppercase; }
  table { width:100%; border-collapse:collapse; }
  .kv td { border:1px solid #d7dccf; padding:5px 8px; }
  .kv td.k { background:var(--gl); font-weight:600; width:25%; }
  .lines th { background:var(--g); color:#fff; padding:6px; font-size:11px; border:1px solid var(--g); }
  .lines td { border:1px solid #d7dccf; padding:5px 6px; text-align:center; }
  .lines td.l { text-align:left; } .lines td.r { text-align:right; }
  .lines tbody tr:nth-child(even) td { background:#f7f8f5; }
  .lines tfoot td { background:var(--g); color:#fff; font-weight:700; }
  .mono { font-weight:700; }
  .notes li { color:#d11; font-weight:700; margin-bottom:4px; }
  .terms li { margin-bottom:4px; }
  .foot { margin-top:28px; color:var(--mute); text-align:center; font-size:10.5px; border-top:1px solid #e5e7eb; padding-top:8px; }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A4; margin:12mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top"><img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex"></div>

  @if($sample)<div class="tag">SAMPLE PO</div>@endif
  <h1 @unless($sample) style="margin-top:16px" @endunless>MILL PURCHASE ORDER</h1>

  <table class="kv">
    <tr><td class="k">PO Number</td><td>{{ $order->order_no }}</td></tr>
    <tr><td class="k">PO Date</td><td>{{ $order->order_date->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Mill</td><td>{{ $order->vendor->name ?? '' }}</td></tr>
    <tr><td class="k">Delivery Date</td><td>{{ $order->expected_date?->format('d/m/Y') ?? '—' }}</td></tr>
    @if($order->program)<tr><td class="k">Program</td><td>{{ $order->program }}</td></tr>@endif
    <tr><td class="k">PO Type</td><td>{{ $spec['po_kind'] ?? 'Outsource Processing' }}</td></tr>
    @if($order->job)<tr><td class="k">Customer Order</td><td>{{ $order->job->job_no }}{{ $order->job->customer_reference ? ' — ' . $order->job->customer_reference : '' }}</td></tr>@endif
  </table>

  <div class="label">Fabric Details</div>
  <table class="kv">
    @foreach(array_chunk($fabric, 2) as $pair)
      <tr>
        <td class="k">{{ $pair[0][0] }}</td><td>{{ $pair[0][1] }}</td>
        @if(isset($pair[1]))<td class="k">{{ $pair[1][0] }}</td><td>{{ $pair[1][1] }}</td>@else<td></td><td></td>@endif
      </tr>
    @endforeach
  </table>

  <div class="label">Line Items{{ $collections ? ' — ' . $collections : '' }}</div>
  <table class="lines">
    <thead><tr><th>S.No</th><th>Collection</th><th>Pattern #</th><th>Description</th><th>Packaging</th><th>Qty (m)</th><th>Rate</th><th>Amount</th></tr></thead>
    <tbody>
      @foreach($order->items as $n => $i)
        <tr>
          <td>{{ $n + 1 }}</td>
          <td>{{ $i->collection }}</td>
          <td class="mono">{!! nl2br(e(str_replace([', ', ','], "\n", $i->pattern_code ?? ''))) !!}</td>
          <td>{{ $i->description ?: collect([$i->design, $i->color_scheme])->filter()->implode(' - ') }}</td>
          <td>{{ $i->product->name ?? '' }}</td>
          <td class="r">{{ rtrim(rtrim(number_format($i->quantity, 2), '0'), '.') }}</td>
          <td class="r">{{ rtrim(rtrim(number_format($i->rate, 2), '0'), '.') }}</td>
          <td class="r">{{ number_format($i->amount) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="5" class="r">TOTAL</td><td class="r">{{ number_format($order->items->sum('quantity')) }}</td><td></td><td class="r">Rs {{ number_format($order->subtotal) }}</td></tr>
      @if((float) $order->gst_amount)
        <tr><td colspan="7" class="r">GST</td><td class="r">Rs {{ number_format($order->gst_amount) }}</td></tr>
        <tr><td colspan="7" class="r">NET</td><td class="r">Rs {{ number_format($order->total_amount) }}</td></tr>
      @endif
    </tfoot>
  </table>

  @if(!empty($spec['notes']))
    <div class="label">Notes &amp; Special Instructions</div>
    <ol class="notes">@foreach($spec['notes'] as $note)<li>{{ $note }}</li>@endforeach</ol>
  @endif

  @if(!empty($lab))
    <div class="label">Lab Requirements</div>
    <table class="kv">
      @foreach(array_chunk($lab, 2) as $pair)
        <tr>
          <td class="k">{{ $pair[0]['label'] }}</td><td>{{ $pair[0]['value'] }}</td>
          @if(isset($pair[1]))<td class="k">{{ $pair[1]['label'] }}</td><td>{{ $pair[1]['value'] }}</td>@else<td></td><td></td>@endif
        </tr>
      @endforeach
    </table>
  @endif

  @if($order->terms->isNotEmpty())
    <div class="label">Terms &amp; Conditions</div>
    <ol class="terms">@foreach($order->terms as $t)<li><strong>{{ $t->title }}</strong>@if($t->description) — {{ $t->description }}@endif</li>@endforeach</ol>
  @endif

  <div class="label">Approved By</div>
  @if($order->approver)
    <div><strong style="font-size:14px">{{ $order->approver->name }}</strong><br>
      <span style="color:var(--mute)">Vistex (Private) Limited<br>Date: {{ $order->approved_at?->format('d F Y') }} | Time: {{ $order->approved_at?->format('H:i') }} PKT</span></div>
  @else
    <div style="color:var(--mute)">Not yet approved ({{ $order->status }})</div>
  @endif

  <div class="foot">Vistex (Private) Limited • F-128, Hub River Road, SITE Area, Karachi, Pakistan</div>
</div>
</body>
</html>
