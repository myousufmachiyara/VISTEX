{{-- reprocessing_receives/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Reprocessing Receives')
@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reprocessing Receives</h2>
                @can('greige_processing.create')
                <a href="{{ route('reprocessing_receives.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Receive</a>
                @endcan
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="rrTable">
                        <thead><tr><th>#</th><th>Date</th><th>Receive #</th><th>Issue #</th><th>Challan #</th><th>Items</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($receives as $index => $receive)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($receive->receive_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $receive->receive_no }}</td>
                                <td>{{ $receive->reprocessingIssue->issue_no ?? '' }}</td>
                                <td>{{ $receive->vendor_challan_no }}</td>
                                <td class="small">
                                    @foreach($receive->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity_received }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td>
                                    <a href="{{ route('reprocessing_receives.print', $receive->id) }}" target="_blank" class="text-success mr-2"><i class="fas fa-print"></i></a>
                                    @can('greige_processing.delete')
                                    <form action="{{ route('reprocessing_receives.destroy', $receive->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete?')"><i class="fa fa-trash-alt"></i></button>
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
<script>$(document).ready(function() { $('#rrTable').DataTable({ pageLength: 50, order: [[0,'desc']] }); });</script>
@endsection 