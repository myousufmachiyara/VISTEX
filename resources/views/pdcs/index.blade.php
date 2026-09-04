@extends('layouts.app')
@section('title', 'PDC — Post Dated Cheques')
@section('content')
<div class="row"><div class="col">

  <div class="row mb-3">
    @foreach(['pending'=>'Pending','created'=>'Created','signed'=>'Signed','issued'=>'Issued','cleared'=>'Cleared','bounced'=>'Bounced'] as $key=>$label)
    <div class="col">
      <div class="card text-center py-2">
        <div class="small text-muted">{{ $label }}</div>
        <div class="fw-bold">{{ number_format($summary[$key], 2) }}</div>
      </div>
    </div>
    @endforeach
  </div>

  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Post Dated Cheques</h2>
      <a href="{{ route('pdcs.uncleared') }}" class="btn btn-outline-warning">Unclear Cheques</a>
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="pdcTable">
        <thead><tr><th>Due Date</th><th>PDC #</th><th>Party</th><th class="text-end">Total</th><th class="text-end">Allocated</th><th class="text-end">Remaining</th><th></th></tr></thead>
        <tbody>
          @foreach($pdcs as $pdc)
          <tr>
            <td>{{ $pdc->due_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('pdcs.show', $pdc->id) }}" class="text-primary">{{ $pdc->pdc_no }}</a></td>
            <td>{{ $pdc->party->name ?? '' }}</td>
            <td class="text-end">{{ number_format($pdc->amount, 2) }}</td>
            <td class="text-end">{{ number_format($pdc->allocated_amount, 2) }}</td>
            <td class="text-end {{ $pdc->pending_amount > 0 ? 'text-danger' : '' }}">{{ number_format($pdc->pending_amount, 2) }}</td>
            <td><a href="{{ route('pdcs.show', $pdc->id) }}" class="btn btn-sm btn-outline-primary">Manage</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#pdcTable').DataTable({pageLength:50,order:[[0,'asc']]}));</script>
@endsection