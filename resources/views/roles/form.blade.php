@extends('layouts.app')

@section('title', $role ? 'Roles | Edit — ' . $role->name : 'Roles | New Role')

@section('content')

@php
    $actions = [
        'index'  => 'View',
        'create' => 'Create',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'print'  => 'Print',
    ];

    $modulePermissions = [];
    $reportPermissions = collect();

    foreach ($permissions as $permission) {
        if (str_starts_with($permission->name, 'reports.')) {
            $reportPermissions->push($permission);
        } else {
            $parts = explode('.', $permission->name);
            if (count($parts) === 2) {
                [$module, $action] = $parts;
                $modulePermissions[$module][$action] = $permission->name;
            }
        }
    }
    ksort($modulePermissions);
@endphp

<div class="row">
    <div class="col">
        <form action="{{ $role ? route('roles.update', $role) : route('roles.store') }}"
              method="POST" onkeydown="return event.key != 'Enter';">
            @csrf
            @if($role)
                @method('PUT')
            @endif

            {{-- ── Role Name Card ──────────────────────────────── --}}
            <section class="card">
                <header class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">{{ $role ? 'Edit Role: ' . $role->name : 'New Role' }}</h2>
                    <a href="{{ route('roles.index') }}" class="btn btn-default">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </header>
                <div class="card-body">

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
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-12 col-md-3">
                            <label class="form-label"><strong>Role Name <span class="text-danger">*</span></strong></label>
                            <input type="text" name="name"
                                   value="{{ old('name', $role->name ?? '') }}"
                                   class="form-control" required
                                   {{ $role && $role->name === 'superadmin' ? 'readonly' : '' }}>
                            @error('name')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                            @if($role && $role->name === 'superadmin')
                                <small class="text-muted">Superadmin role name cannot be changed.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- ── Module Permissions ───────────────────────────── --}}
            <section class="card mt-3">
                <header class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Module Permissions</h2>
                    <div class="d-flex align-items-center gap-3">
                        <label class="mb-0 d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="checkbox" id="masterCheckAll">
                            <span class="ms-1">Select All</span>
                        </label>
                    </div>
                </header>

                <div class="card-body p-0">
                    <div style="max-height:600px; overflow-y:auto;">
                        <table class="table table-bordered mb-0" id="permissionsTable">
                            <thead class="bg-primary text-white text-center sticky-top" style="z-index:1">
                                <tr>
                                    <th class="text-start ps-3">Module</th>
                                    @foreach($actions as $actionKey => $actionLabel)
                                        <th>
                                            <label class="d-flex flex-column align-items-center gap-1 mb-0" style="cursor:pointer">
                                                <input type="checkbox" class="col-check" data-action="{{ $actionKey }}">
                                                <span class="text-white small">{{ $actionLabel }}</span>
                                            </label>
                                        </th>
                                    @endforeach
                                    <th>
                                        <label class="d-flex flex-column align-items-center gap-1 mb-0" style="cursor:pointer">
                                            <input type="checkbox" id="checkAllModules">
                                            <span class="text-white small">All</span>
                                        </label>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($modulePermissions as $module => $perms)
                                    <tr>
                                        <td class="align-middle ps-3">
                                            <strong>{{ ucwords(str_replace('_', ' ', $module)) }}</strong>
                                        </td>

                                        @foreach($actions as $actionKey => $actionLabel)
                                            <td class="text-center align-middle">
                                                @if(isset($perms[$actionKey]))
                                                    <input type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $perms[$actionKey] }}"
                                                           data-action="{{ $actionKey }}"
                                                           data-module="{{ $module }}"
                                                           class="perm-checkbox"
                                                           {{ $role && $role->hasPermissionTo($perms[$actionKey]) ? 'checked' : '' }}>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="text-center align-middle">
                                            <input type="checkbox"
                                                   class="row-check"
                                                   data-module="{{ $module }}">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 2 + count($actions) }}" class="text-center text-muted py-3">
                                            No module permissions found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ── Report Permissions ───────────────────────────── --}}
            <section class="card mt-3">
                <header class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title mb-0">Report Permissions</h2>
                    <label class="mb-0 d-flex align-items-center gap-1" style="cursor:pointer">
                        <input type="checkbox" id="checkAllReports">
                        <span class="ms-1">Select All Reports</span>
                    </label>
                </header>

                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 text-center">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="text-start ps-3">Report</th>
                                <th style="width:100px">Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportPermissions as $permission)
                                <tr>
                                    <td class="align-middle text-start ps-3">
                                        <strong>
                                            {{ ucwords(str_replace(['reports.', '_'], ['', ' '], $permission->name)) }}
                                        </strong>
                                    </td>
                                    <td class="align-middle">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               class="report-checkbox"
                                               value="{{ $permission->name }}"
                                               {{ $role && $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">
                                        No report permissions found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <footer class="card-footer text-end">
                    <a class="btn btn-default" href="{{ route('roles.index') }}">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        {{ $role ? 'Update Role' : 'Create Role' }}
                    </button>
                </footer>
            </section>

        </form>
    </div>
</div>

@push('scripts')
<script>
// ── Column toggle (check entire action column) ───────────────────
document.querySelectorAll('.col-check').forEach(function (colCb) {
    colCb.addEventListener('change', function () {
        var action = this.dataset.action;
        document.querySelectorAll('input.perm-checkbox[data-action="' + action + '"]')
            .forEach(function (cb) { cb.checked = colCb.checked; });
        syncRowChecks();
        syncMaster();
    });
});

// ── Row toggle (check all actions for a module) ──────────────────
document.querySelectorAll('.row-check').forEach(function (rowCb) {
    rowCb.addEventListener('change', function () {
        var module = this.dataset.module;
        document.querySelectorAll('input.perm-checkbox[data-module="' + module + '"]')
            .forEach(function (cb) { cb.checked = rowCb.checked; });
        syncColChecks();
        syncMaster();
    });
});

// ── Global module toggle ─────────────────────────────────────────
document.getElementById('checkAllModules').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.checked = checked;
    });
    document.querySelectorAll('input.perm-checkbox').forEach(function (cb) {
        cb.checked = checked;
    });
    syncColChecks();
    syncMaster();
});

// ── Report toggle ────────────────────────────────────────────────
document.getElementById('checkAllReports').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.report-checkbox').forEach(function (cb) {
        cb.checked = checked;
    });
    syncMaster();
});

// ── Master toggle (everything) ───────────────────────────────────
document.getElementById('masterCheckAll').addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        if (cb.id !== 'masterCheckAll') cb.checked = checked;
    });
});

// ── Individual perm checkbox → update row/col/master state ───────
document.querySelectorAll('input.perm-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
        syncRowChecks();
        syncColChecks();
        syncMaster();
    });
});
document.querySelectorAll('.report-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
        syncMaster();
    });
});

// ── Sync helpers ─────────────────────────────────────────────────
function syncRowChecks() {
    document.querySelectorAll('.row-check').forEach(function (rowCb) {
        var module = rowCb.dataset.module;
        var boxes  = document.querySelectorAll('input.perm-checkbox[data-module="' + module + '"]');
        rowCb.checked = boxes.length > 0 && Array.from(boxes).every(function (b) { return b.checked; });
    });
}
function syncColChecks() {
    document.querySelectorAll('.col-check').forEach(function (colCb) {
        var action = colCb.dataset.action;
        var boxes  = document.querySelectorAll('input.perm-checkbox[data-action="' + action + '"]');
        colCb.checked = boxes.length > 0 && Array.from(boxes).every(function (b) { return b.checked; });
    });
}
function syncMaster() {
    var allBoxes = document.querySelectorAll('input.perm-checkbox, .report-checkbox');
    var master   = document.getElementById('masterCheckAll');
    master.checked = allBoxes.length > 0 && Array.from(allBoxes).every(function (b) { return b.checked; });
}

// ── Init: sync row/col/master to match loaded state ─────────────
syncRowChecks();
syncColChecks();
syncMaster();
</script>
@endpush

@endsection