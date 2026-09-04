@extends('layouts.app')
@section('title', 'Processing Issues')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Processing Issues</h2>
      @can('processing_issues.create')<a href="{{ route('processing_issues.create') }}" class="btn btn-primary">New Issue</a>@endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="piTable">
        <thead><tr><th>Date</th><th>Issue #</th><th>PO #</th><th>Location</th><th>Lot #</th><th>Items</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
          @foreach($issues as $issue)
          <tr>
            <td>{{ $issue->issue_date->format('d-M-Y') }}</td>
            <td>{{ $issue->issue_no }}</td>
            <td>{{ $issue->purchaseOrder->order_no ?? '' }}</td>
            <td>{{ $issue->location->name ?? '' }}</td>
            <td>{{ $issue->lot_no }}</td>
            <td class="small">@foreach($issue->items as $it){{ $it->product->name ?? '' }} ({{ $it->quantity }})@if(!$loop->last), @endif @endforeach</td>
            <td class="text-end">{{ number_format($issue->items->sum('amount'), 2) }}</td>
            <td>
              @can('processing_issues.delete')
              <form action="{{ route('processing_issues.destroy', $issue->id) }}" method="POST" class="d-inline">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
              </form>
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#piTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsectionwe