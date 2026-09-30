@extends('layouts.app')
@section('title', 'Review Purchase | ' . $challan->challan_no)
@section('content')
@php
  $matchedVendor = $vendors->first(fn($v) => strcasecmp($v->name, (string) $challan->direct_vendor_name) === 0);
@endphp
<div class="row"><div class="col">
  <form action="{{ route('challans.review_direct', $challan->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Review Purchase Without PO — {{ $challan->challan_no }}</h2></header>
      <div class="card-body">
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="row mb-3">
          <div class="col-md-3"><strong>Vendor (as typed):</strong> {{ $challan->direct_vendor_name }}</div>
          <div class="col-md-3"><strong>Category:</strong> {{ $challan->category->name ?? '—' }}</div>
          <div class="col-md-3"><strong>Date:</strong> {{ $challan->received_date->format('d-M-Y') }}</div>
          <div class="col-md-3"><strong>Logged by:</strong> {{ $challan->receivedBy->name ?? '' }}</div>
        </div>
        @if($challan->remarks)<div class="alert alert-secondary py-2"><strong>Remarks:</strong> {{ $challan->remarks }}</div>@endif
        <div class="mb-3">
          @foreach($challan->challan_images as $img)
            <a href="{{ asset('storage/' . $img) }}" target="_blank"><img src="{{ asset('storage/' . $img) }}" style="height:90px;margin-right:8px;border:1px solid #ccc;"></a>
          @endforeach
        </div>

        <h6>Accounting</h6>
        <div class="row mb-3">
          <div class="col-md-3">
            <label>Payable To — Vendor</label>
            <select name="payable_vendor_id" class="form-control select2-js">
              <option value="">— none / other account —</option>
              @foreach($vendors as $v)<option value="{{ $v->id }}" @selected(old('payable_vendor_id', $matchedVendor->id ?? null) == $v->id)>{{ $v->name }}</option>@endforeach
            </select>
            <small class="text-muted">Hits the vendor's ledger (Accounts Payable).</small>
          </div>
          <div class="col-md-3">
            <label>…or Payable Account</label>
            <select name="payable_account_id" class="form-control select2-js">
              <option value="">— select —</option>
              @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('payable_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <small class="text-muted">Used only if no vendor is selected.</small>
          </div>
          <div class="col-md-3">
            <label>Paid From (optional)</label>
            <select name="paid_from_account_id" class="form-control select2-js">
              <option value="">— not paid yet —</option>
              @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('paid_from_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <small class="text-muted">Petty cash / bank / staff — settles the payable immediately.</small>
          </div>
          <div class="col-md-3">
            <label>Stock Location <span class="text-danger">*</span></label>
            <select name="location_id" class="form-control select2-js" required>
              @foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
          </div>
        </div>

        <table class="table table-bordered">
          <thead><tr><th width="18%">Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th><th width="12%">Treatment</th><th>Link</th></tr></thead>
          <tbody>
            @foreach($challan->directItems as $i => $item)
            @php $unitMatch = $units->first(fn($u) => strcasecmp($u->shortcode, (string) $item->unit) === 0 || strcasecmp($u->name, (string) $item->unit) === 0); @endphp
            <tr class="line-row" data-name="{{ $item->description }}">
              <td>{{ $item->description }}<input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}"></td>
              <td class="text-end">{{ number_format($item->quantity, 3) }} {{ $item->unit }}</td>
              <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
              <td class="text-end">{{ number_format($item->amount, 2) }}</td>
              <td>
                <select name="items[{{ $i }}][treatment]" class="form-control treatment-select">
                  <option value="stock">Stock In</option>
                  <option value="expense">Expense</option>
                </select>
              </td>
              <td>
                <div class="stock-fields">
                  <select name="items[{{ $i }}][product_category_id]" class="form-control mb-1 cat-select">
                    <option value="">Product category…</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" @selected($challan->category_id == $c->id)>{{ $c->name }}</option>@endforeach
                  </select>
                  <select name="items[{{ $i }}][product_id]" class="form-control mb-1 product-select">
                    <option value="new">➕ Create new item "{{ $item->description }}"</option>
                  </select>
                  <select name="items[{{ $i }}][measurement_unit_id]" class="form-control unit-select">
                    <option value="">Unit (for new item)…</option>
                    @foreach($units as $u)<option value="{{ $u->id }}" @selected(($unitMatch->id ?? null) == $u->id)>{{ $u->name }} ({{ $u->shortcode }})</option>@endforeach
                  </select>
                </div>
                <div class="expense-fields" style="display:none">
                  <select name="items[{{ $i }}][expense_account_id]" class="form-control">
                    <option value="">Expense account…</option>
                    @foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                  </select>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
          <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">{{ number_format($challan->directItems->sum('amount'), 2) }}</td><td colspan="2"></td></tr></tfoot>
        </table>

        <div class="alert alert-info py-2">
          <strong>Stock In</strong> adds the quantity to stock (value = amount) and debits the category's stock account.
          <strong>Expense</strong> debits the chosen expense account. The total is credited to the payable above.
        </div>
      </div>
      <footer class="card-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        <button type="submit" class="btn btn-success">Post Purchase</button>
      </footer>
    </section>
  </form>

  <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form action="{{ route('challans.reject_direct', $challan->id) }}" method="POST">
      @csrf
      <div class="modal-body"><textarea name="reason" class="form-control" rows="3" required placeholder="Reason for rejecting"></textarea></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Confirm Reject</button></div>
    </form>
  </div></div></div>
</div></div>

<script>
  $(document).on('change', '.treatment-select', function () {
    const stock = $(this).val() === 'stock', row = $(this).closest('tr');
    row.find('.stock-fields').toggle(stock);
    row.find('.expense-fields').toggle(!stock);
  });

  $(document).on('change', '.cat-select', function () {
    const row = $(this).closest('tr'), $p = row.find('.product-select'), name = row.data('name'), catId = $(this).val();
    $p.html(`<option value="new">➕ Create new item "${name}"</option>`);
    if (!catId) return;
    fetch(`/purchase-orders/category-products/${catId}`).then(r => r.json()).then(list => {
      list.forEach(p => $p.append(`<option value="${p.id}">${p.name} (${p.sku})</option>`));
    });
  });

  $('.cat-select').each(function () { if ($(this).val()) $(this).trigger('change'); });
</script>
@endsection