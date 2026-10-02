@extends('layouts.app')
@section('title', 'Unclear Cheques')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Unclear Cheques (Issued, awaiting clearance)</h2></header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Cheque Date</th><th>PDC #</th><th>Party</th><th class="text-end">Amount</th><th>Bank</th><th>Cheque #</th><th>Issued Date</th><th></th></tr></thead>
        <tbody>
          {{-- The controller passes $cheques (each Issued cheque); this used to loop an undefined $pdcs --}}
          @forelse($cheques as $cheque)
          <tr>
            <td data-order="{{ ($cheque->cheque_date ?? $cheque->pdc->due_date)?->format('Y-m-d') }}">{{ ($cheque->cheque_date ?? $cheque->pdc->due_date)?->format('d-M-Y') }}</td>
            <td>{{ $cheque->pdc->pdc_no ?? '' }} <small class="text-muted">#{{ $cheque->sequence_no }}</small></td>
            <td>{{ $cheque->pdc->party->name ?? '' }}</td>
            <td class="text-end">{{ number_format($cheque->amount, 2) }}</td>
            <td>{{ $cheque->bankAccount->name ?? '' }}</td><td>{{ $cheque->cheque_no }}</td>
            <td>{{ $cheque->issued_date?->format('d-M-Y') }}</td>
            <td><a href="{{ route('pdcs.show', $cheque->pdc_id) }}" class="btn btn-sm btn-outline-primary">Manage</a></td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-muted">No issued cheques awaiting clearance.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection