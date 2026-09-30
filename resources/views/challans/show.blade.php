@extends('layouts.app')
@section('title', $challan->challan_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $challan->challan_no }}</h2>
      <span class="badge bg-{{ match($challan->status) {
          'Accepted', 'Processed' => 'success',
          'Rejected' => 'danger',
          'PartiallyAccepted' => 'warning text-dark',
          default => 'warning text-dark',
      } }}">
        {{ $challan->status === 'AwaitingInspection' ? 'Awaiting Inspection' : $challan->status }}
      </span>
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
          <div class="col-md-3"><strong>PO #:</strong> {{ $challan->purchaseOrder->order_no ?? '' }}</div>
          <div class="col-md-3"><strong>Category:</strong> {{ $challan->purchaseOrder->category->name ?? '' }}</div>
          <div class="col-md-3"><strong>Vendor:</strong> {{ $challan->purchaseOrder->vendor->name ?? '' }}</div>
          <div class="col-md-3"><strong>Vendor Challan #:</strong> {{ $challan->vendor_challan_no ?? '—' }}</div>
        </div>
      @endif

      <div class="mb-3"><strong>Received Date:</strong> {{ $challan->received_date->format('d-M-Y') }} by {{ $challan->receivedBy->name ?? '' }}</div>

      <h6>Challan Photo(s)</h6>
      <div class="row mb-3">
        @foreach($challan->challan_images as $img)
        <div class="col-md-4 mb-2"><a href="{{ Storage::url($img) }}" target="_blank"><img src="{{ Storage::url($img) }}" class="img-fluid border rounded"></a></div>
        @endforeach
      </div>

      @if($challan->remarks)<div class="mb-3"><strong>Remarks:</strong> {{ $challan->remarks }}</div>@endif

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
        <h6>Expected Items</h6>
        @if($challan->purchaseOrder->type === 'purchase')
          <table class="table table-sm table-bordered">
            <thead><tr><th>Product</th><th class="text-end">Ordered Qty</th></tr></thead>
            <tbody>
              @foreach($challan->purchaseOrder->items as $item)
              <tr><td>{{ $item->product->name ?? '' }}</td><td class="text-end">{{ number_format($item->quantity,3) }}</td></tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p>{{ $challan->purchaseOrder->item_name }} — {{ number_format($challan->purchaseOrder->total_meters_required,3) }} meters</p>
        @endif
      @endif

      @if($challan->has_objection)
        <div class="alert alert-warning mt-3">
          <strong>Received with objection:</strong> {{ $challan->objection_remarks }}
        </div>
        @if($challan->objection_voice_note)
          <div class="mb-2"><strong>Voice note:</strong><br><audio controls src="{{ asset('storage/' . $challan->objection_voice_note) }}"></audio></div>
        @endif
      @endif

    </div>
    <footer class="card-footer d-flex justify-content-between">
      <div>
        @if($challan->entry_type === 'direct' && $challan->status === 'AwaitingInspection')
          @can('challans.edit')<a href="{{ route('challans.review_direct_form', $challan->id) }}" class="btn btn-primary">Review Purchase</a>@endcan
        @endif
      </div>
      <div>
        <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
        <a href="{{ route('challans.pending') }}" class="btn btn-outline-secondary">Back</a>
      </div>
    </footer>
  </section>
</div></div>
@endsection