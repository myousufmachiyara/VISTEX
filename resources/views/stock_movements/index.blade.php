@extends('layouts.app')

@section('title', 'Stock Movements')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Stock Movements</h2>
                <div>
                    <a href="{{ route('stock_movements.pending_approvals') }}" class="btn btn-warning mr-2">
                        <i class="fas fa-clock"></i> Pending My Approval
                    </a>
                    @can('stock_movements.create')
                    <a href="{{ route('stock_movements.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Movement
                    </a>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-2">
                        <select name="movement_type" class="form-control" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="transfer" @selected(request('movement_type') == 'transfer')>Transfer</option>
                            <option value="adjustment" @selected(request('movement_type') == 'adjustment')>Adjustment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="Pending" @selected(request('status') == 'Pending')>Pending</option>
                            <option value="Accepted" @selected(request('status') == 'Accepted')>Accepted</option>
                            <option value="Objected" @selected(request('status') == 'Objected')>Objected</option>
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
                    <table class="table table-bordered table-striped" id="smTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Movement #</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $index => $m)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($m->movement_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $m->movement_no }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($m->movement_type) }}</span></td>
                                <td>{{ $m->fromLocation->name ?? '—' }}</td>
                                <td>{{ $m->toLocation->name ?? '—' }}</td>
                                <td class="small">
                                    @foreach($m->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ $item->quantity }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge bg-{{ $m->status === 'Accepted' ? 'success' : ($m->status === 'Objected' ? 'danger' : 'warning text-dark') }}">
                                        {{ $m->status }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('stock_movements.print', $m->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @can('stock_movements.delete')
                                    @if($m->status === 'Pending')
                                    <form action="{{ route('stock_movements.destroy', $m->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this movement?')" title="Delete">
                                            <i class="fa fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-secondary" title="Cannot delete — already responded to" style="cursor:help;">
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
        $('#smTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection