@extends('layouts.app')

@section('title', 'Purchase Returns | All')

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
                <h2 class="card-title">{{ request('view_deleted') ? 'Deleted' : 'All' }} Purchase Returns</h2>
                <div>
                    @if(request('view_deleted'))
                        <a href="{{ route('purchase_returns.index') }}" class="btn btn-default mr-2">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('purchase_returns.index', ['view_deleted' => 1]) }}" class="btn btn-danger mr-2">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif
                    <a href="{{ route('purchase_returns.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Purchase Return
                    </a>
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="purchaseReturnTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Return Date</th>
                                <th>Return #</th>
                                <th>Against Invoice</th>
                                <th>Vendor</th>
                                <th class="text-end">Amount</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($returns as $index => $return)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($return->return_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $return->return_no }}</td>
                                <td>{{ $return->purchase->purchase_no ?? 'N/A' }}</td>
                                <td>{{ $return->vendor->name ?? 'N/A' }}</td>
                                <td class="text-end">{{ number_format($return->total_amount, 2) }}</td>
                                <td>
                                    @if($return->trashed())
                                        <span class="text-muted">Deleted</span>
                                    @else
                                        <a href="{{ route('purchase_returns.print', $return->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <form action="{{ route('purchase_returns.destroy', $return->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this return? This reverses its stock and accounting entries.')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
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
        $('#purchaseReturnTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection