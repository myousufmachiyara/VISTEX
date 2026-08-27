@extends('layouts.app')
@section('title', 'Service Types')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Service Types</h2>
      @can('service_types.create')
      <button type="button" class="modal-with-form btn btn-primary" href="#addModal">Add Service Type</button>
      @endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Name</th><th>Cost Account</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($serviceTypes as $st)
          <tr>
            <td>{{ $st->name }}</td>
            <td>{{ $st->costAccount->name ?? '—' }}</td>
            <td><span class="badge bg-{{ $st->is_active ? 'success' : 'secondary' }}">{{ $st->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>
              @can('service_types.edit')
              <a href="javascript:void(0)" class="text-primary me-1" onclick="editServiceType({{ $st->id }})">Edit</a>
              @endcan
              @can('service_types.delete')
              <form action="{{ route('service_types.destroy', $st->id) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete?')">Delete</button>
              </form>
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  @can('service_types.create')
  <div id="addModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" action="{{ route('service_types.store') }}">
        @csrf
        <header class="card-header"><h2 class="card-title">Add Service Type</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2">
            <label>Cost Account</label>
            <select name="service_cost_account_id" class="form-control select2-js">
              <option value="">None</option>
              @foreach($accounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
        </div>
        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
        </footer>
      </form>
    </section>
  </div>
  @endcan

  @can('service_types.edit')
  <div id="editModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" id="editServiceTypeForm" action="">
        @csrf @method('PUT')
        <header class="card-header"><h2 class="card-title">Edit Service Type</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" id="est_name" class="form-control" required></div>
          <div class="mb-2">
            <label>Cost Account</label>
            <select name="service_cost_account_id" id="est_account" class="form-control select2-js">
              <option value="">None</option>
              @foreach($accounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2"><label><input type="checkbox" name="is_active" id="est_is_active" value="1"> Active</label></div>
        </div>
        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-primary">Update</button>
          <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
        </footer>
      </form>
    </section>
  </div>
  @endcan
</div></div>

<script>
$(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });

function editServiceType(id) {
  fetch('/service-types/' + id + '/edit', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
      $('#editServiceTypeForm').attr('action', '/service-types/' + id);
      $('#est_name').val(data.name);
      $('#est_account').val(data.service_cost_account_id).trigger('change');
      $('#est_is_active').prop('checked', data.is_active);
      $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
    });
}
</script>
@endsection