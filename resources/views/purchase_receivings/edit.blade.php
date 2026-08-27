@extends('layouts.app')

@section('title', 'Purchase Receiving | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_receivings.update', $receiving->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Receiving — {{ $receiving->receiving_no }}</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Saving will reverse and redo this receiving's stock entries.
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Purchase Order</label>
              <input type="text" class="form-control" value="{{ $receiving->purchaseOrder->order_no ?? 'N/A' }} — {{ $receiving->purchaseOrder->vendor->name ?? '' }}" disabled>
              <small class="text-muted">Purchase order cannot be changed after creation.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receiving Location <span class="text-danger">*</span></label>
              <select name="location_id" class="form-control select2-js" required>
                <option value="">Select Location</option>
                @foreach ($locations as $loc)
                  <option value="{{ $loc->id }}" @selected($loc->id == $receiving->location_id)>{{ $loc->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receiving Date</label>
              <input type="date" name="receiving_date" class="form-control" value="{{ $receiving->receiving_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor Challan #</label>
              <input type="text" name="vendor_challan_no" class="form-control" value="{{ $receiving->vendor_challan_no }}">
            </div>

            <div class="col-md-6 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($receiving->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($receiving->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-6 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1">{{ $receiving->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Ordered</th>
                  <th>Outstanding (incl. this receiving)</th>
                  <th>Receiving Now</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($poItems as $i => $row)
                <tr>
                  <td>
                    {{ $row['product_name'] }}
                    <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $row['purchase_order_item_id'] }}">
                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $row['product_id'] }}">
                  </td>
                  <td>{{ $row['ordered'] }}</td>
                  <td>{{ $row['outstanding'] }}</td>
                  <td>
                    <input type="number" name="items[{{ $i }}][quantity_received]" class="form-control"
                           value="{{ $row['current_quantity'] }}" step="any" min="0" max="{{ $row['outstanding'] }}">
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Receiving</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });
</script>
@endsection