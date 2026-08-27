@extends('layouts.app')

@section('title', 'Pending My Approval')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Movements Pending My Approval</h2>
                <a href="{{ route('stock_movements.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> All Movements
                </a>
            </header>

            <div class="card-body">
                @forelse ($movements as $m)
                <div class="card mb-3 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong class="text-primary">{{ $m->movement_no }}</strong>
                                <span class="badge bg-secondary ms-2">{{ ucfirst($m->movement_type) }}</span>
                                <br>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($m->movement_date)->format('d-M-Y') }}</small>
                            </div>
                            <div class="text-end">
                                <div><strong>From:</strong> {{ $m->fromLocation->name ?? '—' }}</div>
                                <div><strong>To:</strong> {{ $m->toLocation->name ?? '—' }}</div>
                            </div>
                        </div>

                        <table class="table table-sm table-bordered mb-2">
                            <thead><tr><th>Product</th><th class="text-end">Quantity</th></tr></thead>
                            <tbody>
                                @foreach($m->items as $item)
                                <tr>
                                    <td>{{ $item->product->name ?? '' }}</td>
                                    <td class="text-end">{{ number_format($item->quantity, 3) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($m->remarks)
                            <p class="text-muted small">Remarks: {{ $m->remarks }}</p>
                        @endif

                        <div class="d-flex gap-2">
                            <form action="{{ route('stock_movements.accept', $m->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Accept this movement?')">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                            </form>

                            <button type="button" class="btn btn-danger btn-sm" onclick="showObjectForm({{ $m->id }})">
                                <i class="fas fa-times"></i> Object
                            </button>
                        </div>

                        <form action="{{ route('stock_movements.object', $m->id) }}" method="POST" class="mt-2" id="objectForm{{ $m->id }}" style="display:none">
                            @csrf
                            <div class="input-group">
                                <input type="text" name="objection_reason" class="form-control" placeholder="Reason for objection..." required>
                                <button type="submit" class="btn btn-outline-danger">Submit Objection</button>
                            </div>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-4">No movements pending your approval.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>

<script>
function showObjectForm(id) {
    $('#objectForm' + id).slideToggle();
}
</script>
@endsection