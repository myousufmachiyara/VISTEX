@extends('layouts.app')
@section('title', 'Purchase Receivings | All')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Purchase Receivings (GRN)</h2>
      @can('purchase_receivings.create')
      <a href="{{ route('purchase_receivings.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Receiving</a>
      @endcan
    </header>

    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="PendingApproval" @selected(request('status')=='PendingApproval')>Pending Approval</option>
            <option value="Approved" @selected(request('status')=='Approved')>Approved</option>
            <option value="Rejected" @selected(request('status')=='Rejected')>Rejected</option>
          </select>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped" id="grnTable">
          <thead>
            <tr>
              <th width="4%">#</th>
              <th>Date</th>
              <th>GRN #</th>
              <th>PO #</th>
              <th>Category</th>
              <th>Vendor</th>
              <th>Challan #</th>
              <th class="text-end">Amount</th>
              <th>Status</th>
              <th width="6%">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($receivings as $index => $grn)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td>{{ $grn->receiving_date->format('d-M-Y') }}</td>
              <td>
                <a href="{{ route('purchase_receivings.show', $grn->id) }}" class="text-primary">{{ $grn->receiving_no }}</a>
              </td>
              <td>{{ $grn->purchaseOrder->order_no ?? 'N/A' }}</td>
              <td>{{ $grn->purchaseOrder->category->name ?? 'N/A' }}</td>
              <td>{{ $grn->purchaseOrder->vendor->name ?? 'N/A' }}</td>
              <td>{{ $grn->vendor_challan_no ?? '—' }}</td>
              <td class="text-end">{{ number_format($grn->amount, 2) }}</td>
              <td>
                <span class="badge bg-{{ match($grn->status){'Approved'=>'success','Rejected'=>'danger',default=>'warning text-dark'} }}">
                  {{ $grn->status === 'PendingApproval' ? 'Pending' : $grn->status }}
                </span>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('purchase_receivings.show', $grn->id) }}">View</a></li>
                    <li><a class="dropdown-item" href="{{ route('purchase_receivings.print', $grn->id) }}" target="_blank">Print</a></li>

                    @if($grn->status === 'PendingApproval' && $grn->canBeApprovedBy(auth()->user()))
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="{{ route('purchase_receivings.approve', $grn->id) }}" method="POST">
                          @csrf
                          <button type="submit" class="dropdown-item text-success" onclick="return confirm('Approve? This posts stock and accounting.')">Approve</button>
                        </form>
                      </li>
                      <li>
                        <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $grn->id }}">Reject</button>
                      </li>
                    @endif

                    @if(auth()->user()->hasRole('superadmin') && $grn->status !== 'Approved')
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="{{ route('purchase_receivings.destroy', $grn->id) }}" method="POST" onsubmit="return confirm('Delete this receiving?')">
                          @csrf @method('DELETE')
                          <button type="submit" class="dropdown-item text-danger">Delete</button>
                        </form>
                      </li>
                    @endif
                  </ul>
                </div>

                @if($grn->status === 'PendingApproval' && $grn->canBeApprovedBy(auth()->user()))
                <div class="modal fade" id="rejectModal{{ $grn->id }}" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form action="{{ route('purchase_receivings.reject', $grn->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                          <h5 class="modal-title">Reject {{ $grn->receiving_no }}</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <label class="form-label">Reason <span class="text-danger">*</span></label>
                          <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-danger">Confirm Reject</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div></div>

<script>
    $(document).ready(function() {
        $('#grnTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection