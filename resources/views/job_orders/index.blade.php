@extends('layouts.app')

@section('title', 'Job Orders | All')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @elseif (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ request('view_deleted') ? 'Deleted' : 'All' }} Job Orders</h2>
                <div>
                    @if(request('view_deleted'))
                        <a href="{{ route('jobs.index') }}" class="btn btn-default mr-2">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('jobs.index', ['view_deleted' => 1]) }}" class="btn btn-danger mr-2">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif
                    @can('jobs.create')
                    <a href="{{ route('jobs.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Job Order
                    </a>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="jobOrderTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Job #</th>
                                <th>Vendor</th>
                                <th>Type</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobOrders as $index => $job)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($job->issue_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">
                                    <a href="{{ route('jobs.show', $job->id) }}">{{ $job->job_no }}</a>
                                </td>
                                <td>{{ $job->vendor->name ?? 'N/A' }}</td>
                                <td>{{ $job->job_type ?? '—' }}</td>
                                <td class="small">
                                    @foreach($job->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge bg-{{ $job->status === 'Received' ? 'success' : ($job->status === 'PartiallyReceived' ? 'warning text-dark' : 'info text-dark') }}">
                                        {{ $job->status }}
                                    </span>
                                </td>
                                <td>
                                    @if($job->trashed())
                                        <span class="text-muted">Deleted</span>
                                    @else
                                        <a href="{{ route('jobs.print', $job->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @can('jobs.delete')
                                        <form action="{{ route('jobs.destroy', $job->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this job order? This reverses the stock issue.')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    @endif
                                    @can('jobs.edit')
                                    @if($job->status === 'Issued')
                                    <a href="{{ route('jobs.edit', $job->id) }}" class="text-primary mr-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @else
                                    <span class="text-secondary me-2" title="Cannot edit — job has receives recorded" style="cursor:help;">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    @endif
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#jobOrderTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection