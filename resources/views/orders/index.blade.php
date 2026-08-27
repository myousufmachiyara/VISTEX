@extends('layouts.app')

@section('title', 'Orders')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Customer Orders</h2>
                @can('orders.create')
                <a href="{{ route('orders.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Order
                </a>
                @endcan
            </header>

            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            @foreach(['Open','InProduction','PartiallyDispatched','Dispatched','Closed','Cancelled'] as $s)
                                <option value="{{ $s }}" @selected(request('status') == $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" onchange="this.form.submit()">
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="orderTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Collection / Article</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Advance</th>
                                <th>Status</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $index => $order)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">
                                    <a href="{{ route('orders.show', $order->id) }}">{{ $order->order_no }}</a>
                                </td>
                                <td>{{ $order->customer->name ?? 'N/A' }}</td>
                                <td class="small">{{ $order->collection }} / {{ $order->article }}</td>
                                <td class="text-end">{{ number_format($order->total_amount, 2) }}</td>
                                <td class="text-end {{ $order->advance_balance > 0 ? 'text-success fw-bold' : '' }}">
                                    {{ number_format($order->advance_balance, 2) }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ match($order->status) {
                                        'Dispatched', 'Closed' => 'success',
                                        'Cancelled' => 'danger',
                                        'InProduction', 'PartiallyDispatched' => 'warning text-dark',
                                        default => 'info text-dark',
                                    } }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td>
                                    @can('orders.edit')
                                    <a href="{{ route('orders.edit', $order->id) }}" class="text-primary mr-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    <a href="{{ route('orders.print', $order->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @can('orders.delete')
                                    <form action="{{ route('orders.destroy', $order->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this order?')" title="Delete">
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
        $('.select2-js').select2({ width: '100%' });
        $('#orderTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection