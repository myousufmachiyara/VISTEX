@extends('layouts.app')

@section('title', 'Products | Attributes')

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
          <h2 class="card-title">All Attributes</h2>
          @can('attributes.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addAttributeModal">
            <i class="fas fa-plus"></i> Add Attribute
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-attributes">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Values</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($attributes as $attribute)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $attribute->name }}</td>
                <td>
                  @foreach($attribute->values as $value)
                    <span class="badge bg-secondary">{{ $value->value }}</span>
                  @endforeach
                </td>
                <td>
                  @can('attributes.edit')
                  <a class="text-primary me-1 modal-with-form"
                     href="#editAttributeModal{{ $attribute->id }}" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('attributes.delete')
                  <form action="{{ route('attributes.destroy', $attribute->id) }}" method="POST"
                        class="d-inline" onsubmit="return confirm('Delete {{ addslashes($attribute->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                  @endcan
                </td>
              </tr>

              {{-- Edit Modal --}}
              @can('attributes.edit')
              <div id="editAttributeModal{{ $attribute->id }}" class="modal-block modal-block-warning mfp-hide">
                <section class="card">
                  <form method="POST" action="{{ route('attributes.update', $attribute->id) }}"
                        onkeydown="return event.key != 'Enter';">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                      <h2 class="card-title">Edit Attribute</h2>
                    </header>
                    <div class="card-body">
                      <div class="form-group mb-3">
                        <label class="form-label">Attribute Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name"
                               value="{{ $attribute->name }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="slug"
                               value="{{ $attribute->slug }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">
                          Attribute Values
                          <small class="text-muted">(comma-separated)</small>
                        </label>
                        <input type="text" class="form-control" name="values"
                               value="{{ $attribute->values->pluck('value')->implode(', ') }}"
                               placeholder="Red, Blue, Small, Large" required>
                        <small class="text-muted">
                          Values used by existing product variations cannot be deleted.
                        </small>
                      </div>
                    </div>
                    <footer class="card-footer">
                      <div class="text-end">
                        <button type="submit" class="btn btn-warning">Update</button>
                        <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                      </div>
                    </footer>
                  </form>
                </section>
              </div>
              @endcan
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{-- Add Attribute Modal --}}
    @can('attributes.create')
    <div id="addAttributeModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('attributes.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Attribute</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label">Attribute Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Slug <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="slug"
                     placeholder="e.g. color, size" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">
                Attribute Values
                <small class="text-muted">(comma-separated)</small>
              </label>
              <input type="text" class="form-control" name="values"
                     placeholder="Red, Blue, Small, Large" required>
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

  </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
  $('#datatable-attributes').DataTable({ pageLength: 100 });
});
</script>
@endpush

@endsection