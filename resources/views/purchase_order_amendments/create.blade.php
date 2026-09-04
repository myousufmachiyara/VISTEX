@extends('layouts.app')
@section('title', 'Amend ' . $po->order_no)
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('purchase_order_amendments.store', $po->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
    @csrf
    <section class="card">
      <header class="card-header"><h2 class="card-title">Amend {{ $po->order_no }}</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <p class="text-muted">Only the fields you change below will be included. The original PO values are preserved for record; this amendment becomes the effective version once approved.</p>

        <div class="row">
          <div class="col-md-4 mb-3"><label>Expected Date</label><input type="date" name="expected_date" class="form-control" value="{{ $po->expected_date?->format('Y-m-d') }}"></div>
          @if($po->type === 'weaving')
          <div class="col-md-4 mb-3"><label>Total Meters Required</label><input type="number" name="total_meters_required" class="form-control" step="any" value="{{ $po->total_meters_required }}"></div>
          <div class="col-md-4 mb-3"><label>Rate per Pick</label><input type="number" name="rate_per_pick" class="form-control" step="any" value="{{ $po->rate_per_pick }}"></div>
          @endif
          <div class="col-md-4 mb-3">
            <label>Payment Term</label>
            <select name="payment_term_type" class="form-control">
              <option value="">No change</option>
              <option value="cash" @selected($po->payment_term_type=='cash')>Cash</option>
              <option value="credit" @selected($po->payment_term_type=='credit')>Credit</option>
              <option value="pdc" @selected($po->payment_term_type=='pdc')>PDC</option>
              <option value="other" @selected($po->payment_term_type=='other')>Other</option>
            </select>
          </div>
          <div class="col-md-4 mb-3"><label>Payment Days</label><input type="number" name="payment_term_days" class="form-control" value="{{ $po->payment_term_days }}"></div>
          <div class="col-md-12 mb-3"><label>Remarks (replaces existing)</label><textarea name="remarks" class="form-control" rows="2">{{ $po->remarks }}</textarea></div>
          <div class="col-md-12 mb-3"><label>Reason for Amendment <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
        </div>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-warning">Submit Amendment</button></footer>
    </section>
  </form>
</div></div>
@endsection