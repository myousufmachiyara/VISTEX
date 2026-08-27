@extends('layouts.app')

@section('title', 'Products | Subcategories')

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
          <h2 class="card-title">All Subcategories</h2>
          @can('product_subcategories.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addSubcategoryModal">
            <i class="fas fa-plus"></i> Add Subcategory
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-subcategories">
            <thead>
              <tr>
                <th>#</th>
                <th>Category</th>
                <th>Name</th>
                <th>Code</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($subcategories as $subcategory)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $subcategory->category->name ?? '—' }}</td>
                <td>{{ $subcategory->name }}</td>
                <td>{{ $subcategory->code }}</td>
                <td>
                  @can('product_subcategories.edit')
                  <a class="text-primary me-1 modal-with-form"
                     href="#editSubcategoryModal{{ $subcategory->id }}" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('product_subcategories.delete')
                  <form action="{{ route('product_subcategories.destroy', $subcategory->id) }}" method="POST"
                        class="d-inline" onsubmit="return confirm('Delete {{ addslashes($subcategory->name) }}?')">
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
              @can('product_subcategories.edit')
              <div id="editSubcategoryModal{{ $subcategory->id }}" class="modal-block modal-block-warning mfp-hide">
                <section class="card">
                  <form method="POST" action="{{ route('product_subcategories.update', $subcategory->id) }}"
                        onkeydown="return event.key != 'Enter';">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                      <h2 class="card-title">Edit Subcategory</h2>
                    </header>
                    <div class="card-body">
                      <div class="form-group mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-control" required>
                          @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                              {{ $subcategory->category_id == $cat->id ? 'selected' : '' }}>
                              {{ $cat->name }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name"
                               value="{{ $subcategory->name }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code"
                               value="{{ $subcategory->code }}" required>
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

    {{-- Add Modal --}}
    @can('product_subcategories.create')
    <div id="addSubcategoryModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('product_subcategories.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Subcategory</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-control" required>
                <option value="">Select Category</option>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="code" required>
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
  $('#datatable-subcategories').DataTable({ pageLength: 100 });
});
</script>
@endpush

@endsection