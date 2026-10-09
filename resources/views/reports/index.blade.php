@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="row"><div class="col">
  @foreach($families as $group => $fam)
  <section class="card mb-4" id="{{ $group }}">
    <header class="card-header"><h2 class="card-title">{{ $fam['title'] }}</h2></header>
    <div class="card-body">
      <div class="row">
        @foreach($fam['reports'] as $key => $r)
        <div class="col-md-4 col-lg-3 mb-3">
          <a href="{{ route('reports.show', [$group, $key]) }}" class="card h-100 text-decoration-none border report-tile">
            <div class="card-body py-3">
              <div class="fw-bold text-dark">{{ $loop->iteration }}. {{ $r['title'] }}</div>
              <div class="small text-muted mt-1">{{ $r['desc'] }}</div>
            </div>
          </a>
        </div>
        @endforeach
      </div>
    </div>
  </section>
  @endforeach
</div></div>
<style>.report-tile:hover{border-color:var(--bs-primary)!important;box-shadow:0 2px 8px rgba(0,0,0,.08)}</style>
@endsection
