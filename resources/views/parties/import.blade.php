@extends('layouts.app')
@section('title', 'Import ' . ucfirst($type) . 's')
@section('content')
<div class="row"><div class="col-md-8">
  <section class="card">
    <header class="card-header"><h2 class="card-title">Import {{ ucfirst($type) }}s from CSV/TSV</h2></header>
    <div class="card-body">
      @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
      @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert alert-warning">
          <strong>{{ count(session('import_errors')) }} rows had issues:</strong>
          <ul class="mb-0">@foreach(session('import_errors') as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ route('parties.import', $type) }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">File (CSV or tab-separated, first row must be a header)</label>
          <input type="file" name="import_file" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="alert alert-info py-2">
          <strong>Expected header columns</strong> (case-insensitive, extra columns are ignored):
          <code class="d-block mt-1">
            name{{ $type === 'vendor' ? ', vendor_type' : '' }}, phone, email, contact_person, address, city,
            tax_id_number, payment_terms_type, payment_days, currency,
            opening_balance, opening_type, opening_balance_date{{ $type === 'customer' ? ', credit_limit' : '' }}, notes, is_active
          </code>
          <p class="mb-0 mt-2">Rows whose <code>name</code> already exists are skipped, not overwritten.</p>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
        <a href="{{ route($type === 'customer' ? 'customers.index' : 'vendors.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div>
  </section>
</div></div>
@endsection