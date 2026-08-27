@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">

  <ul class="nav nav-tabs flex-wrap">
    <li class="nav-item">
      <a class="nav-link {{ $tab=='IL'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=IL&from_date={{ $from }}&to_date={{ $to }}">
        <i class="fas fa-list me-1"></i> Item Ledger
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='SR'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=SR">
        <i class="fas fa-boxes me-1"></i> Stock In Hand
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='LOC'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=LOC">
        <i class="fas fa-truck me-1"></i> Vendor Stock
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='STR'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=STR&from_date={{ $from }}&to_date={{ $to }}">
        <i class="fas fa-exchange-alt me-1"></i> Stock Movements
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='NMI'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=NMI">
        <i class="fas fa-clock me-1"></i> Non-Moving Items
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='ROL'?'active':'' }}" href="{{ route('reports.inventory') }}?tab=ROL">
        <i class="fas fa-exclamation-triangle me-1"></i> Reorder Level
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ── 1. ITEM LEDGER ───────────────────────────────────────── --}}
    @if($tab === 'IL')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="IL">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>Product</label>
            <select name="item_id" class="form-control select2-js">
              <option value="">-- Select a product --</option>
              @foreach($products as $p)
                <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }}>
                  {{ $p->name }} ({{ $p->sku }})
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from_date" value="{{ $from }}" class="form-control">
          </div>
          <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to_date" value="{{ $to }}" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      @if($productId)
        @php
          $totalIn  = $itemLedger->sum('qty_in');
          $totalOut = $itemLedger->sum('qty_out');
          $balance  = $openingBalance + $totalIn - $totalOut;
        @endphp

        <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-3">
          <div>
            <i class="fas fa-history me-1"></i>
            <strong>Opening Balance</strong> as of {{ \Carbon\Carbon::parse($from)->format('d-M-Y') }}:
            <strong class="{{ $openingBalance >= 0 ? 'text-success' : 'text-danger' }}">
              {{ number_format($openingBalance, 3) }}
            </strong>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col">
            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-success">Purchase → IN</span>
              <span class="badge bg-warning text-dark">Purchase Return → OUT</span>
              <span class="badge bg-secondary">Gate Pass Out → OUT</span>
              <span class="badge bg-primary">Job Receive Output → IN</span>
            </div>
          </div>
          <div class="col-auto text-end">
            <span class="me-3">Total In: <strong class="text-success">{{ number_format($totalIn, 3) }}</strong></span>
            <span class="me-3">Total Out: <strong class="text-danger">{{ number_format($totalOut, 3) }}</strong></span>
            <span>Closing Balance: <strong class="text-primary">{{ number_format($balance, 3) }}</strong></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm" id="ilTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th class="text-end">Qty In</th>
                <th class="text-end">Qty Out</th>
                <th class="text-end">Value</th>
                <th class="text-end">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-secondary fw-bold">
                <td colspan="6">Opening Balance</td>
                <td class="text-end">{{ number_format($openingBalance, 3) }}</td>
              </tr>
              @forelse($itemLedger as $row)
                <tr class="{{ $row['qty_out'] > 0 ? 'table-danger' : '' }}">
                  <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-M-Y') }}</td>
                  <td><span class="badge bg-{{ $row['qty_in'] > 0 ? 'success' : 'danger' }}">{{ $row['type'] }}</span></td>
                  <td><small>{{ $row['description'] }}</small></td>
                  <td class="text-end text-success fw-bold">{{ $row['qty_in'] > 0 ? number_format($row['qty_in'], 3) : '—' }}</td>
                  <td class="text-end text-danger fw-bold">{{ $row['qty_out'] > 0 ? number_format($row['qty_out'], 3) : '—' }}</td>
                  <td class="text-end">{{ $row['value'] > 0 ? number_format($row['value'], 2) : '—' }}</td>
                  <td class="text-end">{{ number_format($row['balance'], 3) }}</td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted">No movements in this period.</td></tr>
              @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="3" class="text-end">Totals</td>
                <td class="text-end text-success">{{ number_format($totalIn, 3) }}</td>
                <td class="text-end text-danger">{{ number_format($totalOut, 3) }}</td>
                <td></td>
                <td class="text-primary">{{ number_format($balance, 3) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      @else
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-1"></i> Select a product above and click Filter to view its ledger.
        </div>
      @endif
    @endif

    {{-- ── 2. STOCK IN HAND ────────────────────────────────────── --}}
    @if($tab === 'SR')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="SR">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>Category</label>
            <select name="category_id" class="form-control select2-js">
              <option value="">-- All Categories --</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      @php
        $grandQty   = $stockInHand->sum('quantity');
        $grandTotal = $stockInHand->sum('total');
      @endphp

      <div class="row mb-3">
        <div class="col text-end">
          <span class="me-3">Total Qty: <strong>{{ number_format($grandQty, 3) }}</strong></span>
          <span>Total Value: <strong class="text-danger fs-5">PKR {{ number_format($grandTotal, 0) }}</strong></span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="srTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>SKU</th>
              <th>Category</th>
              <th>Unit</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Avg Cost</th>
              <th class="text-end">Value</th>
            </tr>
          </thead>
          <tbody>
            @forelse($stockInHand as $s)
              <tr class="{{ $s['quantity'] < 0 ? 'table-danger' : '' }}">
                <td>{{ $s['product'] }}</td>
                <td>{{ $s['sku'] }}</td>
                <td>{{ $s['category'] }}</td>
                <td>{{ $s['unit'] }}</td>
                <td class="text-end fw-bold {{ $s['quantity'] < 0 ? 'text-danger' : '' }}">{{ number_format($s['quantity'], 3) }}</td>
                <td class="text-end">{{ number_format($s['rate'], 2) }}</td>
                <td class="text-end">{{ number_format($s['total'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No stock data found.</td></tr>
            @endforelse
          </tbody>
          @if($stockInHand->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="4" class="text-end">Grand Total</td>
              <td class="text-end">{{ number_format($grandQty, 3) }}</td>
              <td></td>
              <td class="text-end text-danger">{{ number_format($grandTotal, 2) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    @endif

    {{-- ── 3. VENDOR STOCK (Fresh / Issued / Leftover) ─────────── --}}
    @if($tab === 'LOC')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="LOC">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>Vendor</label>
            <select name="vendor_id" class="form-control select2-js" onchange="this.form.submit()">
              <option value="">-- All Vendors --</option>
              @foreach($vendors as $v)
                <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </form>

      <div class="alert alert-warning mb-3">
        <i class="fas fa-truck me-1"></i>
        Raw material currently sitting at vendor locations —
        <strong>Fresh</strong> (untouched), <strong>Issued</strong> (allocated to an active job),
        <strong>Leftover</strong> (returned unused from a job receive, available for reissue).
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="locTable">
          <thead class="table-light">
            <tr>
              <th>Vendor</th>
              <th>Product</th>
              <th class="text-end">Fresh</th>
              <th class="text-end">Issued</th>
              <th class="text-end">Leftover</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($vendorStock as $row)
              <tr>
                <td>{{ $row['vendor'] }}</td>
                <td>{{ $row['product'] }} ({{ $row['sku'] }})</td>
                <td class="text-end">{{ number_format($row['fresh'], 3) }}</td>
                <td class="text-end">{{ number_format($row['issued'], 3) }}</td>
                <td class="text-end">{{ number_format($row['leftover'], 3) }}</td>
                <td class="text-end fw-bold">{{ number_format($row['total'], 3) }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-3">No stock currently held at any vendor.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

    {{-- ── 4. STOCK MOVEMENTS (Gate Pass out / Job Receive output) ── --}}
    @if($tab === 'STR')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="STR">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label>Vendor</label>
            <select name="vendor_id" class="form-control select2-js">
              <option value="">-- All --</option>
              @foreach($vendors as $v)
                <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from_date" value="{{ $from }}" class="form-control">
          </div>
          <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to_date" value="{{ $to }}" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="strTable">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Type</th>
              <th>Product</th>
              <th>From</th>
              <th>To</th>
              <th class="text-end">Qty</th>
            </tr>
          </thead>
          <tbody>
            @forelse($stockMovements as $m)
              @php
                $badge = $m['type'] === 'Gate Pass Out' ? 'bg-secondary' : 'bg-primary';
              @endphp
              <tr>
                <td>{{ \Carbon\Carbon::parse($m['date'])->format('d-M-Y') }}</td>
                <td>{{ $m['reference'] }}</td>
                <td><span class="badge {{ $badge }}">{{ $m['type'] }}</span></td>
                <td>{{ $m['product'] }}</td>
                <td>{{ $m['from'] }}</td>
                <td>{{ $m['to'] }}</td>
                <td class="text-end">{{ number_format($m['quantity'], 3) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No stock movements found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

    {{-- ── 5. NON-MOVING ITEMS ─────────────────────────────────── --}}
    @if($tab === 'NMI')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="NMI">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>No movement in last (months)</label>
            <input type="number" name="months" value="{{ request('months', 3) }}" min="1" max="60" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="alert alert-warning mb-3">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Items with stock on hand but no purchase/sale movement in the last <strong>{{ request('months', 3) }} months</strong>.
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="nmiTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>SKU</th>
              <th class="text-end">Stock Qty</th>
              <th>Last Movement</th>
              <th class="text-end">Days Inactive</th>
            </tr>
          </thead>
          <tbody>
            @forelse($nonMovingItems as $nmi)
              @php $critical = is_numeric($nmi['days_inactive']) && $nmi['days_inactive'] > 180; @endphp
              <tr class="{{ $critical ? 'table-danger' : 'table-warning' }}">
                <td>{{ $nmi['product'] }}</td>
                <td>{{ $nmi['sku'] }}</td>
                <td class="text-end">{{ number_format($nmi['stock_qty'], 3) }}</td>
                <td>
                  @if($nmi['last_date'] === 'Never')
                    <span class="badge bg-danger">Never Moved</span>
                  @else
                    {{ \Carbon\Carbon::parse($nmi['last_date'])->format('d-M-Y') }}
                  @endif
                </td>
                <td class="text-end fw-bold">{{ is_numeric($nmi['days_inactive']) ? $nmi['days_inactive'].' days' : '∞' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-success py-3"><i class="fas fa-check-circle me-1"></i> All items have recent movement.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

    {{-- ── 6. REORDER LEVEL ────────────────────────────────────── --}}
    @if($tab === 'ROL')
      <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-circle me-1"></i>
        Items where <strong>current stock ≤ reorder level</strong>. Only products with a reorder level set are shown.
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="rolTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>SKU</th>
              <th class="text-end">Stock In Hand</th>
              <th class="text-end">Reorder Level</th>
              <th class="text-end">Shortage</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reorderLevel as $rl)
              <tr class="{{ $rl['stock_inhand'] <= 0 ? 'table-danger' : 'table-warning' }}">
                <td>{{ $rl['product'] }}</td>
                <td>{{ $rl['sku'] }}</td>
                <td class="text-end {{ $rl['stock_inhand'] <= 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($rl['stock_inhand'], 3) }}</td>
                <td class="text-end">{{ number_format($rl['reorder_level'], 3) }}</td>
                <td class="text-end text-danger fw-bold">{{ number_format($rl['shortage'], 3) }}</td>
                <td>
                  @if($rl['stock_inhand'] <= 0)
                    <span class="badge bg-danger">Out of Stock</span>
                  @else
                    <span class="badge bg-warning text-dark">Reorder Now</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-success py-3"><i class="fas fa-check-circle me-1"></i> All stock levels are above reorder points.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    $('#ilTable, #srTable, #locTable, #strTable, #nmiTable, #rolTable').DataTable({
      pageLength: 100,
      order: [],
    });
  });
</script>
@endsection