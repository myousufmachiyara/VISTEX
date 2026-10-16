<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $qc->qc_no }} — QC Report</title>
@php
  $po = $qc->purchaseOrder;
  $f = fn($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp
<style>
  :root { --g:#7a9563; --gl:#e9eee3; --ink:#2b2f33; --mute:#6b7280; }
  * { box-sizing:border-box; }
  body { font-family:"Segoe UI", Arial, sans-serif; color:var(--ink); font-size:11px; margin:0; background:#fff; }
  .page { max-width:1050px; margin:0 auto; padding:24px 28px; }
  .top { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid var(--g); padding-bottom:10px; }
  .top img { height:48px; }
  .no { font-size:18px; font-weight:700; letter-spacing:1px; }
  h1 { text-align:center; font-size:18px; margin:14px 0 10px; }
  table { width:100%; border-collapse:collapse; }
  .kv td { border:1px solid #d7dccf; padding:5px 8px; }
  .kv td.k { background:var(--gl); font-weight:600; width:14%; }
  .lines { margin-top:12px; }
  .lines th { background:var(--g); color:#fff; padding:5px; font-size:10px; }
  .lines td { border:1px solid #d7dccf; padding:5px; }
  .r { text-align:right; }
  .sign { display:flex; gap:20px; margin-top:44px; }
  .sign div { flex:1; border-top:1px solid #999; text-align:center; padding-top:4px; color:var(--mute); }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A4 landscape; margin:8mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top">
    <img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex">
    <div class="no">{{ $qc->qc_no }}</div>
  </div>
  <h1>QC REPORT — PROCESSED FABRIC</h1>
  <table class="kv">
    <tr><td class="k">Processing PO / Lot</td><td>{{ $po->order_no ?? '' }}</td><td class="k">Mill</td><td>{{ $po->vendor->name ?? '' }}</td><td class="k">Status</td><td>{{ $qc->status }}</td></tr>
    <tr><td class="k">GRN</td><td>{{ $qc->receiving->receiving_no ?? '' }}</td><td class="k">Challan</td><td>{{ $qc->receiving->challan->challan_no ?? '—' }}</td><td class="k">Date</td><td>{{ $qc->inspection_date->format('d-M-Y') }}</td></tr>
  </table>

  <table class="lines">
    <thead><tr>
      <th>Item</th><th class="r">Received A/B/C</th><th class="r">Rolls</th><th class="r">Net kg</th><th class="r">Measured m</th><th class="r">Width"</th>
      <th class="r">GSM PO / Actual</th><th class="r">A</th><th class="r">B</th><th class="r">C</th><th class="r">Reprocess</th><th class="r">Short</th><th>Defects</th>
    </tr></thead>
    <tbody>
    @foreach($qc->items as $l)
      <tr>
        <td>{{ $l->product->name ?? '' }}</td>
        <td class="r">{{ $f($l->received_a) }} / {{ $f($l->received_b) }} / {{ $f($l->received_c) }}</td>
        <td class="r">{{ $l->rolls ?? '—' }}</td>
        <td class="r">{{ $f($l->net_kg) }}</td>
        <td class="r">{{ $f($l->measured_meters) }}</td>
        <td class="r">{{ $f($l->width_inch) }}</td>
        <td class="r">{{ $f($l->expected_gsm) }} / {{ $f($l->actual_gsm) }}</td>
        <td class="r">{{ $f($l->final_a) }}</td>
        <td class="r">{{ $f($l->final_b) }}</td>
        <td class="r">{{ $f($l->final_c) }}</td>
        <td class="r">{{ $f($l->reprocess_qty) }}</td>
        <td class="r">{{ $f($l->shortage_qty) }}</td>
        <td>{{ $l->defect_labels }}{{ $l->defect_notes ? ' — ' . $l->defect_notes : '' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <table class="kv" style="margin-top:12px">
    <tr><td class="k">Shortage charged</td><td>{{ $f($qc->shortage_amount) }}</td><td class="k">Quality claim</td><td>{{ $f($qc->claim_amount) }} {{ $qc->claim_reason ? '(' . $qc->claim_reason . ')' : '' }}</td></tr>
    <tr><td class="k">Remarks</td><td colspan="3">{{ $qc->remarks ?? '—' }}</td></tr>
  </table>

  <div class="sign"><div>QC Inspector</div><div>Warehouse In-charge</div><div>Approved By</div></div>
</div>
</body>
</html>
