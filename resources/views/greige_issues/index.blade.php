@extends('layouts.app')

@section('title', 'Greige Issues')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Greige Issues</h2>
                @can('greige_processing.create')
                <a href="{{ route('greige_issues.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Issue
                </a>
                @endcan
            </header>

            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-2">
                        <select name="invoice_status" class="form-control" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="WIP" @selected(request('invoice_status') == 'WIP')>WIP</option>
                            <option value="Fresh" @selected(request('invoice_status') == 'Fresh')>Fresh</option>
                        </select>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="giTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Issue #</th>
                                <th>Processing PO</th>
                                <th>Vendor</th>
                                <th>Location</th>
                                <th>Lot #</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issues as $index => $issue)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($issue->issue_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $issue->issue_no }}</td>
                                <td>{{ $issue->greigeProcessingOrder->gppo_no ?? '' }}</td>
                                <td>{{ $issue->greigeProcessingOrder->vendor->name ?? '' }}</td>
                                <td>{{ $issue->location->name ?? '' }}</td>
                                <td>{{ $issue->lot_no ?? '—' }}</td>
                                <td class="small">
                                    @foreach($issue->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td>
                                    <form action="{{ route('greige_issues.toggle_status', $issue->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="badge border-0 bg-{{ $issue->invoice_status === 'Fresh' ? 'success' : 'warning text-dark' }}">
                                            {{ $issue->invoice_status }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="{{ route('greige_issues.print', $issue->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @can('greige_processing.delete')
                                    <form action="{{ route('greige_issues.destroy', $issue->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this issue?')" title="Delete">
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
    $(document).ready(function() { $('#giTable').DataTable({ pageLength: 50, order: [[0, 'desc']] }); });
</script>
@endsection