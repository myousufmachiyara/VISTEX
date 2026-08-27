@extends('layouts.app')

@section('title', 'Locations')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Locations</h2>
        @can('locations.create')
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> New Location
        </button>
        @endcan
      </header>

      <div class="card-body">
        <div class="alert alert-info py-2">
          <i class="fas fa-info-circle me-1"></i>
          Locations without a vendor are <strong>our own warehouses</strong>. Locations with a vendor
          are <strong>vendor sites</strong> where our stock is held during job work.
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="locationTable">
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Vendor</th>
                <th>In-Charge</th>
                <th>Contact</th>
                <th>Status</th>
                <th width="10%">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($locations as $loc)
              <tr>
                <td>
                  {{ $loc->name }}
                  @if($loc->is_default)
                    <span class="badge bg-primary ms-1">Default</span>
                  @endif
                </td>
                <td>
                  @if($loc->vendor_id)
                    <span class="badge bg-warning text-dark">Vendor Site</span>
                  @else
                    <span class="badge bg-success">Our Warehouse</span>
                  @endif
                </td>
                <td>{{ $loc->vendor->name ?? '—' }}</td>
                <td>{{ $loc->inCharge->name ?? '—' }}</td>
                <td>
                  {{ $loc->contact_person ?? '—' }}
                  @if($loc->phone) <br><small class="text-muted">{{ $loc->phone }}</small> @endif
                </td>
                <td>
                  <span class="badge bg-{{ $loc->is_active ? 'success' : 'secondary' }}">
                    {{ $loc->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  @can('locations.edit')
                  <a href="javascript:void(0);" class="text-primary mr-2" title="Edit"
                     onclick="editLocation({{ $loc->id }})">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('locations.delete')
                  <form action="{{ route('locations.destroy', $loc->id) }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-link p-0 text-danger" onclick="return confirm('Delete this location?')" title="Delete">
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

    {{-- ════════════════════════════════════════════════════════════
         ADD MODAL
    ════════════════════════════════════════════════════════════ --}}
    @can('locations.create')
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('locations.store') }}" onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Location</h2>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Location Type <span class="text-danger">*</span></label>
                <select name="location_type" id="add_location_type" class="form-control" required>
                  <option value="own">Our Own Warehouse</option>
                  <option value="vendor">Vendor Site</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2" id="add_vendor_field" style="display:none">
                <label>Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" class="form-control select2-js">
                  <option value="">Select Vendor</option>
                  @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Location Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Main Warehouse, Unit 1" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control">
              </div>

              <div class="col-lg-6 mb-2">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control">
              </div>

              <div class="col-lg-6 mb-2">
                <label>In-Charge (VISTEX Employee)</label>
                <select name="in_charge_user_id" class="form-control select2-js">
                  <option value="">— Not assigned —</option>
                  @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-12 mb-2">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"></textarea>
              </div>

              <div class="col-lg-6 mb-2" id="add_default_field">
                <label>Default Warehouse?</label>
                <select name="is_default" class="form-control">
                  <option value="0">No</option>
                  <option value="1">Yes — set as default</option>
                </select>
                <small class="text-muted">Only one location can be the default.</small>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Status</label>
                <select name="is_active" class="form-control">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Add Location</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
    @endcan

    {{-- ════════════════════════════════════════════════════════════
         EDIT MODAL
    ════════════════════════════════════════════════════════════ --}}
    @can('locations.edit')
    <div id="editModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editLocationForm" action="" onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit Location</h2>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Location Type <span class="text-danger">*</span></label>
                <select name="location_type" id="edit_location_type" class="form-control" required>
                  <option value="own">Our Own Warehouse</option>
                  <option value="vendor">Vendor Site</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2" id="edit_vendor_field" style="display:none">
                <label>Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" id="edit_vendor_id" class="form-control select2-js">
                  <option value="">Select Vendor</option>
                  @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Location Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Contact Person</label>
                <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
              </div>

              <div class="col-lg-6 mb-2">
                <label>Phone</label>
                <input type="text" name="phone" id="edit_phone" class="form-control">
              </div>

              <div class="col-lg-6 mb-2">
                <label>In-Charge (VISTEX Employee)</label>
                <select name="in_charge_user_id" id="edit_in_charge_user_id" class="form-control select2-js">
                  <option value="">— Not assigned —</option>
                  @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-12 mb-2">
                <label>Address</label>
                <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
              </div>

              <div class="col-lg-6 mb-2" id="edit_default_field">
                <label>Default Warehouse?</label>
                <select name="is_default" id="edit_is_default" class="form-control">
                  <option value="0">No</option>
                  <option value="1">Yes — set as default</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Status</label>
                <select name="is_active" id="edit_is_active" class="form-control">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update Location</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
    @endcan

  </div>
</div>

<script>
    $(document).ready(function () {
        $('#locationTable').DataTable({ pageLength: 50 });

        $('#addModal .select2-js').select2({ width: '100%', dropdownParent: $('#addModal') });
        $('#editModal .select2-js').select2({ width: '100%', dropdownParent: $('#editModal') });
    });

    function toggleLocationType(prefix) {
        const type = $('#' + prefix + '_location_type').val();
        if (type === 'vendor') {
            $('#' + prefix + '_vendor_field').show();
            $('#' + prefix + '_vendor_field select').attr('required', true);
            $('#' + prefix + '_default_field').hide();
        } else {
            $('#' + prefix + '_vendor_field').hide();
            $('#' + prefix + '_vendor_field select').attr('required', false).val('').trigger('change');
            $('#' + prefix + '_default_field').show();
        }
    }

    $('#add_location_type').on('change', function () { toggleLocationType('add'); });
    $('#edit_location_type').on('change', function () { toggleLocationType('edit'); });

    // Initialize add modal state
    toggleLocationType('add');

    function editLocation(id) {
        fetch('/locations/' + id + '/edit', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            $('#editLocationForm').attr('action', '/locations/' + id);

            $('#edit_name').val(data.name);
            $('#edit_contact_person').val(data.contact_person ?? '');
            $('#edit_phone').val(data.phone ?? '');
            $('#edit_address').val(data.address ?? '');
            $('#edit_is_active').val(data.is_active ? '1' : '0').trigger('change');
            $('#edit_is_default').val(data.is_default ? '1' : '0').trigger('change');
            $('#edit_in_charge_user_id').val(data.in_charge_user_id).trigger('change');

            const type = data.vendor_id ? 'vendor' : 'own';
            $('#edit_location_type').val(type).trigger('change');
            toggleLocationType('edit');

            if (data.vendor_id) {
            $('#edit_vendor_id').val(data.vendor_id).trigger('change');
            }

            $.magnificPopup.open({
            items: { src: '#editModal' },
            type: 'inline'
            });
        })
        .catch(() => alert('Could not load location data. Please try again.'));
    }
</script>
@endsection