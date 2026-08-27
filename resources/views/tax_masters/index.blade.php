@extends('layouts.app')
@section('title', 'Tax Master')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Tax Master</h2>
      @can('tax_masters.create')
      <button type="button" class="modal-with-form btn btn-primary" href="#addModal">Add Tax</button>
      @endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Name</th><th>Rate</th><th>Default</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($taxes as $tax)
          <tr>
            <td>{{ $tax->name }}</td>
            <td>{{ number_format($tax->rate, 2) }}%</td>
            <td>{{ $tax->is_default ? 'Yes' : '—' }}</td>
            <td><span class="badge bg-{{ $tax->is_active ? 'success' : 'secondary' }}">{{ $tax->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>
              @can('tax_masters.edit')
              <a href="javascript:void(0)" class="text-primary me-1" onclick="editTax({{ $tax->id }})">Edit</a>
              @endcan
              @can('tax_masters.delete')
              <form action="{{ route('tax-masters.destroy', $tax->id) }}" method="POST" class="d-inline">
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

  @can('tax_masters.create')
  <div id="addModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" action="{{ route('tax-masters.store') }}">
        @csrf
        <header class="card-header"><h2 class="card-title">Add Tax</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label>Rate (%)</label><input type="number" name="rate" class="form-control" step="0.01" min="0" max="100" required></div>
          <div class="mb-2"><label><input type="checkbox" name="is_default" value="1"> Set as default</label></div>
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

  @can('tax_masters.edit')
  <div id="editModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" id="editTaxForm" action="">
        @csrf @method('PUT')
        <header class="card-header"><h2 class="card-title">Edit Tax</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Name</label><input type="text" name="name" id="et_name" class="form-control" required></div>
          <div class="mb-2"><label>Rate (%)</label><input type="number" name="rate" id="et_rate" class="form-control" step="0.01" min="0" max="100" required></div>
          <div class="mb-2"><label><input type="checkbox" name="is_default" id="et_is_default" value="1"> Set as default</label></div>
          <div class="mb-2"><label><input type="checkbox" name="is_active" id="et_is_active" value="1"> Active</label></div>
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
function editTax(id) {
  fetch('/tax-masters/' + id + '/edit', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
      $('#editTaxForm').attr('action', '/tax-masters/' + id);
      $('#et_name').val(data.name);
      $('#et_rate').val(data.rate);
      $('#et_is_default').prop('checked', data.is_default);
      $('#et_is_active').prop('checked', data.is_active);
      $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
    });
}
</script>
@endsection