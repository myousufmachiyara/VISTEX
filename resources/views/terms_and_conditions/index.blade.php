@extends('layouts.app')
@section('title', 'Terms & Conditions')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Terms &amp; Conditions</h2>
      @can('terms_and_conditions.create')
      <button type="button" class="modal-with-form btn btn-primary" href="#addModal">Add Term</button>
      @endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead><tr><th>Title</th><th>Applies To</th><th>Default Checked</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          @foreach($terms as $term)
          <tr>
            <td>{{ $term->title }}</td>
            <td><span class="badge bg-info text-dark">{{ ucfirst($term->applies_to) }}</span></td>
            <td>{{ $term->is_default_checked ? 'Yes' : '—' }}</td>
            <td><span class="badge bg-{{ $term->is_active ? 'success' : 'secondary' }}">{{ $term->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>
              @can('terms_and_conditions.edit')
              <a href="javascript:void(0)" class="text-primary me-1" onclick="editTerm({{ $term->id }})">Edit</a>
              @endcan
              @can('terms_and_conditions.delete')
              <form action="{{ route('terms_and_conditions.destroy', $term->id) }}" method="POST" class="d-inline">
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

  @can('terms_and_conditions.create')
  <div id="addModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" action="{{ route('terms_and_conditions.store') }}">
        @csrf
        <header class="card-header"><h2 class="card-title">Add Term</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Title</label><input type="text" name="title" class="form-control" required></div>
          <div class="mb-2"><label>Description</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
          <div class="mb-2">
            <label>Applies To</label>
            <select name="applies_to" class="form-control">
              <option value="all">All PO Types</option>
              <option value="purchase">Purchasing Only</option>
              <option value="weaving">Weaving Only</option>
              <option value="processing">Processing Only</option>
            </select>
          </div>
          <div class="mb-2"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
          <div class="mb-2"><label><input type="checkbox" name="is_default_checked" value="1"> Pre-check by default on new POs</label></div>
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

  @can('terms_and_conditions.edit')
  <div id="editModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
      <form method="POST" id="editTermForm" action="">
        @csrf @method('PUT')
        <header class="card-header"><h2 class="card-title">Edit Term</h2></header>
        <div class="card-body">
          <div class="mb-2"><label>Title</label><input type="text" name="title" id="et_title" class="form-control" required></div>
          <div class="mb-2"><label>Description</label><textarea name="description" id="et_description" class="form-control" rows="3" required></textarea></div>
          <div class="mb-2">
            <label>Applies To</label>
            <select name="applies_to" id="et_applies_to" class="form-control">
              <option value="all">All PO Types</option>
              <option value="purchase">Purchasing Only</option>
              <option value="weaving">Weaving Only</option>
              <option value="processing">Processing Only</option>
            </select>
          </div>
          <div class="mb-2"><label>Sort Order</label><input type="number" name="sort_order" id="et_sort_order" class="form-control"></div>
          <div class="mb-2"><label><input type="checkbox" name="is_default_checked" id="et_default_checked" value="1"> Pre-check by default</label></div>
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
function editTerm(id) {
  fetch('/terms-and-conditions/' + id + '/edit', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
      $('#editTermForm').attr('action', '/terms-and-conditions/' + id);
      $('#et_title').val(data.title);
      $('#et_description').val(data.description);
      $('#et_applies_to').val(data.applies_to);
      $('#et_sort_order').val(data.sort_order);
      $('#et_default_checked').prop('checked', data.is_default_checked);
      $('#et_is_active').prop('checked', data.is_active);
      $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
    });
}
</script>
@endsection