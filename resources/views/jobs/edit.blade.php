@extends('layouts.app')
@section('title', 'Edit ' . $job->job_no)
@section('content')
<form action="{{ route('jobs.update', $job->id) }}" method="POST" enctype="multipart/form-data" onkeydown="return event.key != 'Enter' || event.target.tagName == 'TEXTAREA';">
  @csrf
  @method('PUT')
  <section class="card mb-3">
    <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h2 class="card-title">Edit {{ $job->job_no }} <span class="badge bg-{{ $job->status === 'Rejected' ? 'danger' : 'warning text-dark' }} ms-1">{{ $job->status }}</span></h2>
      <div>
        <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">{{ $job->status === 'Rejected' ? 'Save & Resubmit' : 'Save Changes' }}</button>
      </div>
    </header>
  </section>
  @include('jobs._form', ['job' => $job])
</form>
@endsection
