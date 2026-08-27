@extends('layouts.app')

@section('title', 'Bulk Import | Chart of Accounts')

@section('content')

<div class="row">
    <div class="col-lg-8 mx-auto">

        <section class="card">

            <header class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-file-import me-2"></i>
                    Bulk Import Chart of Accounts
                </h2>
            </header>

            <div class="card-body">

                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info">

                    <strong>Before importing:</strong>

                    <ul class="mb-0 mt-2">

                        <li>
                            Account Code must be unique.
                        </li>

                        <li>
                            Sub Head ID must already exist in BillTrix.
                        </li>

                        <li>
                            Existing account codes will not be duplicated.
                        </li>

                        <li>
                            Customer and Vendor accounts should be imported
                            through their respective modules.
                        </li>

                        <li>
                            Inventory-in-transit vendor accounts should not
                            be created. BillTrix tracks those through
                            inventory and stock movement.
                        </li>

                    </ul>

                </div>

                <div class="mb-4">

                    <a href="{{ route('coa.import.template') }}"
                       class="btn btn-outline-secondary">

                        <i class="fas fa-download me-1"></i>

                        Download Import Template

                    </a>

                </div>

                <form method="POST"
                      action="{{ route('coa.import') }}"
                      enctype="multipart/form-data">

                    @csrf

                    <div class="mb-4">

                        <label class="form-label">
                            Excel / CSV File
                            <span class="text-danger">*</span>
                        </label>

                        <input type="file"
                               name="file"
                               class="form-control"
                               accept=".xlsx,.xls,.csv"
                               required>

                        <small class="text-muted">
                            Supported formats: XLSX, XLS, CSV.
                            Maximum size: 10 MB.
                        </small>

                    </div>

                    <div class="text-end">

                        <a href="{{ route('coa.index') }}"
                           class="btn btn-default">
                            Cancel
                        </a>

                        <button type="submit"
                                class="btn btn-primary">

                            <i class="fas fa-upload me-1"></i>

                            Import Accounts

                        </button>

                    </div>

                </form>

            </div>

        </section>

    </div>
</div>

@endsection