@extends('layouts.app')
@section('title', $challan->challan_no)
@section('content')
<div class="row"><div class="col">
  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">{{ $challan->challan_no }}</h2>
      <span class="badge bg-{{ $challan->status === 'Processed' ? 'success' : 'warning text-dark' }}">{{ $challan->status === 'AwaitingInspection' ? 'Awaiting Inspection' : 'Processed' }}</span>
    </header>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-3"><strong>PO #:</strong> {{ $challan->purchaseOrder->order_no ?? '' }}</div>
        <div class="col-md-3"><strong>Category:</strong> {{ $challan->purchaseOrder->category->name ?? '' }}</div>
        <div class="col-md-3"><strong>Vendor:</strong> {{ $challan->purchaseOrder->vendor->name ?? '' }}</div>
        <div class="col-md-3"><strong>Vendor Challan #:</strong> {{ $challan->vendor_challan_no ?? '—' }}</div>
      </div>
      <div class="mb-3"><strong>Received Date:</strong> {{ $challan->received_date->format('d-M-Y') }} by {{ $challan->receivedBy->name ?? '' }}</div>

      <h6>Challan Photo(s)</h6>
      <div class="row mb-3">
        @foreach($challan->challan_images as $img)
        <div class="col-md-4 mb-2"><a href="{{ Storage::url($img) }}" target="_blank"><img src="{{ Storage::url($img) }}" class="img-fluid border rounded"></a></div>
        @endforeach
      </div>

      @if($challan->remarks)<div class="mb-3"><strong>Remarks:</strong> {{ $challan->remarks }}</div>@endif

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

      @if($challan->status === 'AwaitingInspection')
      <div class="alert alert-info mt-3">
        Print this challan, physically inspect the goods, then proceed to Receiving to Approve, Reject, Return, or Amend.
        @if($challan->status === 'AwaitingInspection')
          <a href="{{ route('purchase_receivings.create', $challan->id) }}" class="btn btn-primary mt-3">Proceed to Receiving</a>
          <a href="{{ route('purchase_order_objections.create', $challan->purchase_order_id) }}" class="btn btn-outline-danger mt-3">Report Objection</a>
        @endif
      </div>
      @endif
    </div>
    <footer class="card-footer text-end">
      <button onclick="window.print()" class="btn btn-outline-secondary">Print</button>
      <a href="{{ route('challans.pending') }}" class="btn btn-outline-secondary">Back</a>
    </footer>
  </section>
</div></div>
@endsection