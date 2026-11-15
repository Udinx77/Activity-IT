{{-- Menu sidebar: Activity Plan & Action (sisipkan lewat @include di includes/sidebar.blade.php) --}}
@php $actOpen = request()->routeIs('act.*'); @endphp
<li class="nav-header">Activity Plan &amp; Action </li>
<li class="nav-item {{ $actOpen ? 'menu-open' : '' }}">
  <a href="#" class="nav-link {{ $actOpen ? 'active' : '' }}">
    <i class="nav-icon fas fa-shoe-prints"></i>
    <p>
      Data Activity
      <i class="right fas fa-angle-left"></i>
    </p>
  </a>
  <ul class="nav nav-treeview">
    <li class="nav-item">
      <a href="{{ route('act.dashboard') }}" class="nav-link {{ request()->routeIs('act.dashboard') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Dashboard Activity</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.team.generate') }}" class="nav-link {{ request()->routeIs('act.team.*') || request()->routeIs('act.plan.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Generate Tim</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.activity.index') }}" class="nav-link {{ request()->routeIs('act.activity.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Daily / Weekly / Monthly</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.pending.index') }}" class="nav-link {{ request()->routeIs('act.pending.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Pending / Continue</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.closed.index') }}" class="nav-link {{ request()->routeIs('act.closed.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Closed</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.import.index') }}" class="nav-link {{ request()->routeIs('act.import.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Import CSV / Excel</p>
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('act.master.index') }}" class="nav-link {{ request()->routeIs('act.master.*') ? 'active' : '' }}">
        <i class="far fa-circle nav-icon"></i><p>Master Activity</p>
      </a>
    </li>
  </ul>
</li>
