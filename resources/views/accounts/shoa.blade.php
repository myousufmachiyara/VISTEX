@extends('layouts.app')

@section('title', 'Accounts | Sub Head Of Accounts')

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

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">All Sub Head Of Accounts</h2>
                @can('shoa.create')
                <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                    <i class="fas fa-plus"></i> Add New
                </button>
                @endcan
            </header>

            <div class="card-body">
                <div class="modal-wrapper table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-shoa">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Head</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subHeadOfAccounts as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->headOfAccount->name ?? 'N/A' }}</td>
                                <td>
                                    @can('shoa.edit')
                                    <a href="javascript:void(0);" class="text-primary me-1"
                                       onclick="editSubHead({{ $item->id }})">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    @endcan

                                    @can('shoa.delete')
                                    <form action="{{ route('shoa.destroy', $item->id) }}"
                                          method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this sub-head? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        {{-- FIX: was <a onclick="return confirm()"> — anchor never submits form.
                                             Changed to <button type="submit"> which actually submits. --}}
                                        <button type="submit" class="btn btn-link p-0 m-0 text-danger">
                                            <i class="fa fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════ --}}
        {{-- ADD MODAL                                            --}}
        {{-- ════════════════════════════════════════════════════ --}}
        @can('shoa.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('shoa.store') }}"
                      onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header">
                        <h2 class="card-title">New Sub Head Of Account</h2>
                    </header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Head Of Account <span class="text-danger">*</span></label>
                            <select class="form-control select2-js" name="hoa_id" required>
                                <option value="" selected disabled>Select Head</option>
                                @foreach($HeadOfAccounts as $row)
                                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Account Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" placeholder="Name"
                                   name="name" required>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Add</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ════════════════════════════════════════════════════ --}}
        {{-- EDIT MODAL                                           --}}
        {{-- ════════════════════════════════════════════════════ --}}
        @can('shoa.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action=""
                      onkeydown="return event.key != 'Enter';">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                        <h2 class="card-title">Update Sub Head Of Account</h2>
                    </header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Head Of Account <span class="text-danger">*</span></label>
                            <select class="form-control select2-js" name="hoa_id"
                                    id="edit_hoa_id" required>
                                <option value="" disabled>Select Head</option>
                                @foreach($HeadOfAccounts as $row)
                                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Account Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                   id="edit_name" placeholder="Name" required>
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
    $('#datatable-shoa').DataTable({ pageLength: 50 });
    $('#addModal .select2-js').select2({ width: '100%', dropdownParent: $('#addModal') });
    $('#updateModal .select2-js').select2({ width: '100%', dropdownParent: $('#updateModal') });
});

function editSubHead(id) {
    fetch('/shoa/' + id + '/edit', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) {
        if (!res.ok) throw new Error('Server error: ' + res.status);
        return res.json();
    })
    .then(function (data) {
        $('#updateForm').attr('action', '/shoa/' + id);
        $('#edit_name').val(data.name);
        $('#edit_hoa_id').val(data.hoa_id).trigger('change');

        $.magnificPopup.open({
            items: { src: '#updateModal' },
            type: 'inline'
        });
    })
    .catch(function (err) {
        console.error('[SHOA] editSubHead failed:', err);
        alert('Could not load data. Please try again.');
    });
}
</script>
@endpush

@endsection