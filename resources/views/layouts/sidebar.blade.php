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

        {{-- ═══ MODULE 8: USERS ═══ --}}
        @if(auth()->user()->canAny(['user_roles.index', 'users.index']))
        <li class="nav-parent {{ request()->routeIs('roles.*','users.*','permissions.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-user-shield"></i><span>Users</span></a>
          <ul class="nav nav-children">
            @can('user_roles.index')<li class="{{ request()->routeIs('roles.*')?'active':'' }}"><a class="nav-link" href="{{ route('roles.index') }}">Roles &amp; Permissions</a></li>@endcan
            @can('users.index')<li class="{{ request()->routeIs('users.*')?'active':'' }}"><a class="nav-link" href="{{ route('users.index') }}">All Users</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 1-2: ACCOUNTS ═══ --}}
        @if(auth()->user()->canAny(['coa.index','shoa.index','tax_masters.index']))
        <li class="nav-parent {{ request()->routeIs('coa.*','shoa.*','account-mappings.*','tax-masters.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-book"></i><span>Accounts</span></a>
          <ul class="nav nav-children">
            @can('coa.index')<li class="{{ request()->routeIs('coa.*')?'active':'' }}"><a class="nav-link" href="{{ route('coa.index') }}">Chart of Accounts</a></li>@endcan
            @can('shoa.index')<li class="{{ request()->routeIs('shoa.*')?'active':'' }}"><a class="nav-link" href="{{ route('shoa.index') }}">Sub Heads</a></li>@endcan
            @can('coa.index')<li class="{{ request()->routeIs('account-mappings.*')?'active':'' }}"><a class="nav-link" href="{{ route('account-mappings.index') }}">Account Mappings</a></li>@endcan
            @can('tax_masters.index')<li class="{{ request()->routeIs('tax-masters.*')?'active':'' }}"><a class="nav-link" href="{{ route('tax-masters.index') }}">Tax Master</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 3: PARTIES ═══ --}}
        @if(auth()->user()->canAny(['customers.index','vendors.index']))
        <li class="nav-parent {{ request()->routeIs('customers.*','vendors.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-address-book"></i><span>Parties</span></a>
          <ul class="nav nav-children">
            @can('customers.index')<li class="{{ request()->routeIs('customers.*')?'active':'' }}"><a class="nav-link" href="{{ route('customers.index') }}">Customers</a></li>@endcan
            @can('vendors.index')<li class="{{ request()->routeIs('vendors.*')?'active':'' }}"><a class="nav-link" href="{{ route('vendors.index') }}">Vendors</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 4: LOCATIONS ═══ --}}
        @can('locations.index')
        <li class="{{ request()->routeIs('locations.*')?'active':'' }}"><a class="nav-link" href="{{ route('locations.index') }}"><i class="fa fa-map-marker-alt"></i><span>Locations</span></a></li>
        @endcan

        {{-- ═══ MODULE 5+7: PRODUCTS ═══ --}}
        @php $productPerms=['product_categories.index','measurement_units.index','products.index']; @endphp
        @if(auth()->user()->canAny($productPerms))
        <li class="nav-parent {{ request()->routeIs('product_categories.*','measurement_units.*','products.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-layer-group"></i><span>Products</span></a>
          <ul class="nav nav-children">
            @can('measurement_units.index')<li class="{{ request()->routeIs('measurement_units.*')?'active':'' }}"><a class="nav-link" href="{{ route('measurement_units.index') }}">M.Units</a></li>@endcan
            @can('product_categories.index')<li class="{{ request()->routeIs('product_categories.*')?'active':'' }}"><a class="nav-link" href="{{ route('product_categories.index') }}">Categories</a></li>@endcan
            @can('products.index')<li class="{{ request()->routeIs('products.*')?'active':'' }}"><a class="nav-link" href="{{ route('products.index') }}">All Products</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 9: SERVICE TYPES ═══ --}}
        @can('service_types.index')
        <li class="{{ request()->routeIs('service_types.*')?'active':'' }}"><a class="nav-link" href="{{ route('service_types.index') }}"><i class="fa fa-cogs"></i><span>Service Types</span></a></li>
        @endcan

        {{-- ═══ MODULE 10: CUSTOMER SKU RATES ═══ --}}
        @can('customer_sku_rates.index')
        <li class="{{ request()->routeIs('customer_sku_rates.*')?'active':'' }}"><a class="nav-link" href="{{ route('customer_sku_rates.index') }}"><i class="fa fa-tags"></i><span>Customer SKU Rates</span></a></li>
        @endcan

        {{-- ═══ MODULE 12: FORECASTING ═══ --}}
        @can('forecasts.index')
        <li class="{{ request()->routeIs('forecasts.*')?'active':'' }}"><a class="nav-link" href="{{ route('forecasts.index') }}"><i class="fa fa-chart-line"></i><span>Forecasting</span></a></li>
        @endcan

        {{-- ═══ MODULE 13-14: PURCHASE ═══ --}}
        @if(auth()->user()->canAny(['purchase_orders.index','purchase_receivings.index']))
        <li class="nav-parent {{ request()->routeIs('purchase_orders.*','purchase_receivings.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-shopping-cart"></i><span>Purchase</span></a>
          <ul class="nav nav-children">
            @can('purchase_orders.index')<li class="{{ request()->routeIs('purchase_orders.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_orders.index') }}">Purchase Orders</a></li>@endcan
            @can('purchase_receivings.index')<li class="{{ request()->routeIs('purchase_receivings.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_receivings.index') }}">Receivings (GRN)</a></li>@endcan
            @can('purchase_orders.index')<li class="{{ request()->routeIs('purchase_order_objections.*')?'active':'' }}"><a class="nav-link" href="{{ route('purchase_order_objections.index') }}">Objections</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 15-17: WEAVING ═══ --}}
        @if(auth()->user()->canAny(['cpo.index','yarn_issues.index','greige_receives.index']))
        <li class="nav-parent {{ request()->routeIs('cpo.*','yarn_issues.*','greige_receives.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-exchange-alt"></i><span>Weaving</span></a>
          <ul class="nav nav-children">
            @can('cpo.index')<li class="{{ request()->routeIs('cpo.*')?'active':'' }}"><a class="nav-link" href="{{ route('cpo.index') }}">Conversion POs</a></li>@endcan
            @can('yarn_issues.index')<li class="{{ request()->routeIs('yarn_issues.*')?'active':'' }}"><a class="nav-link" href="{{ route('yarn_issues.index') }}">Yarn Issue</a></li>@endcan
            @can('greige_receives.index')<li class="{{ request()->routeIs('greige_receives.*')?'active':'' }}"><a class="nav-link" href="{{ route('greige_receives.index') }}">Greige Receive</a></li>@endcan
          </ul>
        </li>
        @endif

        {{-- ═══ MODULE 11: VOUCHERS ═══ --}}
        @can('vouchers.index')
        <li class="nav-parent {{ request()->routeIs('vouchers.*') ? 'nav-expanded active' : '' }}">
          <a class="nav-link" href="#"><i class="fa fa-money-check-alt"></i><span>Vouchers</span></a>
          <ul class="nav nav-children">
            @foreach(['receipt'=>'Receipt','payment'=>'Payment','journal'=>'Journal','contra'=>'Contra','system'=>'System Generated'] as $t=>$l)
            <li class="{{ request()->is("vouchers/{$t}*")?'active':'' }}"><a class="nav-link" href="{{ route('vouchers.index',$t) }}">{{ $l }}</a></li>
            @endforeach
          </ul>
        </li>
        @endcan

      </ul>
    </nav>
  </div></div>
</aside>