@extends('layouts.app')
@section('title', $challan->challan_no)
@section('content')
@php($po = $challan->purchaseOrder)
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $challan->challan_no }}</h2>
      <span class="badge bg-{{ $challan->status_badge }}">{{ $challan->status_label }}</span>
    </header>
    <div class="card-body">

      @if($challan->entry_type === 'direct')
        <div class="row mb-3">
          <div class="col-md-3"><strong>Vendor (typed):</strong> {{ $challan->direct_vendor_name ?? '—' }}</div>
          <div class="col-md-3"><strong>Category:</strong> {{ $challan->category->name ?? '' }}</div>
          <div class="col-md-3"><strong>Entry Type:</strong> Without PO</div>
        </div>
      @else
        <div class="row mb-3">
          <div class="col-md-3"><strong>PO #:</strong> <a href="{{ route('purchase_orders.show', $po->id) }}">{{ $po->order_no }}</a> <small class="text-muted">({{ ucfirst($po->type) }})</small></div>
          <div class="col-md-3"><strong>Category:</strong> {{ $po->category->name ?? '' }}</div>
          <div class="col-md-3"><strong>Vendor:</strong> {{ $po->vendor->name ?? '' }}</div>
          <div class="col-md-3"><strong>Vendor Challan #:</strong> {{ $challan->vendor_challan_no ?? '—' }}</div>
        </div>
      @endif

      <div class="mb-3"><strong>Received at gate:</strong> {{ $challan->received_date->format('d-M-Y') }} by {{ $challan->receivedBy->name ?? '' }}</div>
      @if($challan->remarks)<div class="mb-3"><strong>Remarks:</strong> {{ $challan->remarks }}</div>@endif

      @if($challan->has_objection)
        <div class="alert alert-warning">
          <strong>Gate objection:</strong> {{ $challan->objection_remarks }}
          @if($challan->objection_voice_note)
            <div class="mt-2">
              <audio controls preload="metadata"><source src="{{ \App\Support\Media::url($challan->objection_voice_note) }}" type="audio/mp4">Your browser cannot play this voice note.</audio>
              <a href="{{ \App\Support\Media::url($challan->objection_voice_note) }}" target="_blank" class="small ms-2">Download</a>
            </div>
          @endif
        </div>
      @endif

      {{-- ── Review outcome ── --}}
      @if($challan->decision)
        <div class="alert alert-{{ $challan->status_badge === 'secondary' ? 'light' : explode(' ', $challan->status_badge)[0] }}">
          <strong>Incharge decision:</strong> {{ \App\Models\Challan::DECISIONS[$challan->decision] ?? $challan->decision }}
          @if($challan->reviewedBy) — {{ $challan->reviewedBy->name }}, {{ $challan->reviewed_at?->format('d-M-Y H:i') }}@endif
          @if($challan->decision_remarks)<br>{{ $challan->decision_remarks }}@endif
          @if($challan->receiving)<br>GRN: <a href="{{ route('purchase_receivings.show', $challan->receiving->id) }}">{{ $challan->receiving->receiving_no }}</a> — Rs. {{ number_format($challan->receiving->amount, 2) }}@endif
        </div>
      @endif
      @if($challan->amendment)
        <div class="alert alert-info">
          <strong>Amendment #{{ $challan->amendment->amendment_no }}</strong> — {{ $challan->amendment->status }}
          @foreach($challan->amendment->change_lines as $line)<div class="small">{{ $line }}</div>@endforeach
          @if($challan->amendment->rejection_reason)<div class="small">Rejected: {{ $challan->amendment->rejection_reason }}</div>@endif
        </div>
      @endif

      @if($challan->entry_type === 'direct')
        <h6>Items (Without PO)</h6>
        <table class="table table-sm table-bordered">
          <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th><th>Treatment</th></tr></thead>
          <tbody>
            @foreach($challan->directItems as $item)
            <tr>
              <td>{{ $item->description }}</td>
              <td class="text-end">{{ number_format($item->quantity, 3) }} {{ $item->unit }}</td>
              <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
              <td class="text-end">{{ number_format($item->amount, 2) }}</td>
              <td>{{ $item->treatment === 'pending' ? '—' : ucfirst($item->treatment) }}</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">{{ number_format($challan->directItems->sum('amount'), 2) }}</td><td></td></tr></tfoot>
        </table>
      @else
        <h6>Items</h6>
        <table class="table table-sm table-bordered">
          <thead><tr><th>Item</th><th class="text-end">Expected</th><th class="text-end">Gate Count</th><th class="text-end">Accepted</th><th class="text-end">Rejected</th><th>Line Status</th></tr></thead>
          <tbody>
            @forelse($challan->items as $item)
            <tr>
              <td>{{ $item->display_name }}@if($item->rejection_note)<br><small class="text-muted">{{ $item->rejection_note }}</small>@endif</td>
              <td class="text-end">{{ number_format($item->expected_qty, 3) }}</td>
              <td class="text-end">{{ number_format($item->received_qty, 3) }}</td>
              <td class="text-end">{{ $challan->decision && $challan->decision !== 'amend' ? number_format($item->accepted_qty, 3) : '—' }}</td>
              <td class="text-end">{{ $challan->decision && $challan->decision !== 'amend' ? number_format($item->rejected_qty, 3) : '—' }}</td>
              <td>{{ ucfirst($item->decision) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-muted">No gate count recorded — the incharge enters quantities during inspection.</td></tr>
            @endforelse
          </tbody>
        </table>
      @endif

      <h6>Challan Photo(s)</h6>
      <div class="row mb-3">
        @foreach($challan->challan_images ?? [] as $img)
        <div class="col-md-3 mb-2"><a href="{{ \App\Support\Media::url($img) }}" target="_blank"><img src="{{ \App\Support\Media::url($img) }}" class="img-fluid border rounded"></a></div>
        @endforeach
      </div>
    </div>
    <footer class="card-footer d-flex justify-content-between">
      <div>
        @if($challan->isAwaitingReview() && $challan->canBeReviewedBy(auth()->user()))
          @if($challan->entry_type === 'direct')
            <a href="{{ route('challans.review_direct_form', $challan->id) }}" class="btn btn-primary">Review Purchase</a>
          @else
            <a href="{{ route('challans.review_form', $challan->id) }}" class="btn btn-primary">Inspect &amp; Decide</a>
          @endif
        @endif
      </div>
      <div>
        <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
        <a href="{{ route('challans.index') }}" class="btn btn-outline-secondary">Back</a>
      </div>
    </footer>
  </section>
</div></div>
@endsection
