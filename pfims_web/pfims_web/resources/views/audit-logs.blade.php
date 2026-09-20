@php
    $navigation = [
        'dashboard' => ['label' => 'DASHBOARD', 'icon' => 'dashboard.png', 'url' => '/dashboard'],
        'projects' => ['label' => 'PROJECTS', 'icon' => 'projects.png', 'url' => '/projects'],
        'finance' => ['label' => 'FINANCE', 'icon' => 'finance.png', 'url' => '/finance'],
        'inventory' => ['label' => 'INVENTORY', 'icon' => 'inventory.png', 'url' => '/inventory'],
        'reports' => ['label' => 'REPORTS', 'icon' => 'reports.png', 'url' => '/reports'],
    ];
    $firstPage = max(1, $logs->currentPage() - 2);
    $lastPage = min($logs->lastPage(), $logs->currentPage() + 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Audit Logs - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/centralized-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    <script src="{{ asset('js/table-scroll-fade.js') }}?v={{ filemtime(public_path('js/table-scroll-fade.js')) }}" defer></script>
    <script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}" defer></script>
</head>
<body class="dashboard-page audit-logs-page" data-portal="admin" data-default-filter-module="audit-logs">
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="PFIMS logo">
            <div class="brand-text">PFIMS<small>E.V. Catapang Design-Construction &amp; Supply</small></div>
        </div>
        <div class="right">
            <span class="header-clock" aria-label="Current Philippine date and time">Loading date and time…</span>
            <a href="{{ url('/notifications') }}"><img src="{{ asset('images/notif.jpg') }}" alt="" aria-hidden="true"><span class="sr-only">Open alerts</span></a>
            <a href="{{ url('/profile') }}"><img class="profile-avatar" src="{{ asset('images/user.jpg') }}" alt="" aria-hidden="true"><span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span></a>
        </div>
    </header>

    <aside class="sidebar">
        <nav aria-label="Primary navigation">
            <ul>
                @foreach($navigation as $key => $item)
                    <li class="{{ in_array($key, ['projects','finance','inventory'], true) ? 'nav-parent' : '' }}">
                        <a href="{{ in_array($key, ['projects','finance','inventory'], true) ? '#' : url($item['url']) }}" class="{{ in_array($key, ['projects','finance','inventory'], true) ? 'nav-parent-toggle' : '' }}" aria-expanded="false">
                            <img src="{{ asset('images/'.$item['icon']) }}" alt="" class="nav-link-icon" aria-hidden="true">{{ $item['label'] }}
                            @if(in_array($key, ['projects','finance','inventory'], true))<span class="nav-chevron" aria-hidden="true">▾</span>@endif
                        </a>
                        @if($key === 'projects')
                            <div class="nav-dropdown"><a href="{{ url('/projects') }}">Project Records</a><a href="{{ url('/ml-dashboard-test') }}?section=predictive">Project Cost Prediction</a></div>
                        @elseif($key === 'finance')
                            <div class="nav-dropdown"><a href="{{ url('/finance') }}">Expenses</a><a href="{{ url('/finance') }}?section=budgets">Budgets</a><a href="{{ url('/finance') }}?section=contracts">Contracts</a><a href="{{ url('/finance') }}?section=ar-ap">AR / AP</a><a href="{{ url('/finance') }}?section=cash-position">Cash Position</a><a href="{{ url('/finance') }}?section=equipment">Equipment</a><a href="{{ url('/finance') }}?section=bonds">Bonds</a><a href="{{ url('/ml-dashboard-test') }}?section=budget-comparison">Budget-Spending Comparison</a></div>
                        @elseif($key === 'inventory')
                            <div class="nav-dropdown"><a href="{{ url('/inventory') }}">Items</a><a href="{{ url('/suppliers') }}">Suppliers</a><a href="{{ url('/inventory') }}?section=transactions">Transactions</a><a href="{{ url('/ml-dashboard-test') }}?section=material-projection">Material Projection</a></div>
                        @endif
                    </li>
                @endforeach
                <li class="active"><a href="{{ route('audit-logs.index') }}"><span class="nav-link-icon pfims-audit-icon" aria-hidden="true">≡</span>AUDIT LOGS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li><a href="{{ url('/settings') }}"><img src="{{ asset('images/settings.jpg') }}" alt="" class="nav-icon" aria-hidden="true">Settings</a></li>
                <li class="logout"><form action="{{ url('/logout') }}" method="POST">@csrf<button type="submit"><img src="{{ asset('images/logout.jpg') }}" alt="" class="nav-icon" aria-hidden="true">Log out</button></form></li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <section class="dashboard-page-header audit-page-header">
            <div class="dashboard-title-block"><h1>AUDIT LOGS</h1><p>Review create, update, and delete activity across PFIMS.</p></div>
        </section>

        <form class="panel content-card filter-panel audit-log-filter-panel" method="GET" action="{{ route('audit-logs.index') }}">
            <div class="panel-heading">
                <div><h2>Filters</h2><p>Filters update the audit activity table below.</p></div>
            </div>
            <div class="filters-grid audit-log-filters" data-filter-description="Filters update the audit activity table below.">
                <label class="filter-control">Search<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, record, or details"></label>
                <label class="filter-control">From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="filter-control">To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <label class="filter-control">User<select name="user_id"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></label>
                <label class="filter-control">Role<select name="role"><option value="">All roles</option>@foreach(['ADMIN','ACCOUNTING','OPERATIONS'] as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst(strtolower($role)) }}</option>@endforeach</select></label>
                <label class="filter-control">Action<select name="action"><option value="">All actions</option>@foreach(['CREATE','UPDATE','DELETE'] as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></label>
                <label class="filter-control">Module<select name="module"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></label>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-secondary pfims-clear-filters">Clear filters</a>
                <button type="submit" class="btn btn-primary audit-apply-filters">Apply filters</button>
            </div>
        </form>

        <section class="panel content-card audit-log-panel">
            <div class="panel-heading"><div><h2>Activity history</h2><p>{{ number_format($logs->total()) }} recorded {{ Str::plural('action', $logs->total()) }}</p></div></div>
            <div class="table-wrap table-wrapper audit-log-table-wrap">
                <table>
                    <thead><tr><th>Date &amp; Time</th><th>User</th><th>User Role</th><th>Action Type</th><th>Details</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y \a\t g:i A') }}</td>
                                <td><strong>{{ $log->user_name }}</strong><small class="audit-user-email">{{ $log->user_email }}</small></td>
                                <td>{{ ucfirst(strtolower($log->user_role)) }}</td>
                                <td><span class="audit-action audit-action-{{ strtolower($log->action_type) }}">{{ $log->action_type }}</span></td>
                                <td class="audit-details-cell">{{ $log->details }}
                                    @if($log->view_available)<a class="audit-view-link" href="{{ url($log->view_url) }}">view</a>
                                    @elseif($log->action_type === 'DELETE')<span class="audit-view-unavailable">view unavailable</span>@endif
                                    @if($log->changes)<details class="audit-changes"><summary>Changed fields</summary><dl>@foreach($log->changes as $field => $change)<dt>{{ str($field)->replace('_', ' ')->headline() }}</dt><dd>{{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</dd>@endforeach</dl></details>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No audit activity matches these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrapper">
                <div class="rows-info">Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</div>
                @if($logs->hasPages())
                    <nav class="pagination-links" aria-label="Audit log pages">
                        @if($logs->onFirstPage())<button type="button" disabled>Previous</button>@else<a href="{{ $logs->previousPageUrl() }}">Previous</a>@endif
                        @if($firstPage > 1)<a href="{{ $logs->url(1) }}">1</a>@if($firstPage > 2)<span class="ellipsis">…</span>@endif @endif
                        @for($page = $firstPage; $page <= $lastPage; $page++)<a href="{{ $logs->url($page) }}" class="{{ $page === $logs->currentPage() ? 'active' : '' }}" @if($page === $logs->currentPage()) aria-current="page" @endif>{{ $page }}</a>@endfor
                        @if($lastPage < $logs->lastPage())@if($lastPage < $logs->lastPage() - 1)<span class="ellipsis">…</span>@endif<a href="{{ $logs->url($logs->lastPage()) }}">{{ $logs->lastPage() }}</a>@endif
                        @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">Next</a>@else<button type="button" disabled>Next</button>@endif
                    </nav>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
