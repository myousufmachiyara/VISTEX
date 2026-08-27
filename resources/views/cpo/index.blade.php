@extends('layouts.app')
@section('title', 'Conversion POs')
@section('content')
<div class="row"><div class="col">
  <section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title">Conversion POs (Weaving)</h2>
      @can('cpo.create')
      <a href="{{ route('cpo.create') }}" class="btn btn-primary">New CPO</a>
      @endcan
    </header>
    <div class="card-body">
      <table class="table table-bordered table-striped" id="cpoTable">
        <thead><tr><th>CPO #</th><th>Date</th><th>Vendor</th><th>Item</th><th class="text-end">Meters</th><th class="text-end">Yarn Required</th><th class="text-end">Weaving Cost</th><th class="text-end">Net Amount</th><th>Actions</th></tr></thead>
        <tbody>
          @foreach($cpos as $cpo)
          <tr>
            <td class="text-primary">{{ $cpo->cpo_no }}</td>
            <td>{{ $cpo->po_date->format('d-M-Y') }}</td>
            <td>{{ $cpo->vendor->name ?? '' }}</td>
            <td>{{ $cpo->item_name }}</td>
            <td class="text-end">{{ number_format($cpo->total_meters_required, 3) }}</td>
            <td class="text-end">{{ number_format($cpo->total_yarn_weight_consumed, 3) }}</td>
            <td class="text-end">{{ number_format($cpo->weaving_cost, 2) }}</td>
            <td class="text-end fw-bold">{{ number_format($cpo->net_amount, 2) }}</td>
            <td>
              @can('cpo.delete')
              <form action="{{ route('cpo.destroy', $cpo->id) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
              </form>
              @endcan
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
</div></div>
<script>$(document).ready(()=>$('#cpoTable').DataTable({pageLength:50,order:[[1,'desc']]}));</script>
@endsection