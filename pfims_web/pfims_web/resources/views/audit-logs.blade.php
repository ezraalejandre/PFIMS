<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Audit Logs - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
</head>
<body class="audit-logs-page" data-portal="admin" data-default-filter-module="audit-logs">
    @include('partials.header')
    <main class="main-content">
        <div class="page-header">
            <div><h1>AUDIT LOGS</h1><p>Review create, update, and delete activity across PFIMS.</p></div>
        </div>

        <form class="filters-bar audit-log-filters" method="GET" action="{{ route('audit-logs.index') }}">
            <label><span>Search</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, record, or details"></label>
            <label><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label><span>User</span><select name="user_id"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></label>
            <label><span>Role</span><select name="role"><option value="">All roles</option>@foreach(['ADMIN','ACCOUNTING','OPERATIONS'] as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst(strtolower($role)) }}</option>@endforeach</select></label>
            <label><span>Action</span><select name="action"><option value="">All actions</option>@foreach(['CREATE','UPDATE','DELETE'] as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></label>
            <label><span>Module</span><select name="module"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></label>
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a href="{{ route('audit-logs.index') }}" class="btn-secondary pfims-clear-filters">Clear Filters</a>
        </form>

        <div class="table-wrapper audit-log-table-wrap">
            <table>
                <thead><tr><th>Date &amp; Time</th><th>User</th><th>User Role</th><th>Action Type</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y \a\t g:i A') }}</td>
                            <td><strong>{{ $log->user_name }}</strong><small class="audit-user-email">{{ $log->user_email }}</small></td>
                            <td>{{ ucfirst(strtolower($log->user_role)) }}</td>
                            <td><span class="audit-action audit-action-{{ strtolower($log->action_type) }}">{{ $log->action_type }}</span></td>
                            <td>{{ $log->details }}
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
        <div class="pagination-wrapper">{{ $logs->links() }}</div>
    </main>
    <script src="{{ asset('js/table-scroll-fade.js') }}?v={{ filemtime(public_path('js/table-scroll-fade.js')) }}"></script>
    <script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
</body>
</html>
