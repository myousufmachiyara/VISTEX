@extends('layouts.app')

@section('title', 'Job Types')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Job Types</h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add Job Type
        </button>
      </header>

      <div class="card-body">
        <table class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Name</th>
              <th>Service Cost Account</th>
              <th>Status</th>
              <th width="10%">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($jobTypes as $jt)
            <tr>
              <td>{{ $jt->name }}</td>
              <td>{{ $jt->serviceCostAccount->name ?? '— Not mapped —' }}</td>
              <td><span class="badge bg-{{ $jt->is_active ? 'success' : 'secondary' }}">{{ $jt->is_active ? 'Active' : 'Inactive' }}</span></td>
              <td>
                <a href="javascript:void(0)" class="text-primary" onclick="editJobType({{ $jt->id }}, '{{ $jt->name }}', {{ $jt->service_cost_account_id ?? 'null' }}, {{ $jt->is_active ? 1 : 0 }})">
                  <i class="fa fa-edit"></i>
                </a>
                <form action="{{ route('job-types.destroy', $jt->id) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')
                  <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete?')"><i class="fa fa-trash-alt"></i></button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('job-types.store') }}">
          @csrf
          <header class="card-header"><h2 class="card-title">Add Job Type</h2></header>
          <div class="card-body">
            <div class="mb-2">
              <label>Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-2">
              <label>Service Cost Account</label>
              <select name="service_cost_account_id" class="form-control select2-js">
                <option value="">— Not mapped —</option>
                @foreach(\App\Models\ChartOfAccounts::orderBy('account_code')->get() as $acc)
                  <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <div id="editModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editForm">
          @csrf @method('PUT')
          <header class="card-header"><h2 class="card-title">Edit Job Type</h2></header>
          <div class="card-body">
            <div class="mb-2">
              <label>Name</label>
              <input type="text" name="name" id="e_name" class="form-control" required>
            </div>
            <div class="mb-2">
              <label>Service Cost Account</label>
              <select name="service_cost_account_id" id="e_account" class="form-control select2-js">
                <option value="">— Not mapped —</option>
                @foreach(\App\Models\ChartOfAccounts::orderBy('account_code')->get() as $acc)
                  <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-2">
              <label>Status</label>
              <select name="is_active" id="e_active" class="form-control">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
  </div>
</div>

<script>
$(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

function editJobType(id, name, accountId, isActive) {
    $('#editForm').attr('action', '/job-types/' + id);
    $('#e_name').val(name);
    $('#e_account').val(accountId).trigger('change');
    $('#e_active').val(isActive ? '1' : '0');
    $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
}
</script>
@endsection