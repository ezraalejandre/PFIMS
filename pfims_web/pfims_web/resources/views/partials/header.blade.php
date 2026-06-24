<!-- Shared header and sidebar with role-based link visibility -->
@php
    $role = auth()->check() ? (auth()->user()->role ?? 'user') : 'guest';
@endphp

<!-- Top header -->
<header class="top-header">
    <div class="left">
        <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
        <div class="brand-text">
            PFIMS
            <small>E.V. Catapang Design-Construction & Supply</small>
        </div>
    </div>

    <div class="right">
        @if(auth()->check())
            <a href="{{ url('/notifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>

            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name }}</span>
            </a>

        @else
            <a href="{{ url('/') }}">Sign in</a>
        @endif
    </div>
</header>

<!-- Sidebar with role-based links -->
<aside class="sidebar">
    <nav>
        <ul>
            
            <li class="{{ request()->is('dashboard') ? 'active' : '' }}"><a href="{{ url('/dashboard') }}">DASHBOARD</a></li>

            @if(in_array($role, ['admin','project']))
                <li class="{{ request()->is('projects*') ? 'active' : '' }}"><a href="{{ url('/projects') }}">PROJECTS</a></li>
                <li class="{{ request()->is('finance*') ? 'active' : '' }}"><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li class="{{ request()->is('inventory*') ? 'active' : '' }}"><a href="{{ url('/inventory') }}">INVENTORY</a></li>
                <li class="{{ request()->is('suppliers*') ? 'active' : '' }}"><a href="{{ url('/suppliers') }}">SUPPLIERS</a></li>
                <li class="{{ request()->is('reports*') ? 'active' : '' }}"><a href="{{ url('/reports') }}">REPORTS</a></li>
            @endif

            @if(in_array($role, ['accounting','finance']))
                <li class="{{ request()->is('finance*') ? 'active' : '' }}"><a href="{{ url('/finance') }}">FINANCE</a></li>
            @endif

            @if(in_array($role, ['operations','inventory']))
                <li class="{{ request()->is('projects*') ? 'active' : '' }}"><a href="{{ url('/projects') }}">PROJECTS</a></li>
                <li class="{{ request()->is('inventory*') ? 'active' : '' }}"><a href="{{ url('/inventory') }}">INVENTORY</a></li>
                <li class="{{ request()->is('suppliers*') ? 'active' : '' }}"><a href="{{ url('/suppliers') }}">SUPPLIERS</a></li>
            @endif
        
        </ul>
    </nav>

    <div class="bottom-nav">
        <ul>
            <li>
                <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                    <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon">
                    Settings
                </a>
            </li>
            <li class="logout">
                <form action="{{ url('/logout') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; padding: 0; cursor: pointer;">
                        <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon">
                        Log out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</aside>
