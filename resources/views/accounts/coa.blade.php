@extends('layouts.app')

@section('title', 'Accounts | All COA')

@section('content')

    {{-- ── Account type map — single source of truth for this page ──────
         Must stay in sync with COAController::ACCOUNT_TYPES
    ──────────────────────────────────────────────────────────────────── --}}
    @php
        $accountTypes = [
            // Party accounts
            'customer'     => 'Customer',
            'vendor'       => 'Vendor',

            // Asset accounts
            'cash'         => 'Cash',
            'bank'         => 'Bank',
            'inventory'    => 'Inventory / Stock',
            'receivable'   => 'Receivable (Loan Given)',

            // Liability accounts
            'liability'    => 'Liability',
            'payable'      => 'Payable (Loan Taken)',

            // Equity
            'equity'       => 'Equity',

            // Revenue
            'revenue'      => 'Revenue',

            // Expense accounts
            'cogs'         => 'Cost of Goods Sold',
            'expenses'     => 'Expenses',

            // FIX: these four were missing — accounts 22-32 in seeder use them
            'service_cost' => 'Service Cost',
            'freight'      => 'Freight / Logistics',
            'sampling'     => 'Sampling Expense',
            'packaging'    => 'Packaging Expense',
        ];
    @endphp

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
                        <h2 class="card-title">All Products</h2>
                        @can('coa.create')
                            <div>

                                <a href="{{ route('account-mappings.index') }}"
                                class="btn btn-outline-primary">
                                    <i class="fas fa-link me-1"></i>
                                    Account Mappings
                                </a>

                                @can('coa.create')
                                <a href="{{ route('coa.import.form') }}" class="btn btn-outline-primary">Import</a>
                                @endcan

                                <button type="button"
                                        class="modal-with-form btn btn-primary"
                                        href="#addModal">
                                    <i class="fas fa-plus"></i>
                                    Add Account
                                </button>

                            </div>
                        @endcan
                    </div>
                     @if ($errors->has('error'))
                        <strong class="text-danger">{{ $errors->first('error') }}</strong>
                    @endif
                   
                </header>

                <div class="card-body">

                    {{-- ── Sub-head filter ──────────────────────────── --}}
                    <form method="GET" action="{{ route('coa.index') }}" class="mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Filter by Sub-head</label>
                            <select name="subhead" data-plugin-selecttwo class="form-control select2-js" onchange="this.form.submit()">
                                <option value="all" {{ request('subhead', 'all') === 'all' ? 'selected' : '' }}>
                                    All
                                </option>
                                @foreach($subHeads as $sub)
                                    <option value="{{ $sub->id }}"
                                        {{ request('subhead') == $sub->id ? 'selected' : '' }}>
                                        {{ $sub->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    {{-- ── Table ───────────────────────────────────── --}}
                    <div class="modal-wrapper table-scroll">
                        <table class="table table-bordered table-striped mb-0" id="datatable-coa">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Sub-head</th>
                                    <th>Type</th>
                                    <th>Phone</th>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><code>{{ $item->account_code }}</code></td>
                                    <td><strong>{{ $item->name }}</strong></td>
                                    <td>{{ $item->subHeadOfAccount->name ?? '—' }}</td>
                                    <td>
                                        <strong>
                                            {{ $accountTypes[$item->account_type] ?? ucfirst($item->account_type ?? '—') }}
                                        </strong>
                                    </td>
                                    <td>{{ $item->contact_no ?? '—' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($item->opening_date)->format('d-m-Y') }}</td>
                                    <td>{{ $item->remarks ?? '—' }}</td>
                                    <td>
                                        @can('coa.edit')
                                            <a href="#" class="text-primary me-1"
                                               onclick="editAccount({{ $item->id }}); return false;">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endcan
                                        
                                        @can('coa.delete')
                                            <form action="{{ route('coa.destroy', $item->id) }}"
                                                  method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Delete this account? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 text-danger">
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
            @can('coa.create')
            <div id="addModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="POST" id="addForm" action="{{ route('coa.store') }}"
                          onkeydown="return event.key != 'Enter';">
                        @csrf
                        <header class="card-header">
                            <h2 class="card-title">Add New Account</h2>
                        </header>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Account Name"
                                           name="name" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Account Type</label>
                                    <select class="form-control select2-js" name="account_type">
                                        <option value="" disabled selected>Select Account Type</option>
                                        @foreach($accountTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Sub-head of Account <span class="text-danger">*</span></label>
                                    <select class="form-control select2-js" name="shoa_id" required>
                                        <option value="" disabled selected>Select Sub-head</option>
                                        @foreach($subHeads as $row)
                                            <option value="{{ $row->id }}">{{ $row->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Opening Balance</label>
                                    <input type="number" class="form-control" name="opening_balance"
                                           value="0" step="any">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Receivables <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="receivables"
                                           value="0" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Payables <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="payables"
                                           value="0" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Credit Limit <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="credit_limit"
                                           value="0" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Opening Date</label>
                                    <input type="date" class="form-control" name="opening_date"
                                           value="{{ date('Y-m-d') }}" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Phone No.</label>
                                    <input type="text" class="form-control" placeholder="Phone No."
                                           name="contact_no">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" class="form-control" placeholder="Remarks"
                                           name="remarks">
                                </div>

                                <div class="col-lg-12 mb-2">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" rows="2" placeholder="Address"
                                              name="address"></textarea>
                                </div>

                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">Add Account</button>
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
            @can('coa.edit')
            <div id="editModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="POST" id="editForm" action=""
                          onkeydown="return event.key != 'Enter';">
                        @csrf
                        @method('PUT')
                        <header class="card-header">
                            <h2 class="card-title">Edit Account</h2>
                        </header>
                        <div class="card-body">
                            <div class="row">

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                    <input type="text" id="edit_name" class="form-control"
                                           placeholder="Account Name" name="name" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Account Type</label>
                                    <select id="edit_account_type" class="form-control select2-js"
                                            name="account_type">
                                        <option value="" disabled>Select Account Type</option>
                                        @foreach($accountTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Sub-head of Account <span class="text-danger">*</span></label>
                                    <select id="edit_shoa_id" class="form-control select2-js"
                                            name="shoa_id" required>
                                        <option value="" disabled>Select Sub-head</option>
                                        @foreach($subHeads as $row)
                                            <option value="{{ $row->id }}">{{ $row->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Opening Balance</label>
                                    <input type="number" id="edit_opening_balance" class="form-control"
                                           name="opening_balance" step="any">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Receivables <span class="text-danger">*</span></label>
                                    <input type="number" id="edit_receivables" class="form-control"
                                           name="receivables" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Payables <span class="text-danger">*</span></label>
                                    <input type="number" id="edit_payables" class="form-control"
                                           name="payables" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Credit Limit <span class="text-danger">*</span></label>
                                    <input type="number" id="edit_credit_limit" class="form-control"
                                           name="credit_limit" step="any" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Opening Date</label>
                                    <input type="date" id="edit_opening_date" class="form-control"
                                           name="opening_date" required>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Phone No.</label>
                                    <input type="text" id="edit_contact_no" class="form-control"
                                           placeholder="Phone No." name="contact_no">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" id="edit_remarks" class="form-control"
                                           placeholder="Remarks" name="remarks">
                                </div>

                                <div class="col-lg-12 mb-2">
                                    <label class="form-label">Address</label>
                                    <textarea id="edit_address" class="form-control" rows="2"
                                              placeholder="Address" name="address"></textarea>
                                </div>

                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">Update Account</button>
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
    // DataTable — named ID, manual init
    $('#datatable-coa').DataTable({
        pageLength: 50,
        order: [[1, 'asc']], // sort by account code
    });

    // Select2 inside add modal — init once on page load
    $('#addModal .select2-js').select2({ width: '100%', dropdownParent: $('#addModal') });
    $('#editModal .select2-js').select2({ width: '100%', dropdownParent: $('#editModal') });
});

function editAccount(id) {
    fetch('/coa/' + id + '/edit', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) {
        if (!res.ok) throw new Error('Server error: ' + res.status);
        return res.json();
    })
    .then(function (data) {
        // Set form action to PUT endpoint
        $('#editForm').attr('action', '/coa/' + id);

        // Populate plain fields
        $('#edit_name').val(data.name);
        $('#edit_opening_balance').val(data.opening_balance ?? 0);
        $('#edit_receivables').val(data.receivables);
        $('#edit_payables').val(data.payables);
        $('#edit_credit_limit').val(data.credit_limit);
        $('#edit_opening_date').val(data.opening_date
            ? data.opening_date.substring(0, 10)   // ensure YYYY-MM-DD
            : '');
        $('#edit_remarks').val(data.remarks ?? '');
        $('#edit_address').val(data.address ?? '');
        $('#edit_contact_no').val(data.contact_no ?? '');

        // Select2 fields — must trigger('change') to update the visible selection
        $('#edit_account_type').val(data.account_type).trigger('change');
        $('#edit_shoa_id').val(data.shoa_id).trigger('change');

        // Open modal
        $.magnificPopup.open({
            items: { src: '#editModal' },
            type: 'inline'
        });
    })
    .catch(function (err) {
        console.error('[COA] editAccount failed:', err);
        alert('Could not load account data. Please try again.');
    });
}
</script>
@endpush

@endsection