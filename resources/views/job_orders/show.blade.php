@extends('layouts.app')

@section('title', 'Job Order — ' . $jobOrder->job_no)

@section('content')
<div class="row">
    <div class="col-md-8">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $jobOrder->job_no }}</h2>
                <span class="badge bg-{{ $jobOrder->status === 'Received' ? 'success' : ($jobOrder->status === 'PartiallyReceived' ? 'warning text-dark' : 'info text-dark') }}">
                    {{ $jobOrder->status }}
                </span>
            </header>

            @if(session('success'))
                <div class="alert alert-success m-3">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger m-3">{{ session('error') }}</div>
            @endif

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Vendor:</strong> {{ $jobOrder->vendor->name ?? 'N/A' }}</div>
                    <div class="col-md-4"><strong>Job Type:</strong> {{ $jobOrder->job_type ?? '—' }}</div>
                    <div class="col-md-4"><strong>Issue Date:</strong> {{ $jobOrder->issue_date->format('d-M-Y') }}</div>
                </div>

                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Quantity Issued</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobOrder->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'N/A' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td><span class="badge bg-light text-dark border">{{ ucfirst($item->source_status ?? '—') }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($jobOrder->remarks)
                    <p><strong>Remarks:</strong> {{ $jobOrder->remarks }}</p>
                @endif
            </div>
        </section>
    </div>

    {{-- ── Comments panel ────────────────────────────────────────────── --}}
    <div class="col-md-4">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-comments me-1"></i> Comments</h2>
            </header>
            <div class="card-body" style="max-height:400px; overflow-y:auto;">
                @forelse($jobOrder->comments as $comment)
                    <div class="mb-3 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong class="small">{{ $comment->user->name ?? 'User' }}</strong>
                            <span class="text-muted" style="font-size:.75rem">{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mb-1 small">{{ $comment->comment }}</p>
                        @if($comment->user_id === auth()->id() || auth()->user()->hasRole(['superadmin', 'admin']))
                        <form action="{{ route('jobs.comments.destroy', [$jobOrder->id, $comment->id]) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger" style="font-size:.72rem" onclick="return confirm('Delete comment?')">
                                Delete
                            </button>
                        </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small">No comments yet.</p>
                @endforelse
            </div>
            <footer class="card-footer">
                <form action="{{ route('jobs.comments.store', $jobOrder->id) }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <textarea name="comment" class="form-control" rows="2" placeholder="Add a comment or suggestion..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">
                        <i class="fas fa-paper-plane"></i> Post Comment
                    </button>
                </form>
            </footer>
        </section>
    </div>
</div>
@endsection