@extends('layouts.app')
@section('title', 'Yarn Issue | Edit')
@section('content')
<div class="row"><div class="col">
  <form action="{{ route('yarn_issues.update', $issue->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
    @csrf @method('PUT')
    <section class="card">
      <header class="card-header"><h2 class="card-title">Edit Yarn Issue — {{ $issue->issue_no }}</h2></header>
      <div class="card-body">
        @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="alert alert-info py-2">
          PO: <strong>{{ $issue->purchaseOrder->order_no ?? '' }} — {{ $issue->purchaseOrder->vendor->name ?? '' }}</strong> (locked, cannot change)
        </div>

        <div class="row">
          <div class="col-md-3 mb-3"><label>Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ $issue->issue_date->format('Y-m-d') }}" required></div>
          <div class="col-md-4 mb-3"><label>Add More Attachments</label><input type="file" name="attachments[]" class="form-control" multiple></div>
          <div class="col-md-5 mb-3"><label>Remarks</label><textarea name="remarks" class="form-control" rows="1">{{ $issue->remarks }}</textarea></div>
        </div>

        <table class="table table-bordered">
          <thead><tr><th>Yarn</th><th>Quantity to Issue</th></tr></thead>
          <tbody>
            @foreach($issue->items as $i => $item)
            <tr>
              <td>{{ $item->product->name ?? '' }}<input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}"></td>
              <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control" step="any" min="0.001" value="{{ $item->quantity }}"></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <footer class="card-footer text-end"><button type="submit" class="btn btn-success">Update Issue</button></footer>
    </section>
  </form>
</div></div>
@endsection