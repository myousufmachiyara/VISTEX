@extends('layouts.app')
@section('title', 'Unclear Cheques')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Unclear Cheques (Issued, awaiting clearance)</h2></header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Due Date</th><th>PDC #</th><th>Party</th><th class="text-end">Amount</th><th>Bank</th><th>Cheque #</th><th>Issued Date</th><th></th></tr></thead>
        <tbody>
          @foreach($pdcs as $pdc)
          <tr>
            <td>{{ $pdc->due_date->format('d-M-Y') }}</td>
            <td>{{ $pdc->pdc_no }}</td><td>{{ $pdc->party->name ?? '' }}</td>
            <td class="text-end">{{ number_format($pdc->amount, 2) }}</td>
            <td>{{ $pdc->bankAccount->name ?? '' }}</td><td>{{ $pdc->cheque_no }}</td>
            <td>{{ $pdc->issued_date?->format('d-M-Y') }}</td>
            <td><a href="{{ route('pdcs.show', $pdc->id) }}" class="btn btn-sm btn-outline-primary">Manage</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection