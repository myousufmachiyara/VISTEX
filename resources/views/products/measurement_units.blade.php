@extends('layouts.app')

@section('title', 'Products | Measurement Units')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif

      <header class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="card-title">Measurement Units</h2>
          @can('measurement_units.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addUnitModal">
            <i class="fas fa-plus"></i> Add Unit
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-units">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Short Code</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($units as $unit)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $unit->name }}</td>
                <td><code>{{ $unit->shortcode }}</code></td>
                <td>
                  @can('measurement_units.edit')
                  <a href="javascript:void(0);" class="text-primary me-1"
                     onclick="editUnit({{ $unit->id }})" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('measurement_units.delete')
                  @if($unit->id > 9)
                  <form action="{{ route('measurement_units.destroy', $unit->id) }}"
                        method="POST" class="d-inline"
                        onsubmit="return confirm('Delete {{ addslashes($unit->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
                      <i class="fa fa-trash-alt"></i>
                    </button>
                  </form>
                  @else
                    <span class="text-muted" title="System unit — cannot be deleted">
                      <i class="fa fa-lock"></i>
                    </span>
                  @endif
                  @endcan
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{-- ── ADD MODAL ─────────────────────────────────────────────── --}}
    @can('measurement_units.create')
    <div id="addUnitModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('measurement_units.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Measurement Unit</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label">Unit Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name"
                     placeholder="e.g. Pounds, Meters, Pieces" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Short Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="shortcode"
                     placeholder="e.g. lbs, m, pcs" required>
              <small class="text-muted">Used in dropdowns and print views. Lowercase recommended.</small>
            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Create</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

    {{-- ── EDIT MODAL ────────────────────────────────────────────── --}}
    @can('measurement_units.edit')
    <div id="editUnitModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editUnitForm" action=""
              onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit Measurement Unit</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label">Unit Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name"
                     id="eu_name" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Short Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="shortcode"
                     id="eu_shortcode" required>
            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Update</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

  </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
  $('#datatable-units').DataTable({ pageLength: 50, order: [[1, 'asc']] });
});

function editUnit(id) {
  fetch('/measurement_units/' + id + '/edit', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(res) {
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
  })
  .then(function(data) {
    $('#editUnitForm').attr('action', '/measurement_units/' + id);
    $('#eu_name').val(data.name);
    $('#eu_shortcode').val(data.shortcode);
    $.magnificPopup.open({ items: { src: '#editUnitModal' }, type: 'inline' });
  })
  .catch(function(err) {
    console.error('[MeasurementUnit] editUnit failed:', err);
    alert('Could not load unit data. Please try again.');
  });
}
</script>
@endpush

@endsection