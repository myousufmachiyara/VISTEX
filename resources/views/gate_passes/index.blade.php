@extends('layouts.app')

@section('title', 'Gate Passes | All')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Gate Passes</h2>
                @can('gate_passes.create')
                <a href="{{ route('gate_passes.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Gate Pass
                </a>
                @endcan
            </header>

            <div class="card-body">
                <div class="alert alert-info py-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Moves stock between any two locations — our warehouses or vendor sites.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="gatePassTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Date</th>
                                <th>Gate Pass #</th>
                                <th>To Location</th>
                                <th>Items</th>
                                <th>Remarks</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gatePasses as $index => $gp)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($gp->entry_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $gp->doc_no }}</td>
                                <td>{{ $gp->to->name ?? 'N/A' }}</td>
                                <td class="small">
                                    @foreach($gp->items as $item)
                                        {{ $item->product->name ?? '' }} ({{ number_format($item->quantity, 3) }})@if(!$loop->last), @endif
                                    @endforeach
                                </td>
                                <td class="text-muted small">{{ Str::limit($gp->remarks, 40) }}</td>
                                <td>
                                    @can('gate_passes.edit')
                                    <a href="{{ route('gate_passes.edit', $gp->doc_no) }}" class="text-primary mr-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    <a href="{{ route('gate_passes.print', $gp->doc_no) }}" target="_blank" class="text-success mr-2" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @can('gate_passes.delete')
                                    <form action="{{ route('gate_passes.destroy', $gp->doc_no) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this gate pass?')" title="Delete">
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
        $('#gatePassTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection