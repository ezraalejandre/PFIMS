<div class="login-history">
    <div class="login-history-heading">
        <div>
            <div class="label">Login History</div>
            <div class="desc">Your 10 most recent successful web logins</div>
        </div>
    </div>

    @forelse ($loginHistories as $login)
        @php($loggedInAt = $login->logged_in_at->copy()->timezone('Asia/Manila'))
        <div class="login-history-row">
            <div class="login-history-device">
                <span class="login-history-icon" aria-hidden="true">{{ $login->device === 'Mobile device' ? '▯' : '▣' }}</span>
                <div>
                    <div class="login-history-name">{{ $login->device }} · {{ $login->browser }}</div>
                    <div class="login-history-meta">{{ $login->location ?: 'Location unavailable' }}</div>
                    <div class="login-history-meta">IP {{ $login->ip_address ?: 'Unavailable' }}</div>
                </div>
            </div>
            <time datetime="{{ $loggedInAt->toIso8601String() }}" title="{{ $loggedInAt->format('F j, Y g:i:s A') }} (Manila time)">
                {{ $loggedInAt->format('M j, Y') }}<br>
                <span>{{ $loggedInAt->format('g:i A') }} PHT</span>
            </time>
        </div>
    @empty
        <div class="login-history-empty">No login history is available yet. Your next successful login will appear here.</div>
    @endforelse
</div>
