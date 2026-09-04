<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title d-flex justify-content-between">
      <a href="{{ route('dashboard') }}" class="logo">
        <img src="{{ asset('assets/img/billtrix-logo-1.png') }}" class="sidebar-logo" alt="BillTrix" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
        <i class="fas fa-times"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars"></i>
    </div>
  </div>

  <div class="nano"><div class="nano-content">
    <nav id="menu" class="nav-main" role="navigation">
      <ul class="nav nav-main">

        <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
          <a class="nav-link" href="{{ route('dashboard') }}"><i class="fa fa-tachometer-alt"></i><span>Dashboard</span></a>
        </li>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- SYSTEM — Users, Roles, Permissions                       --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['user_roles.index','users.index']))
        <li class="nav-parent {{ request()->routeIs('roles.*','permissions.*','users.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-user-shield"></i><span>System</span></a>
          <ul class="nav nav-children">
            @can('user_roles.index')<li class="{{ request()->routeIs('roles.*','permissions.*')?'active':'' }}"><a class="nav-link" href="{{ route('roles.index') }}">Roles &amp; Permissions</a></li>@endcan
            @can('users.index')<li class="{{ request()->routeIs('users.*')?'active':'' }}"><a class="nav-link" href="{{ route('users.index') }}">Users</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- ACCOUNTS — Chart of Accounts, Sub Heads, Tax              --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['coa.index','shoa.index','tax_masters.index']))
        <li class="nav-parent {{ request()->routeIs('coa.*','shoa.*','account-mappings.*','tax-masters.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-book"></i><span>Accounts</span></a>
          <ul class="nav nav-children">
            @can('coa.index')<li class="{{ request()->routeIs('coa.*','account-mappings.*')?'active':'' }}"><a class="nav-link" href="{{ route('coa.index') }}">Chart of Accounts</a></li>@endcan
            @can('shoa.index')<li class="{{ request()->routeIs('shoa.*')?'active':'' }}"><a class="nav-link" href="{{ route('shoa.index') }}">Sub Heads</a></li>@endcan
            @can('tax_masters.index')<li class="{{ request()->routeIs('tax-masters.*')?'active':'' }}"><a class="nav-link" href="{{ route('tax-masters.index') }}">Tax Master</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- PARTIES — Customers, Vendors, Brokers, Locations          --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['customers.index','vendors.index','brokers.index','locations.index']))
        <li class="nav-parent {{ request()->routeIs('customers.*','vendors.*','brokers.*','locations.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-address-book"></i><span>Parties</span></a>
          <ul class="nav nav-children">
            @can('customers.index')<li class="{{ request()->routeIs('customers.*')?'active':'' }}"><a class="nav-link" href="{{ route('customers.index') }}">Customers</a></li>@endcan
            @can('vendors.index')<li class="{{ request()->routeIs('vendors.*')?'active':'' }}"><a class="nav-link" href="{{ route('vendors.index') }}">Vendors</a></li>@endcan
            @can('brokers.index')<li class="{{ request()->routeIs('brokers.*')?'active':'' }}"><a class="nav-link" href="{{ route('brokers.index') }}">Brokers</a></li>@endcan
            @can('locations.index')<li class="{{ request()->routeIs('locations.*')?'active':'' }}"><a class="nav-link" href="{{ route('locations.index') }}">Locations</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- CATALOG — Categories, Units, Products, Service Types, SKU Rates --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['product_categories.index','measurement_units.index','products.index','service_types.index','customer_sku_rates.index']))
        <li class="nav-parent {{ request()->routeIs('product_categories.*','measurement_units.*','products.*','service_types.*','customer_sku_rates.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-layer-group"></i><span>Catalog</span></a>
          <ul class="nav nav-children">
            @can('product_categories.index')<li class="{{ request()->routeIs('product_categories.*')?'active':'' }}"><a class="nav-link" href="{{ route('product_categories.index') }}">Product Categories</a></li>@endcan
            @can('measurement_units.index')<li class="{{ request()->routeIs('measurement_units.*')?'active':'' }}"><a class="nav-link" href="{{ route('measurement_units.index') }}">Measurement Units</a></li>@endcan
            @can('products.index')<li class="{{ request()->routeIs('products.*')?'active':'' }}"><a class="nav-link" href="{{ route('products.index') }}">Products</a></li>@endcan
            @can('service_types.index')<li class="{{ request()->routeIs('service_types.*')?'active':'' }}"><a class="nav-link" href="{{ route('service_types.index') }}">Service Types</a></li>@endcan
            @can('customer_sku_rates.index')<li class="{{ request()->routeIs('customer_sku_rates.*')?'active':'' }}"><a class="nav-link" href="{{ route('customer_sku_rates.index') }}">Customer SKU Rates</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- TERMS & CONDITIONS — standalone                            --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @can('terms_and_conditions.index')
        <li class="{{ request()->routeIs('terms_and_conditions.*')?'active':'' }}"><a class="nav-link" href="{{ route('terms_and_conditions.index') }}"><i class="fa fa-file-contract"></i><span>Terms &amp; Conditions</span></a></li>
        @endcan

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- PURCHASE — PO, Objections, Challans, Receiving, Return     --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['purchase_orders.index','challans.index','purchase_receivings.index','purchase_returns.index']))
        <li class="nav-parent {{ request()->routeIs('purchase_orders.*','purchase_order_objections.*','challans.*','purchase_receivings.*','purchase_returns.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-shopping-cart"></i><span>Purchase</span></a>
          <ul class="nav nav-children">
            @can('purchase_orders.index')<li class="{{ request()->routeIs('purchase_orders.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_orders.index') }}">Purchase Orders</a></li>@endcan
            @can('purchase_orders.index')<li class="{{ request()->routeIs('purchase_order_objections.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_order_objections.index') }}">Objections</a></li>@endcan
            @can('challans.index')<li class="{{ request()->routeIs('challans.*')?'active':'' }}"><a class="nav-link" href="{{ route('challans.index') }}">Challans</a></li>@endcan
            @can('purchase_receivings.index')<li class="{{ request()->routeIs('purchase_receivings.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_receivings.index') }}">Receivings (GRN)</a></li>@endcan
            @can('purchase_returns.index')<li class="{{ request()->routeIs('purchase_returns.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_returns.index') }}">Returns</a></li>@endcan
          </ul>
        </li>
        @endif

        
        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- PRODUCTION — Yarn Issue, Stock Movement, Processing        --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['yarn_issues.index','stock_movements.index','processing_issues.index']))
        <li class="nav-parent {{ request()->routeIs('yarn_issues.*','stock_movements.*','processing_issues.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-industry"></i><span>Production</span></a>
          <ul class="nav nav-children">
            @can('yarn_issues.index')<li class="{{ request()->routeIs('yarn_issues.*')?'active':'' }}"><a class="nav-link" href="{{ route('yarn_issues.index') }}">Yarn Issue</a></li>@endcan
            @can('stock_movements.index')<li class="{{ request()->routeIs('stock_movements.*')?'active':'' }}"><a class="nav-link" href="{{ route('stock_movements.index') }}">Stock Movement</a></li>@endcan
            @can('processing_issues.index')<li class="{{ request()->routeIs('processing_issues.*')?'active':'' }}"><a class="nav-link" href="{{ route('processing_issues.index') }}">Processing Issue</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- SALES — Forecasting + Jobs                                 --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['forecasts.index','jobs.index']))
        <li class="nav-parent {{ request()->routeIs('forecasts.*','jobs.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-chart-line"></i><span>Sales</span></a>
          <ul class="nav nav-children">
            @can('forecasts.index')<li class="{{ request()->routeIs('forecasts.*')?'active':'' }}"><a class="nav-link" href="{{ route('forecasts.index') }}">Forecasting</a></li>@endcan
            @can('jobs.index')<li class="{{ request()->routeIs('jobs.*')?'active':'' }}"><a class="nav-link" href="{{ route('jobs.index') }}">Jobs / Customer Orders</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- FINANCE — PDC + Vouchers                                   --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if(auth()->user()->canAny(['vouchers.index','pdcs.index']))
        <li class="nav-parent {{ request()->routeIs('vouchers.*','pdcs.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-money-check-alt"></i><span>Finance</span></a>
          <ul class="nav nav-children">
            @can('pdcs.index')<li class="{{ request()->routeIs('pdcs.*')?'active':'' }}"><a class="nav-link" href="{{ route('pdcs.index') }}">PDC Cheques</a></li>@endcan
            @can('vouchers.index')
              @foreach(['receipt'=>'Receipt Vouchers','payment'=>'Payment Vouchers','journal'=>'Journal Vouchers','contra'=>'Contra Vouchers','system'=>'System Vouchers'] as $t=>$l)
              <li class="{{ request()->is("vouchers/{$t}*")?'active':'' }}"><a class="nav-link" href="{{ route('vouchers.index',$t) }}">{{ $l }}</a></li>
              @endforeach
            @endcan
          </ul>
        </li>
        @endif

      </ul>
    </nav>
  </div></div>
</aside>