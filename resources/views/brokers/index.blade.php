@extends('layouts.app')
@section('title', 'Brokers')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Brokers</h2>
      @can('brokers.create')
      <button type="button" class="modal-with-form btn btn-primary" href="#addModal">Add Broker</button>
      @endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="brokerTable">
        <thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($brokers as $broker)
          <tr>
            <td>{{ $broker->name }}</td>
            <td>{{ $broker->phone ?? '—' }}</td>
            <td><span class="badge bg-{{ $broker->is_active ? 'success' : 'secondary' }}">{{ $broker->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>
              @can('brokers.edit')
              <a href="javascript:void(0)" class="text-primary me-1" onclick="editBroker({{ $broker->id }})">Edit</a>
              @endcan
              @can('brokers.delete')
              <form action="{{ route('brokers.destroy', $broker->id) }}" method="POST" class="d-inline">
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

  @can('brokers.create')
  <div id="addModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" action="{{ route('brokers.store') }}">
        @csrf
        <header class="card-header"><h2 class="card-title">Add Broker</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
          <div class="mb-2"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
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

  @can('brokers.edit')
  <div id="editModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" id="editBrokerForm" action="">
        @csrf @method('PUT')
        <header class="card-header"><h2 class="card-title">Edit Broker</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" id="eb_name" class="form-control" required></div>
          <div class="mb-2"><label>Phone</label><input type="text" name="phone" id="eb_phone" class="form-control"></div>
          <div class="mb-2"><label>Notes</label><textarea name="notes" id="eb_notes" class="form-control" rows="2"></textarea></div>
          <div class="mb-2"><label><input type="checkbox" name="is_active" id="eb_is_active" value="1"> Active</label></div>
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
function editBroker(id) {
  fetch('/brokers/' + id + '/edit', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
      $('#editBrokerForm').attr('action', '/brokers/' + id);
      $('#eb_name').val(data.name);
      $('#eb_phone').val(data.phone);
      $('#eb_notes').val(data.notes);
      $('#eb_is_active').prop('checked', data.is_active);
      $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
    });
}
</script>
@endsection