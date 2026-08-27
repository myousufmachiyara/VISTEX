@extends('layouts.app')

@section('title', 'Accounting Reports')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
          @php
            $tabs = [
              'general_ledger' => 'General Ledger',
              'trial_balance'  => 'Trial Balance',
              'profit_loss'    => 'Profit & Loss',
              'balance_sheet'  => 'Balance Sheet',
              'receivables'    => 'Receivables',
              'payables'       => 'Payables',
              'party_ledger'   => 'Party Ledger',
              'cash_bank'      => 'Cash / Bank',
            ];
          @endphp
          @foreach($tabs as $key => $label)
            <li class="nav-item">
              <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('reports.accounting', ['tab' => $key]) }}">
                {{ $label }}
              </a>
            </li>
          @endforeach
        </ul>
      </header>

      <div class="card-body">
        @include('reports.accounting-tabs.' . $tab, ['data' => $data, 'accounts' => $accounts, 'customers' => $customers, 'vendors' => $vendors])
      </div>
    </section>
  </div>
</div>
@endsection