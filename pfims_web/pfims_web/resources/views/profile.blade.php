{{-- Prepare the values used by this page section. --}}
@php $portal = $portal ?? 'admin'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="profile-page" data-portal="{{ $portal }}" data-status-message="{{ session('status', '') }}">

    <!-- Error notification (pop-up) -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">!</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Success notification (pop-up) -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Full-width header (fixed) -->
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
            <div class="brand-text">
                PFIMS
                <small>E.V. Catapang Design-Construction & Supply</small>
            </div>
        </div>
                <div class="right">
            <a href="{{ url('/notifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- Sidebar -->
<aside class="sidebar">
    {{-- Show the page navigation. --}}
    <nav>
                <ul>
            <li><a href="{{ url('/dashboard') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
            <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
            <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
            <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
            <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon">REPORTS</a></li>
        </ul>
    </nav>
    <div class="bottom-nav">
        <ul>
            <li>
                <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                    <img src="{{ asset('images/settings.png') }}" alt="Settings" class="nav-icon">
                    Settings
                </a>
            </li>
            <li class="logout">
                    {{-- Collect the details entered in this form. --}}
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.png') }}" alt="Log Out" class="nav-icon">
                            Log out
                        </button>
                    </form>
            </li>
        </ul>
    </div>
</aside>

    <!-- Main content -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>PROFILE</h1>
        </div>

        {{-- Show this section when its display check passes. --}}
        @if(session('status'))
            {{-- Set up the actions used by this page section. --}}
            <script>
                // Handle the dom content loaded event.
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess(document.body.dataset.statusMessage);
                });
            </script>
        @endif

        <!-- Profile Card -->
        <form id="profileCard" class="profile-card" action="{{ url('/profile') }}" method="POST">
            @csrf
            @method('PATCH')

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="profile-info">
                    <h2 id="displayName">{{ $user->name }}</h2>
                    {{-- Show the display role section. --}}
                    <div class="role" id="displayRole">{{ strtoupper($user->role ?? 'USER') }}</div>
                </div>
                <div class="profile-actions">
                    <button type="button" class="btn-cancel-edit" onclick="cancelEdit()">Cancel</button>
                    <button type="button" class="btn-save-profile" onclick="saveProfile()">Save Changes</button>
                    <button type="button" class="btn-edit-profile" onclick="enableEdit()">Edit Profile</button>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <div class="section-title">CONTACT INFORMATION</div>
                <div class="detail-grid">
                    <!-- Full Name -->
                    <div class="detail-item">
                        <label>Full Name</label>
                        {{-- Show the display full name section. --}}
                        <div class="value" id="displayFullName">{{ old('name', $user->name) }}</div>
                        <input type="text" name="name" class="edit-input" id="editFullName" value="{{ old('name', $user->name) }}">
                    </div>
                    <!-- Email Address -->
                    <div class="detail-item">
                        <label>Email Address</label>
                        {{-- Show the display email section. --}}
                        <div class="value" id="displayEmail">{{ old('email', $user->email) }}</div>
                        <input type="email" name="email" class="edit-input" id="editEmail" value="{{ old('email', $user->email) }}">
                    </div>
                    <!-- Phone Number -->
                    <div class="detail-item">
                        <label>Phone Number</label>
                        {{-- Show the display phone section. --}}
                        <div class="value" id="displayPhone">{{ old('phone', $user->phone) }}</div>
                        <input type="text" name="phone" class="edit-input" id="editPhone" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <!-- Location -->
                    <div class="detail-item">
                        <label>Location</label>
                        {{-- Show the display location section. --}}
                        <div class="value" id="displayLocation">{{ old('location', $user->location) }}</div>
                        <input type="text" name="location" class="edit-input" id="editLocation" value="{{ old('location', $user->location) }}">
                    </div>
                </div>
            </div>

        </form>

    </main>

    {{-- Set up the actions used by this page section. --}}
    <script>
                // Hide notification badge on click
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            // Run this step if the badge is set.
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications.
        }

        // Fetch unread notification count
        function fetchNotifBadge() {
            // Use the result when this request finishes.
            fetch('/api/notifications/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load unread count.');
                return response.json();
            })
            .then(function(data) {
                var badge = document.getElementById('notifBadge');
                // Return early if the badge is missing or false.
                if (!badge) return;
                var count = data.unread_count || 0;
                // Run this step if the count is above 0.
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(function(error) {
                console.error('Error loading notification badge:', error);
            });
        }

        document.addEventListener('DOMContentLoaded', fetchNotifBadge);

        // Error notification (pop-up)
        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            // Run this step if the error timeout is set.
            if (window.errorTimeout) clearTimeout(window.errorTimeout);
            // Run this action after the delay.
            window.errorTimeout = setTimeout(function() {
                closeError();
            }, 5000);
        }

        // Close error.
        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            // Run this step if the error timeout is set.
            if (window.errorTimeout) {
                clearTimeout(window.errorTimeout);
                window.errorTimeout = null;
            }
        }

        // Success notification (pop-up)
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'Action completed successfully!';
            }
            notif.style.display = 'block';
            // Run this step if the success timeout is set.
            if (window.successTimeout) clearTimeout(window.successTimeout);
            // Run this action after the delay.
            window.successTimeout = setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            // Run this step if the success timeout is set.
            if (window.successTimeout) {
                clearTimeout(window.successTimeout);
                window.successTimeout = null;
            }
        }

        // Handle the click event.
        document.addEventListener('click', function(e) {
            // Run this step if the style display equals block.
            if (document.getElementById('errorNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            // Run this step if the style display equals block.
            if (document.getElementById('successNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // Button loading state (prevents double-click / double-submit)
        function setButtonLoading(button, isLoading, loadingText) {
            // Return early if the button is missing or false.
            if (!button) return;
            // Run this step if the is loading is set.
            if (isLoading) {
                button.dataset.originalText = button.textContent;
                button.textContent = loadingText || 'Saving...';
                button.disabled = true;
                button.style.opacity = '0.7';
                button.style.cursor = 'not-allowed';
            } else {
                button.textContent = button.dataset.originalText || button.textContent;
                button.disabled = false;
                button.style.opacity = '';
                button.style.cursor = '';
            }
        }

        // Enable edit mode
        function enableEdit() {
            var card = document.getElementById('profileCard');
            card.classList.add('edit-mode');
        }

        // Cancel edit
        function cancelEdit() {
            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            // Reset input values back to display values
            document.getElementById('editFullName').value = document.getElementById('displayFullName').textContent;
            document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
            document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent;
            document.getElementById('editLocation').value = document.getElementById('displayLocation').textContent;
        }

        // Save profile
        function saveProfile() {
            var saveBtn = document.querySelector('.profile-actions .btn-save-profile');
            // Return early if the save btn is set and the save btn disabled is set.
            if (saveBtn && saveBtn.disabled) return;

            var fullName = document.getElementById('editFullName').value.trim();
            var email = document.getElementById('editEmail').value.trim();
            var phone = document.getElementById('editPhone').value.trim();
            var location = document.getElementById('editLocation').value.trim();

            // Run this step if the value checks match.
            if (!fullName || !email || !phone || !location) {
                showError('Please fill in all fields.');
                return;
            }

            setButtonLoading(saveBtn, true, 'Saving...');
            // Use the result when this request finishes.
            fetch(document.getElementById('profileCard').action, {
                method: 'POST', headers: {'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body: new FormData(document.getElementById('profileCard'))
            }).then(async function(response) {
                var result=await response.json();
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save profile.');
                var user=result.user;
                // Process each field.
                [['displayFullName','name'],['displayEmail','email'],['displayPhone','phone'],['displayLocation','location'],['displayName','name']].forEach(function(field) {
                    document.getElementById(field[0]).textContent=user[field[1]] || '';
                });
                document.querySelector('.top-header .right a[href*="profile"] span')?.replaceChildren(document.createTextNode(user.name));
                document.querySelector('.profile-avatar').textContent=Array.from(user.name)[0]?.toUpperCase() || 'U';
                document.getElementById('profileCard').classList.remove('edit-mode');
                showSuccess('Profile updated successfully.');
            }).catch(function(error) { showError(error.message); })
              .finally(function() { setButtonLoading(saveBtn, false); });
        }
    </script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
    @include('partials.validation-alert')
</body>
</html>
