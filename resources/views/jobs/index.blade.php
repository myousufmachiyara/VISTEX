@extends('layouts.app')
@section('title', 'Jobs / Customer Orders')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Jobs / Customer Orders</h2>
      @can('jobs.create')<a href="{{ route('jobs.create') }}" class="btn btn-primary">New Job</a>@endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="jobTable">
        <thead><tr><th>Date</th><th>Job #</th><th>Customer</th><th>Customer PO#</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($jobs as $job)
          <tr>
            <td>{{ $job->order_date->format('d-M-Y') }}</td>
            <td><a href="{{ route('jobs.show', $job->id) }}" class="text-primary">{{ $job->job_no }}</a></td>
            <td>{{ $job->customer->name ?? '' }}</td>
            <td>{{ $job->customer_po_number ?? '—' }}</td>
            <td class="text-end">{{ number_format($job->total_amount, 2) }}</td>
            <td><span class="badge bg-{{ match($job->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $job->status }}</span></td>
            <td>
              @can('jobs.edit')
                @if($job->status === 'Pending')
                  <a href="{{ route('jobs.edit', $job->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                @endif
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#jobTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsection