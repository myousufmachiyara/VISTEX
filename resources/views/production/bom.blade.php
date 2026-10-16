@extends('layouts.app')
@section('title', 'Packaging List (BOM)')
@section('content')
@php
  $mpu = function ($p) {
      $a = $p->getAttribute('attributes');
      return is_array($a) ? ($a['meters_per_unit'] ?? null) : null;
  };
@endphp
<div class="row">
  <div class="col-lg-5">
    <section class="card">
      <header class="card-header"><h2 class="card-title">Put-ups</h2></header>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Put-up</th><th class="text-end">m / pc</th><th class="text-end">Materials</th></tr></thead>
          <tbody>
          @foreach($putUps as $p)
            <tr class="{{ $selected && $selected->id === $p->id ? 'table-primary' : '' }}">
              <td><a href="{{ route('production.bom', ['put_up' => $p->id]) }}">{{ $p->name }}</a> <span class="small text-muted">{{ $p->sku }}</span></td>
              <td class="text-end">{{ $mpu($p) }}</td>
              <td class="text-end">{!! ($counts[$p->id] ?? 0) ? $counts[$p->id] : '<span class="text-danger">none</span>' !!}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div class="col-lg-7">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <section class="card">
      <header class="card-header"><h2 class="card-title">{{ $selected ? 'Packaging for one piece of ' . $selected->name : 'Packaging List' }}</h2></header>
      <div class="card-body">
        @if(!$selected)
          <p class="text-muted mb-0">Pick a put-up to set the packaging materials one piece uses. Packing takes these from stock automatically.</p>
        @elseif($materials->isEmpty())
          <div class="alert alert-warning mb-0">No packaging materials yet. Add them under Products in the <strong>Packaging Materials</strong> category (bags, cartons, labels, header cards, stickers…).</div>
        @else
        <form method="POST" action="{{ route('production.bom.save') }}">
          @csrf
          <input type="hidden" name="put_up_product_id" value="{{ $selected->id }}">
          <table class="table table-sm" id="bomTable">
            <thead><tr><th>Material</th><th style="width:160px">Qty per piece</th><th style="width:40px"></th></tr></thead>
            <tbody>
            @foreach(array_merge($rows, [['product_id' => null, 'qty_per_piece' => null]]) as $i => $r)
              <tr>
                <td><select name="rows[{{ $i }}][material_product_id]" class="form-control form-control-sm">
                  <option value="">—</option>
                  @foreach($materials as $m)<option value="{{ $m->id }}" @selected($r['product_id'] == $m->id)>{{ $m->name }} ({{ $m->sku }})</option>@endforeach
                </select></td>
                <td><input type="number" step="0.0001" min="0" name="rows[{{ $i }}][qty_per_piece]" class="form-control form-control-sm" value="{{ $r['qty_per_piece'] }}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">×</button></td>
              </tr>
            @endforeach
            </tbody>
          </table>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="addRow">+ Add material</button>
            @can('production_orders.edit')<button class="btn btn-primary">Save</button>@endcan
          </div>
          <div class="small text-muted mt-2">Use fractions for shared items, e.g. 0.05 carton when 20 pieces go in one carton.</div>
        </form>
        @endif
      </div>
    </section>
  </div>
</div>
<script>
document.getElementById('addRow')?.addEventListener('click', () => {
  const body = document.querySelector('#bomTable tbody'), last = body.lastElementChild, row = last.cloneNode(true), n = body.children.length;
  row.querySelectorAll('[name]').forEach(e => { e.name = e.name.replace(/rows\[\d+\]/, 'rows[' + (n + 1000) + ']'); e.value = ''; });
  body.appendChild(row);
});
</script>
@endsection
