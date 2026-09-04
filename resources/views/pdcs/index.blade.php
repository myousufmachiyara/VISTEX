@extends('layouts.app')
@section('title', 'PDC — Post Dated Cheques')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Post Dated Cheques</h2>
      <a href="{{ route('pdcs.uncleared') }}" class="btn btn-outline-warning">Unclear Cheques</a>
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['Pending','Created','Signed','Issued','Cleared','Bounced'] as $s)
              <option value="{{ $s }}" @selected(request('status')==$s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped" id="pdcTable">
        <thead><tr><th>Due Date</th><th>PDC #</th><th>PO #</th><th>GRN #</th><th>Party</th><th class="text-end">Amount</th><th>Bank</th><th>Cheque #</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($pdcs as $pdc)
          <tr>
            <td>{{ $pdc->due_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('pdcs.show', $pdc->id) }}" class="text-primary">{{ $pdc->pdc_no }}</a></td>
            <td>
              @if($pdc->reference_type === 'PurchaseReceiving' && $pdc->receivingRef)
                <a href="{{ route('purchase_orders.show', $pdc->receivingRef->purchase_order_id) }}" target="_blank">
                  {{ $pdc->receivingRef->purchaseOrder->order_no ?? '' }}
                </a>
              @else
                —
              @endif
            </td>
            <td>
              @if($pdc->reference_type === 'PurchaseReceiving' && $pdc->receivingRef)
                <a href="{{ route('purchase_receivings.show', $pdc->receivingRef->id) }}" target="_blank">
                  {{ $pdc->receivingRef->receiving_no }}
                </a>
              @else
                —
              @endif
            </td>
            <td>{{ $pdc->party->name ?? '' }}</td>
            <td class="text-end">{{ number_format($pdc->amount, 2) }}</td>
            <td>{{ $pdc->bankAccount->name ?? '—' }}</td>
            <td>{{ $pdc->cheque_no ?? '—' }}</td>
            <td><span class="badge bg-{{ match($pdc->status){'Cleared'=>'success','Bounced'=>'danger','Issued'=>'info',default=>'warning text-dark'} }}">{{ $pdc->status }}</span></td>
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