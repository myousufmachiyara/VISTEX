@extends('layouts.app')

@section('title', 'Products | Categories')

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
          <h2 class="card-title">All Categories</h2>
          @can('product_categories.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addCategoryModal">
            <i class="fas fa-plus"></i> Add Category
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-categories">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Code</th>
                <th>In-Charge(s)</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($categories as $category)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $category->name }}</td>
                <td>{{ $category->code }}</td>
                <td>
                  @forelse($category->inchargeUsers as $user)
                    <span class="badge bg-secondary me-1">{{ $user->name }}</span>
                  @empty
                    <span class="text-muted">—</span>
                  @endforelse
                </td>
                <td>
                  @can('product_categories.edit')
                  <a class="text-primary me-1 modal-with-form" href="#editCategoryModal{{ $category->id }}" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('product_categories.delete')
                  <form action="{{ route('product_categories.destroy', $category->id) }}" method="POST"
                        class="d-inline" onsubmit="return confirm('Delete {{ addslashes($category->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                  @endcan
                </td>
              </tr>

              {{-- Edit Modal — one per row, ID scoped by category --}}
              @can('product_categories.edit')
              <div id="editCategoryModal{{ $category->id }}" class="modal-block modal-block-warning mfp-hide">
                <section class="card">
                  <form method="POST" action="{{ route('product_categories.update', $category->id) }}"
                        onkeydown="return event.key != 'Enter';">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                      <h2 class="card-title">Edit Category</h2>
                    </header>
                    <div class="card-body">
                      <div class="form-group mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name"
                               value="{{ $category->name }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code"
                               value="{{ $category->code }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label class="form-label">In-Charge(s)</label>
                        <select class="form-control select2-incharge" name="incharge_ids[]" multiple>
                          @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected($category->inchargeUsers->contains($user->id))>
                              {{ $user->name }} ({{ $user->username }})
                            </option>
                          @endforeach
                        </select>
                        <small class="text-muted">Users responsible for this category — can view/manage POs, issues, and receivings for it.</small>
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
    @can('product_categories.create')
    <div id="addCategoryModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('product_categories.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Category</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Code <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="code" required>
            </div>
            <div class="form-group mb-3">
              <label class="form-label">In-Charge(s)</label>
              <select class="form-control select2-incharge" name="incharge_ids[]" multiple>
                @foreach($users as $user)
                  <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                @endforeach
              </select>
              <small class="text-muted">Users responsible for this category — can view/manage POs, issues, and receivings for it.</small>
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

<script>
$(document).ready(function () {
  $('#datatable-categories').DataTable({ pageLength: 100 });

  // Select2 for in-charge multi-select — needs dropdownParent since these
  // selects live inside magnificPopup modals
  $('.modal-with-form').on('click', function () {
    setTimeout(function () {
      $('.modal-block:visible .select2-incharge').each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
          $(this).select2({
            width: '100%',
            dropdownParent: $(this).closest('.modal-block')
          });
        }
      });
    }, 100);
  });
});
</script>

@endsection