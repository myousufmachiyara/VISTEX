@extends('layouts.app')

@section('title', 'Yarn-to-Greige Orders')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Yarn-to-Greige Orders</h2>
                @can('yarn_to_greige.create')
                <a href="{{ route('yarn_to_greige.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Order
                </a>
                @endcan
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="ygpoTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>YGPO #</th>
                                <th>CPO #</th>
                                <th>Vendor</th>
                                <th>Location</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $index => $order)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->issue_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">
                                    <a href="{{ route('yarn_to_greige.show', $order->id) }}">{{ $order->ygpo_no }}</a>
                                </td>
                                <td>{{ $order->cpo->cpo_no ?? 'N/A' }}</td>
                                <td>{{ $order->cpo->vendor->name ?? 'N/A' }}</td>
                                <td>{{ $order->location->name ?? 'N/A' }}</td>
                                <td class="small">
                                    @foreach($order->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge bg-{{ $order->status === 'Received' ? 'success' : ($order->status === 'PartiallyReceived' ? 'warning text-dark' : 'info text-dark') }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td>
                                    @if($order->trashed())
                                        <span class="text-muted">Deleted</span>
                                    @else
                                        <a href="{{ route('yarn_to_greige.print', $order->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @can('yarn_to_greige.delete')
                                        @if($order->status === 'Issued')
                                        <form action="{{ route('yarn_to_greige.destroy', $order->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this order? This reverses the stock issue.')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        @else
                                        <span class="text-secondary" title="Cannot delete — has receives recorded" style="cursor:help;">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        @endif
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
        $('#ygpoTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection