@extends('layouts.app')
@section('title', 'New Customer Order')
@section('content')
<form action="{{ route('jobs.store') }}" method="POST" enctype="multipart/form-data" onkeydown="return event.key != 'Enter' || event.target.tagName == 'TEXTAREA';">
  @csrf
  <section class="card mb-3">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">New Customer Order (Job)</h2>
      <div>
        <a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">Save &amp; Send for Approval</button>
      </div>
    </header>
  </section>
  @include('jobs._form', ['job' => null])
</form>
@endsection
