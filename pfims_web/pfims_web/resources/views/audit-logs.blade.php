@php
    $navigation = [
        'dashboard' => ['label' => 'DASHBOARD', 'icon' => 'dashboard.png', 'url' => '/dashboard'],
        'projects' => ['label' => 'PROJECTS', 'icon' => 'projects.png', 'url' => '/projects'],
        'finance' => ['label' => 'FINANCE', 'icon' => 'finance.png', 'url' => '/finance'],
        'inventory' => ['label' => 'INVENTORY', 'icon' => 'inventory.png', 'url' => '/inventory'],
        'reports' => ['label' => 'REPORTS', 'icon' => 'folder.svg', 'url' => '/reports'],
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
<body class="dashboard-page audit-logs-page" data-portal="admin" data-default-filter-module="audit-logs" data-latest-audit-id="{{ $latestAuditId }}" data-pfims-wait-for-ready="true">
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
                <li class="active"><a href="{{ route('audit-logs.index') }}"><img src="{{ asset('images/audit-log.svg') }}" alt="" class="nav-link-icon" aria-hidden="true">AUDIT LOGS</a></li>
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

        <form id="auditLogFilters" class="panel filters audit-log-filter-panel" method="GET" action="{{ route('audit-logs.index') }}" aria-label="Audit log filters">
            <label>Search<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, record, or details"></label>
            <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label>User<select name="user_id"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></label>
            <label>Role<select name="role"><option value="">All roles</option>@foreach(['ADMIN','ACCOUNTING','OPERATIONS'] as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst(strtolower($role)) }}</option>@endforeach</select></label>
            <label>Action<select name="action"><option value="">All actions</option>@foreach(['CREATE','UPDATE','DELETE'] as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></label>
            <label>Module<select name="module"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></label>
            <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}">
            <a href="{{ route('audit-logs.index') }}" class="btn-secondary pfims-clear-filters">Clear filters</a>
        </form>

        <section class="panel content-card audit-log-panel">
            <div class="panel-heading"><div><h2>Activity history</h2><p>{{ number_format($logs->total()) }} recorded {{ Str::plural('action', $logs->total()) }}</p></div></div>
            <div class="table-wrap table-wrapper audit-log-table-wrap">
                <table>
                    <thead><tr><th>Date &amp; Time</th><th>User</th><th>User Role</th><th>Action Type</th><th>Details</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y \a\t g:i A') }}</td>
                                <td><strong>{{ $log->user_name }}</strong><small class="audit-user-email">{{ $log->user_email }}</small></td>
                                <td>{{ ucfirst(strtolower($log->user_role)) }}</td>
                                <td><span class="audit-action audit-action-{{ strtolower($log->action_type) }}">{{ $log->action_type }}</span></td>
                                <td class="audit-details-cell">{{ $log->details }}
                                    @if($log->changes)<details class="audit-changes"><summary>Changed fields</summary><dl>@foreach($log->changes as $field => $change)<dt>{{ str($field)->replace('_', ' ')->headline() }}</dt><dd>{{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</dd>@endforeach</dl></details>@endif
                                </td>
                                <td class="action-cell">
                                    @if($log->view_available)
                                        <a class="pfims-row-action" href="{{ url($log->view_url) }}" title="View record" aria-label="View {{ $log->record_label ?: 'audit record' }}"><img src="{{ asset('images/view.jpg') }}" alt=""></a>
                                    @else
                                        <span class="audit-view-unavailable" aria-label="View unavailable">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">No audit activity matches these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrapper">
                <div class="rows-info">
                    <label for="auditRowsPerPage">Rows per page</label>
                    <select id="auditRowsPerPage" aria-label="Audit log rows per page" data-pfims-page-size="ready">
                        @foreach([10, 20, 50, 100] as $pageSize)
                            <option value="{{ $pageSize }}" @selected((int) request('per_page', 20) === $pageSize)>{{ $pageSize }}</option>
                        @endforeach
                    </select>
                    <span>Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</span>
                </div>
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
    <script>
        (function () {
            var form = document.getElementById('auditLogFilters');
            var select = document.getElementById('auditRowsPerPage');
            var submitTimer = null;

            function normalizedParams(params) {
                var normalized = new URLSearchParams(params);
                normalized.delete('page');
                Array.from(normalized.keys()).forEach(function (key) {
                    if (normalized.get(key) === '') normalized.delete(key);
                });
                normalized.sort();
                return normalized.toString();
            }

            function applyFilters() {
                window.clearTimeout(submitTimer);
                var requested = normalizedParams(new URLSearchParams(new FormData(form)));
                var current = normalizedParams(new URLSearchParams(window.location.search));
                if (requested === current) return false;
                form.requestSubmit();
                return true;
            }

            form.querySelectorAll('select, input[type="date"]').forEach(function (control) {
                control.addEventListener('change', applyFilters);
            });
            form.querySelector('input[type="search"]').addEventListener('input', function () {
                window.clearTimeout(submitTimer);
                submitTimer = window.setTimeout(applyFilters, 450);
            });

            select.addEventListener('change', function () {
                var params = new URLSearchParams(window.location.search);
                params.set('per_page', select.value);
                params.set('page', '1');
                window.location.assign(window.location.pathname + '?' + params.toString());
            });

            function releaseInitialLoader() {
                var state = document.body.dataset.pfimsDefaultFilters;
                if (state === 'loading' || !state) {
                    window.setTimeout(releaseInitialLoader, 40);
                    return;
                }
                if (applyFilters()) return;
                document.documentElement.dataset.pfimsPageReady = 'true';
                document.dispatchEvent(new CustomEvent('pfims:page-ready'));
            }
            window.addEventListener('load', releaseInitialLoader, { once: true });

            var latestId = Number(document.body.dataset.latestAuditId || 0);
            window.setInterval(function () {
                if (document.hidden) return;
                fetch('{{ route('audit-logs.latest') }}', {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Audit freshness check failed');
                        return response.json();
                    })
                    .then(function (payload) {
                        if (Number(payload.latest_id || 0) > latestId) window.location.reload();
                    })
                    .catch(function () { /* Keep the current page stable if the check is unavailable. */ });
            }, 15000);
        })();
    </script>
</body>
</html>
