@extends('layouts.app')
@section('title', 'Import Chart of Accounts')
@section('content')
<div class="row"><div class="col-md-8">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Import Chart of Accounts</h2></header>
    <div class="card-body">
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
      @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert alert-warning">
          <strong>{{ count(session('import_errors')) }} rows had issues:</strong>
          <ul class="mb-0">
            @foreach(session('import_errors') as $err)
              <li>{{ $err['account_code'] ?? '' }}: {{ $err['message'] ?? '' }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('coa.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">File (.xlsx, .xls, or .csv)</label>
          <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <p><a href="{{ route('coa.import.template') }}">Download template</a></p>
        <button type="submit" class="btn btn-primary">Import</button>
        <a href="{{ route('coa.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div>
  </section>
</div></div>
@endsection