@extends('layouts.app')
@section('title', 'Yarn Issues')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Yarn Issues</h2>
      @can('yarn_issues.create')<a href="{{ route('yarn_issues.create') }}" class="btn btn-primary">New Issue</a>@endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="yiTable">
        <thead><tr><th>#</th><th>Date</th><th>Issue #</th><th>CPO #</th><th>Vendor</th><th>Items</th><th>Amount</th><th>Actions</th></tr></thead>
        <tbody>
          @foreach ($issues as $i => $issue)
          <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $issue->issue_date->format('d-M-Y') }}</td>
            <td class="text-primary">{{ $issue->issue_no }}</td>
            <td>{{ $issue->cpo->cpo_no ?? '' }}</td>
            <td>{{ $issue->cpo->vendor->name ?? '' }}</td>
            <td class="small">@foreach($issue->items as $it){{ $it->product->name ?? '' }} ({{ $it->quantity }})@if(!$loop->last), @endif @endforeach</td>
            <td>{{ number_format($issue->items->sum('amount'), 2) }}</td>
            <td>
              @can('yarn_issues.edit')
                <a href="{{ route('yarn_issues.edit', $issue->id) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a>
              @endcan
              <a href="{{ route('yarn_issues.print', $issue->id) }}" target="_blank" class="btn btn-sm btn-outline-success me-1">Print</a>
              @can('yarn_issues.delete')
              <form action="{{ route('yarn_issues.destroy', $issue->id) }}" method="POST" class="d-inline">@csrf @method('DELETE')
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
<script>$(document).ready(()=>$('#yiTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection