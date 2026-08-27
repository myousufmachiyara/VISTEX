@extends('layouts.app')
@section('title', 'Forecasts / Planning')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Forecasts / Planning</h2>
      @can('forecasts.create')
      <a href="{{ route('forecasts.create') }}" class="btn btn-primary">New Forecast</a>
      @endcan
    </header>
    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="Pending" @selected(request('status')=='Pending')>Pending</option>
            <option value="Approved" @selected(request('status')=='Approved')>Approved</option>
            <option value="Rejected" @selected(request('status')=='Rejected')>Rejected</option>
          </select>
        </div>
      </form>

      <table class="table table-bordered table-striped">
        <thead><tr><th>Forecast #</th><th>Customer</th><th>Product</th><th class="text-end">Required</th><th class="text-end">On Hand</th><th class="text-end">On Order</th><th class="text-end">Shortfall</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($forecasts as $f)
          <tr>
            <td>{{ $f->forecast_no }}</td>
            <td>{{ $f->customer->name ?? 'General' }}</td>
            <td>{{ $f->product->name ?? '' }}</td>
            <td class="text-end">{{ number_format($f->required_qty, 3) }}</td>
            <td class="text-end">{{ number_format($f->stock_on_hand, 3) }}</td>
            <td class="text-end">{{ number_format($f->on_order_qty, 3) }}</td>
            <td class="text-end fw-bold">{{ number_format($f->shortfall_qty, 3) }}</td>
            <td><span class="badge bg-{{ match($f->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $f->status }}</span></td>
            <td>
              @if($f->status === 'Pending' && auth()->user()->hasRole('superadmin'))
                @can('forecasts.edit')
                <form action="{{ route('forecasts.approve', $f->id) }}" method="POST" class="d-inline">
                  @csrf<button class="btn btn-sm btn-success" onclick="return confirm('Approve this forecast?')">Approve</button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="$('#rejectForm{{ $f->id }}').toggle()">Reject</button>
                <form action="{{ route('forecasts.reject', $f->id) }}" method="POST" id="rejectForm{{ $f->id }}" style="display:none" class="d-inline mt-1">
                  @csrf
                  <input type="text" name="reason" placeholder="Reason" class="form-control form-control-sm d-inline w-auto" required>
                  <button class="btn btn-sm btn-danger">Confirm Reject</button>
                </form>
                @endcan
                @can('forecasts.delete')
                <form action="{{ route('forecasts.destroy', $f->id) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Delete?')">Delete</button>
                </form>
                @endcan
              @elseif($f->status === 'Rejected')
                <small class="text-muted">{{ $f->rejection_reason }}</small>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
@endsection