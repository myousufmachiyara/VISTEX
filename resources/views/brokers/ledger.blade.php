{{-- brokers/ledger.blade.php --}}
@extends('layouts.app')
@section('title', 'Broker Ledger — ' . $broker->name)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $broker->name }} — Ledger</h2>
      <span class="badge bg-{{ $balance > 0 ? 'danger' : 'success' }} fs-6">
        Balance: {{ number_format(abs($balance), 2) }} {{ $balance > 0 ? '(Payable)' : '' }}
      </span>
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Date</th><th>Voucher #</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
          @foreach($entries as $e)
          <tr>
            <td>{{ \Carbon\Carbon::parse($e->voucher_date)->format('d-M-Y') }}</td>
            <td>{{ $e->voucher_no }}</td>
            <td>{{ $e->narration }}</td>
            <td class="text-end">{{ $e->debit > 0 ? number_format($e->debit, 2) : '' }}</td>
            <td class="text-end">{{ $e->credit > 0 ? number_format($e->credit, 2) : '' }}</td>
            <td class="text-end">{{ number_format($e->running_balance, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection