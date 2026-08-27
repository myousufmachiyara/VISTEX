@extends('layouts.app')

@section('title', 'Quality Checks | All')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Quality Checks</h2>
                @can('quality_checks.create')
                <a href="{{ route('quality_checks.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New QC
                </a>
                @endcan
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="qcTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>QC #</th>
                                <th>Job Receive</th>
                                <th>Vendor</th>
                                <th>Product</th>
                                <th class="text-end">Inspected</th>
                                <th class="text-end">Passed</th>
                                <th class="text-end">Rejected</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($qcs as $index => $qc)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($qc->qc_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $qc->qc_no }}</td>
                                <td>{{ $qc->jobOrderReceive->receive_no ?? 'N/A' }}</td>
                                <td>{{ $qc->jobOrderReceive->jobOrder->vendor->name ?? 'N/A' }}</td>
                                <td>{{ $qc->product->name ?? 'N/A' }}</td>
                                <td class="text-end">{{ number_format($qc->quantity_inspected, 3) }}</td>
                                <td class="text-end text-success">{{ number_format($qc->quantity_passed, 3) }}</td>
                                <td class="text-end {{ $qc->quantity_rejected > 0 ? 'text-danger fw-bold' : '' }}">
                                    {{ number_format($qc->quantity_rejected, 3) }}
                                </td>
                                <td>
                                    <a href="{{ route('quality_checks.print', $qc->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @can('quality_checks.delete')
                                    <form action="{{ route('quality_checks.destroy', $qc->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this QC record? This restores the rejected quantity to stock.')" title="Delete">
                                            <i class="fa fa-trash-alt"></i>
                                        </button>
                                    </form>
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
        $('#qcTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection