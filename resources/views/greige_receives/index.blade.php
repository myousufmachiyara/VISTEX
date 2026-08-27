@extends('layouts.app')
@section('title', 'Greige Receives')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Greige Receives</h2>
      @can('greige_receives.create')<a href="{{ route('greige_receives.create') }}" class="btn btn-primary">New Receive</a>@endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="grTable">
        <thead><tr><th>Date</th><th>Receive #</th><th>CPO #</th><th>Vendor</th><th>Challan #</th><th class="text-end">Yarn Cost</th><th class="text-end">Weaving Charge</th><th class="text-end">Total</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          @foreach ($receives as $r)
          <tr>
            <td>{{ $r->receive_date->format('d-M-Y') }}</td>
            <td class="text-primary">{{ $r->receive_no }}</td>
            <td>{{ $r->cpo->cpo_no ?? '' }}</td>
            <td>{{ $r->cpo->vendor->name ?? '' }}</td>
            <td>{{ $r->vendor_challan_no }}</td>
            <td class="text-end">{{ number_format($r->yarn_cost_amount, 2) }}</td>
            <td class="text-end">{{ number_format($r->weaving_charge_amount, 2) }}</td>
            <td class="text-end fw-bold">{{ number_format($r->total_amount, 2) }}</td>
            <td><span class="badge bg-{{ match($r->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">{{ $r->status === 'PendingApproval' ? 'Pending Approval' : $r->status }}</span></td>
            <td>
              @if($r->status === 'PendingApproval' && $r->canBeApprovedBy(auth()->user()))
                <form action="{{ route('greige_receives.approve', $r->id) }}" method="POST" class="d-inline">
                  @csrf<button class="btn btn-sm btn-success me-1" onclick="return confirm('Approve? This posts stock and accounting.')">Approve</button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="$('#rejectForm{{ $r->id }}').toggle()">Reject</button>
                <form action="{{ route('greige_receives.reject', $r->id) }}" method="POST" id="rejectForm{{ $r->id }}" style="display:none" class="d-inline mt-1">
                  @csrf
                  <input type="text" name="reason" placeholder="Reason" class="form-control form-control-sm d-inline w-auto" required>
                  <button class="btn btn-sm btn-danger">Confirm Reject</button>
                </form>
              @endif
              @if(auth()->user()->hasRole('superadmin') && $r->status !== 'Approved')
                <form action="{{ route('greige_receives.destroy', $r->id) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
                </form>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#grTable').DataTable({pageLength:50,order:[[0,'desc']]}));</script>
@endsectionw