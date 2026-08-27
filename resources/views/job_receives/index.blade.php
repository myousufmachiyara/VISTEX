@extends('layouts.app')

@section('title', 'Job Receives | All')

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
                <h2 class="card-title">{{ request('view_deleted') ? 'Deleted' : 'All' }} Job Receives</h2>
                <div>
                    @if(request('view_deleted'))
                        <a href="{{ route('job_receives.index') }}" class="btn btn-default mr-2">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('job_receives.index', ['view_deleted' => 1]) }}" class="btn btn-danger mr-2">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif
                    @can('job_receives.create')
                    <a href="{{ route('job_receives.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Job Receive
                    </a>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="jobReceiveTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Receive #</th>
                                <th>Job Order</th>
                                <th>Vendor</th>
                                <th>Items</th>
                                <th class="text-end">Processing Charge</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receives as $index => $receive)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($receive->receive_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $receive->receive_no }}</td>
                                <td>{{ $receive->jobOrder->job_no ?? 'N/A' }}</td>
                                <td>{{ $receive->jobOrder->vendor->name ?? 'N/A' }}</td>
                                <td class="small">
                                    @foreach($receive->items as $item)
                                        {{ $item->rawProduct->name ?? '' }}: consumed {{ $item->quantity_consumed }}
                                        @if($item->quantity_leftover > 0)
                                            <span class="text-warning">(leftover {{ $item->quantity_leftover }})</span>
                                        @endif
                                        @if(!$loop->last)<br>@endif
                                    @endforeach
                                </td>
                                <td class="text-end">{{ number_format($receive->processing_charge, 2) }}</td>
                                <td>
                                    @if($receive->trashed())
                                        <span class="text-muted">Deleted</span>
                                    @else
                                        <a href="{{ route('job_receives.print', $receive->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @can('job_receives.edit')
                                        <a href="{{ route('job_receives.edit', $receive->id) }}" class="text-primary mr-2" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan
                                        @can('job_receives.delete')
                                        <form action="{{ route('job_receives.destroy', $receive->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this receive? This reverses the stock and voucher.')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    @endif
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
        $('#jobReceiveTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection