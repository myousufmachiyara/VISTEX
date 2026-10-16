<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Statement — {{ $result['party']->name ?? '' }}</title>
<style>
  :root { --g:#7a9563; --gl:#e9eee3; --ink:#2b2f33; --mute:#6b7280; }
  body { font-family:"Segoe UI", Arial, sans-serif; color:var(--ink); font-size:11px; margin:0; background:#fff; }
  .page { max-width:900px; margin:0 auto; padding:24px 28px; }
  .top { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid var(--g); padding-bottom:10px; }
  .top img { height:44px; }
  h1 { text-align:center; font-size:18px; margin:14px 0 4px; }
  .sub { text-align:center; color:var(--mute); margin-bottom:12px; }
  table { width:100%; border-collapse:collapse; }
  th { background:var(--g); color:#fff; padding:5px; font-size:10px; text-align:left; }
  td { border:1px solid #d7dccf; padding:4px 5px; }
  .text-end { text-align:right; } .fw-bold, tfoot td { font-weight:700; } .small { font-size:10px; } .text-muted { color:var(--mute); }
  .noprint { position:fixed; right:16px; top:16px; }
  .noprint button { background:var(--g); color:#fff; border:0; padding:8px 16px; border-radius:4px; cursor:pointer; }
  @media print { .noprint { display:none; } .page { padding:0; } @page { size:A4; margin:10mm; } }
</style>
</head>
<body onload="setTimeout(() => window.print(), 300)">
<div class="noprint"><button onclick="window.print()">Print</button></div>
<div class="page">
  <div class="top"><img src="{{ asset('assets/img/vistex-logo.png') }}" alt="Vistex"><div><strong>{{ \App\Services\StatementService::TYPES[$type] }} statement</strong></div></div>
  <h1>{{ $result['party']->name ?? '' }}</h1>
  <div class="sub">{{ date('d-M-Y', strtotime($from)) }} to {{ date('d-M-Y', strtotime($to)) }}</div>
  @include('statements._table')
</div>
</body>
</html>
