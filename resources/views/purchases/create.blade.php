@extends('layouts.app')

@section('title', 'Purchase Invoice | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Purchase Invoice</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Receiving (GRN) <span class="text-muted">— optional, pulls items</span></label>
              <select name="receiving_id" id="receiving_select" class="form-control select2-js">
                <option value="">No linked receiving (manual entry)</option>
                @foreach ($receivings as $r)
                  <option value="{{ $r->id }}" data-vendor-id="{{ $r->purchaseOrder->vendor_id ?? '' }}"
                          @selected($selectedReceiving && $selectedReceiving->id == $r->id)>
                    {{ $r->receiving_no }} — {{ $r->purchaseOrder->vendor->name ?? '' }} ({{ $r->receiving_date->format('d-M-Y') }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" id="vendor_select" class="form-control select2-js" required {{ $selectedReceiving ? 'disabled' : '' }}>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $v)
                  <option value="{{ $v->id }}" @selected($selectedReceiving && $selectedReceiving->purchaseOrder->vendor_id == $v->id)>{{ $v->name }}</option>
                @endforeach
              </select>
              @if($selectedReceiving)
                <input type="hidden" name="vendor_id" value="{{ $selectedReceiving->purchaseOrder->vendor_id }}">
              @endif
            </div>

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Bill # (vendor's)</label>
              <input type="text" name="bill_no" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Ref #</label>
              <input type="text" name="ref_no" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Tax Amount</label>
              <input type="number" name="tax_amount" class="form-control" step="any" min="0" value="0">
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Amount</th><th></th></tr>
              </thead>
              <tbody id="itemsBody">
                @if($selectedReceiving)
                  @foreach($selectedReceiving->items as $i => $item)
                  <tr class="item-row">
                    <td>
                      {{ $item->product->name ?? '' }}
                      <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                    </td>
                    <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control qty-input" step="any" min="0" value="{{ $item->quantity_received }}"></td>
                    <td><input type="number" name="items[{{ $i }}][unit_price]" class="form-control price-input" step="any" min="0" value="{{ $item->rate }}"></td>
                    <td class="amount-cell text-end">{{ number_format($item->amount, 2) }}</td>
                    <td></td>
                  </tr>
                  @endforeach
                @else
                  <tr class="item-row">
                    <td>
                      <select name="items[0][product_id]" class="form-control select2-js">
                        <option value="">Select Product</option>
                        @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                      </select>
                    </td>
                    <td><input type="number" name="items[0][quantity]" class="form-control qty-input" step="any" min="0" value="0"></td>
                    <td><input type="number" name="items[0][unit_price]" class="form-control price-input" step="any" min="0" value="0"></td>
                    <td class="amount-cell text-end">0.00</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                  </tr>
                @endif
              </tbody>
              <tfoot>
                <tr><td colspan="3" class="text-end fw-bold">Total:</td><td class="fw-bold text-end" id="grandTotal">{{ number_format($selectedReceiving?->items->sum('amount') ?? 0, 2) }}</td><td></td></tr>
              </tfoot>
            </table>
            @unless($selectedReceiving)
            <button type="button" class="btn btn-outline-primary" id="addRowBtn"><i class="fas fa-plus"></i> Add Product</button>
            @endunless
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () { $('.select2-js').select2({ width: '100%' }); recalcTotal(); });

  $('#receiving_select').on('change', function () {
    const id = $(this).val();
    if (id) {
      window.location.href = "{{ route('purchase_invoices.create') }}?receiving_id=" + id;
    }
  });

  $('#addRowBtn').on('click', function () {
    const idx = Date.now();
    const row = $(`
      <tr class="item-row">
        <td><select name="items[${idx}][product_id]" class="form-control select2-js">
          <option value="">Select Product</option>
          @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
        </select></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][unit_price]" class="form-control price-input" step="any" min="0" value="0"></td>
        <td class="amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.qty-input, .price-input', function () {
    const row = $(this).closest('tr');
    const qty = parseFloat(row.find('.qty-input').val()) || 0;
    const price = parseFloat(row.find('.price-input').val()) || 0;
    row.find('.amount-cell').text((qty * price).toFixed(2));
    recalcTotal();
  });

  function recalcTotal() {
    let total = 0;
    $('.item-row').each(function () { total += parseFloat($(this).find('.amount-cell').text()) || 0; });
    $('#grandTotal').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) { $(this).closest('tr').remove(); recalcTotal(); }
  });
</script>
@endsection