@extends('layouts.app')

@section('title', 'Order — ' . $order->order_no)

@section('content')
<div class="row">
    <div class="col-md-8">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $order->order_no }}</h2>
                <span class="badge bg-{{ match($order->status) {
                    'Dispatched', 'Closed' => 'success',
                    'Cancelled' => 'danger',
                    'InProduction', 'PartiallyDispatched' => 'warning text-dark',
                    default => 'info text-dark',
                } }}">{{ $order->status }}</span>
            </header>

            @if(session('success'))<div class="alert alert-success m-3">{{ session('success') }}</div>@endif

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Customer:</strong> {{ $order->customer->name ?? 'N/A' }}</div>
                    <div class="col-md-3"><strong>Collection:</strong> {{ $order->collection ?? '—' }}</div>
                    <div class="col-md-3"><strong>Article:</strong> {{ $order->article ?? '—' }}</div>
                    <div class="col-md-3"><strong>Pattern #:</strong> {{ $order->pattern_no ?? '—' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Order Date:</strong> {{ $order->order_date->format('d-M-Y') }}</div>
                    <div class="col-md-3"><strong>Delivery Date:</strong> {{ $order->delivery_date?->format('d-M-Y') ?? '—' }}</div>
                </div>

                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Design / Print</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }} ({{ $item->product->sku ?? '' }})</td>
                            <td>{{ $item->design ?? '—' }}</td>
                            <td class="text-end">{{ number_format($item->quantity, 3) }}</td>
                            <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                            <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if($order->remarks)
                    <p><strong>Remarks:</strong> {{ $order->remarks }}</p>
                @endif

                @if($order->attachments)
                    <p><strong>Attachments:</strong>
                        @foreach($order->attachments as $path)
                            <a href="{{ Storage::url($path) }}" target="_blank" class="me-2"><i class="fas fa-file"></i></a>
                        @endforeach
                    </p>
                @endif
            </div>
        </section>
    </div>

    <div class="col-md-4">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-money-bill-wave me-1"></i> Advance</h2>
            </header>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td>Order Total</td>
                        <td class="text-end fw-bold">{{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Advance Received</td>
                        <td class="text-end text-success">{{ number_format($order->advance_received, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Advance Applied</td>
                        <td class="text-end text-muted">{{ number_format($order->advance_applied, 2) }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Available Balance</td>
                        <td class="text-end fw-bold {{ $order->advance_balance > 0 ? 'text-success' : '' }}">
                            {{ number_format($order->advance_balance, 2) }}
                        </td>
                    </tr>
                </table>

                @can('vouchers.create')
                <a href="{{ route('vouchers.create', 'receipt') }}?order_id={{ $order->id }}&customer_id={{ $order->customer_id }}"
                   class="btn btn-sm btn-primary w-100 mt-2">
                    <i class="fas fa-plus"></i> Record Advance Receipt
                </a>
                @endcan
            </div>
        </section>
    </div>
</div>
@endsection