@extends('layouts.app')

@section('title', 'Customers')

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
        <div style="display: flex;justify-content: space-between;">
          <h2 class="card-title">All Customers</h2>
          @can('customers.create')
          <div>
            <a href="{{ route('parties.import.form', 'customer') }}" class="btn btn-outline-primary">
              <i class="fas fa-file-import"></i> Import CSV
            </a>
            <button type="button" class="modal-with-form btn btn-primary" href="#addCustomerModal">
              <i class="fas fa-plus"></i> Add Customer
            </button>
          </div>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-customers">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>NTN</th>
                <th>Contact Person</th>
                <th>Phone</th>
                <th>City</th>
                <th>Terms</th>
                <th>Currency</th>
                <th>Credit Limit</th>
                <th>Opening Balance</th>
                <th>Current Balance</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($customers as $customer)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $customer->name }}</strong></td>
                <td>{{ $customer->ntn_number ?? '—' }}</td>
                <td>{{ $customer->contact_person ?? '—' }}</td>
                <td>{{ $customer->phone ?? '—' }}</td>
                <td>{{ $customer->city ?? '—' }}</td>
                <td class="small">
                  {{ $customer->payment_days }} {{ str_replace('_', ' ', $customer->payment_terms_type) }}
                </td>
                <td>{{ $customer->currency }}</td>
                <td>
                  @if($customer->credit_limit > 0)
                    {{ number_format($customer->credit_limit, 2) }}
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @if($customer->opening_balance > 0)
                    <span class="text-{{ $customer->opening_type === 'receivable' ? 'success' : 'danger' }}">
                      {{ number_format($customer->opening_balance, 2) }}
                      <small>({{ ucfirst($customer->opening_type) }})</small>
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @php $bal = $customer->balance; @endphp
                  @if($bal == 0)
                    <span class="text-muted">Settled</span>
                  @else
                    <span class="text-{{ $bal < 0 ? 'danger' : 'success' }}">
                      {{ number_format(abs($bal), 2) }}
                      <small>({{ $bal < 0 ? 'Payable' : 'Receivable' }})</small>
                    </span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">
                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  @can('customers.edit')
                  <a href="javascript:void(0);" class="text-primary me-1"
                     onclick="editCustomer({{ $customer->id }})" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('customers.delete')
                  <form action="{{ route('customers.destroy', $customer->id) }}" method="POST"
                        class="d-inline"
                        onsubmit="return confirm('Delete {{ addslashes($customer->name) }}? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
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
    @can('customers.create')
    <div id="addCustomerModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('customers.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">Add New Customer</h2>
          </header>
          <div class="card-body">
            <div class="row">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Customer / Brand Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       placeholder="Customer or brand name" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">NTN Number</label>
                <input type="text" class="form-control" name="ntn_number"
                       placeholder="NTN Number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person"
                       placeholder="Person to contact at this customer">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone"
                       placeholder="Phone number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email"
                       placeholder="Email address">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city"
                       placeholder="City">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Tax ID / GST Number</label>
                <input type="text" class="form-control" name="tax_id_number"
                       placeholder="Leave blank if not GST-registered">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2"
                          placeholder="Full address"></textarea>
              </div>

              {{-- ── Financial Details ─────────────────────────── --}}
              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Financial Details</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Payment Terms</label>
                <select class="form-control" name="payment_terms_type">
                  <option value="days_after_invoice">Days after invoice date</option>
                  <option value="of_current_month">Of the current month</option>
                  <option value="of_following_month">Of the following month</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Payment Days</label>
                <input type="number" class="form-control" name="payment_days" value="30" min="0" max="31">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Currency</label>
                <select class="form-control" name="currency">
                  <option value="PKR">PKR — Pakistani Rupee</option>
                  <option value="USD">USD — US Dollar</option>
                  <option value="AED">AED — UAE Dirham</option>
                  <option value="EUR">EUR — Euro</option>
                  <option value="GBP">GBP — British Pound</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Credit Limit</label>
                <input type="number" class="form-control" name="credit_limit"
                       value="0" step="any" min="0" placeholder="0.00">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              {{-- Opening Balance Section --}}
              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Opening Balance (optional)</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Opening Balance</label>
                <input type="number" class="form-control" name="opening_balance"
                       value="0" step="any" min="0" placeholder="0.00">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Type</label>
                <select class="form-control" name="opening_type">
                  <option value="receivable">Receivable (customer owes us)</option>
                  <option value="payable">Payable (we owe customer)</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Date</label>
                <input type="date" class="form-control" name="opening_balance_date"
                       value="{{ date('Y-m-d') }}">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="Any additional notes"></textarea>
              </div>

            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Add Customer</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

    {{-- ════════════════════════════════════════════════════════════
         EDIT MODAL
    ════════════════════════════════════════════════════════════ --}}
    @can('customers.edit')
    <div id="editCustomerModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editCustomerForm" action=""
              onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit Customer</h2>
          </header>
          <div class="card-body">
            <div class="row">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Customer / Brand Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       id="ec_name" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">NTN Number</label>
                <input type="text" class="form-control" name="ntn_number" id="ec_ntn_number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person"
                       id="ec_contact_person">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone" id="ec_phone">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" id="ec_email">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city" id="ec_city">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Tax ID / GST Number</label>
                <input type="text" class="form-control" name="tax_id_number" id="ec_tax_id_number"
                       placeholder="Leave blank if not GST-registered">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" id="ec_address" rows="2"></textarea>
              </div>

              {{-- ── Financial Details ─────────────────────────── --}}
              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Financial Details</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Payment Terms</label>
                <select class="form-control" name="payment_terms_type" id="ec_payment_terms_type">
                  <option value="days_after_invoice">Days after invoice date</option>
                  <option value="of_current_month">Of the current month</option>
                  <option value="of_following_month">Of the following month</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Payment Days</label>
                <input type="number" class="form-control" name="payment_days" id="ec_payment_days" min="0" max="31">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Currency</label>
                <select class="form-control" name="currency" id="ec_currency">
                  <option value="PKR">PKR — Pakistani Rupee</option>
                  <option value="USD">USD — US Dollar</option>
                  <option value="AED">AED — UAE Dirham</option>
                  <option value="EUR">EUR — Euro</option>
                  <option value="GBP">GBP — British Pound</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Credit Limit</label>
                <input type="number" class="form-control" name="credit_limit"
                       id="ec_credit_limit" step="any" min="0">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active" id="ec_is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Opening Balance</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Opening Balance</label>
                <input type="number" class="form-control" name="opening_balance"
                       id="ec_opening_balance" step="any" min="0">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Type</label>
                <select class="form-control" name="opening_type"
                        id="ec_opening_type">
                  <option value="receivable">Receivable (customer owes us)</option>
                  <option value="payable">Payable (we owe customer)</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Date</label>
                <input type="date" class="form-control" name="opening_balance_date"
                       id="ec_opening_balance_date">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" id="ec_notes" rows="2"></textarea>
              </div>

            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Update Customer</button>
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
  $('#datatable-customers').DataTable({
    pageLength: 50,
    order: [[1, 'asc']],
  });
});

function editCustomer(id) {
  fetch('/customers/' + id + '/edit', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(res) {
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
  })
  .then(function(data) {
    $('#editCustomerForm').attr('action', '/customers/' + id);

    $('#ec_name').val(data.name);
    $('#ec_ntn_number').val(data.ntn_number ?? '');
    $('#ec_contact_person').val(data.contact_person ?? '');
    $('#ec_phone').val(data.phone ?? '');
    $('#ec_email').val(data.email ?? '');
    $('#ec_city').val(data.city ?? '');
    $('#ec_address').val(data.address ?? '');
    $('#ec_tax_id_number').val(data.tax_id_number ?? '');
    $('#ec_payment_terms_type').val(data.payment_terms_type ?? 'days_after_invoice');
    $('#ec_payment_days').val(data.payment_days ?? 30);
    $('#ec_currency').val(data.currency ?? 'PKR');
    $('#ec_credit_limit').val(data.credit_limit ?? 0);
    $('#ec_notes').val(data.notes ?? '');
    $('#ec_opening_balance').val(data.opening_balance ?? 0);
    $('#ec_opening_balance_date').val(data.opening_balance_date
      ? data.opening_balance_date.substring(0, 10) : '');
    $('#ec_opening_type').val(data.opening_type ?? 'receivable');
    $('#ec_is_active').val(data.is_active ? '1' : '0');

    $.magnificPopup.open({
      items: { src: '#editCustomerModal' },
      type: 'inline'
    });
  })
  .catch(function(err) {
    console.error('[Customer] editCustomer failed:', err);
    alert('Could not load customer data. Please try again.');
  });
}
</script>

@endsection