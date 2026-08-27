{{-- reprocessing_issues/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Reprocessing Issues')
@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reprocessing Issues</h2>
                @can('greige_processing.create')
                <a href="{{ route('reprocessing_issues.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Issue</a>
                @endcan
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="riTable">
                        <thead><tr><th>#</th><th>Date</th><th>Issue #</th><th>Original GRN</th><th>Vendor</th><th>Items</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($issues as $index => $issue)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($issue->issue_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $issue->issue_no }}</td>
                                <td>{{ $issue->originalReceiving->receiving_no ?? '' }}</td>
                                <td>{{ $issue->originalReceiving->greigeProcessingOrder->vendor->name ?? '' }}</td>
                                <td class="small">
                                    @foreach($issue->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td><span class="badge bg-{{ $issue->status === 'Received' ? 'success' : ($issue->status === 'PartiallyReceived' ? 'warning text-dark' : 'info text-dark') }}">{{ $issue->status }}</span></td>
                                <td>
                                    <a href="{{ route('reprocessing_issues.print', $issue->id) }}" target="_blank" class="text-success mr-2"><i class="fas fa-print"></i></a>
                                    @can('greige_processing.delete')
                                    @if($issue->status === 'Issued')
                                    <form action="{{ route('reprocessing_issues.destroy', $issue->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete?')"><i class="fa fa-trash-alt"></i></button>
                                    </form>
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
<script>$(document).ready(function() { $('#riTable').DataTable({ pageLength: 50, order: [[0,'desc']] }); });</script>
@endsection