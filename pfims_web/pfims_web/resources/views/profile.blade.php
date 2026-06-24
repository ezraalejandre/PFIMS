<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
</head>
<body>

    @include('partials.header')

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>PROFILE</h1>
            <div class="subtitle">account &amp; settings management</div>
        </div>

        @if(session('status'))
            <div class="status-message">{{ session('status') }}</div>
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
                        <div class="value" id="displayFullName">{{ old('name', $user->name) }}</div>
                        <input type="text" name="name" class="edit-input" id="editFullName" value="{{ old('name', $user->name) }}">
                    </div>
                    <!-- Email Address -->
                    <div class="detail-item">
                        <label>Email Address</label>
                        <div class="value" id="displayEmail">{{ old('email', $user->email) }}</div>
                        <input type="email" name="email" class="edit-input" id="editEmail" value="{{ old('email', $user->email) }}">
                    </div>
                    <!-- Phone Number -->
                    <div class="detail-item">
                        <label>Phone Number</label>
                        <div class="value" id="displayPhone">{{ old('phone', $user->phone) }}</div>
                        <input type="text" name="phone" class="edit-input" id="editPhone" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <!-- Location -->
                    <div class="detail-item">
                        <label>Location</label>
                        <div class="value" id="displayLocation">{{ old('location', $user->location) }}</div>
                        <input type="text" name="location" class="edit-input" id="editLocation" value="{{ old('location', $user->location) }}">
                    </div>
                </div>
            </div>

        </form>

    </main>

    <script>
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications.
        }

        // ─── ENABLE EDIT MODE ───
        function enableEdit() {
            var card = document.getElementById('profileCard');
            card.classList.add('edit-mode');
        }

        // ─── CANCEL EDIT ───
        function cancelEdit() {
            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            // Reset input values back to display values
            document.getElementById('editFullName').value = document.getElementById('displayFullName').textContent;
            document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
            document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent;
            document.getElementById('editLocation').value = document.getElementById('displayLocation').textContent;
        }

        // ─── SAVE PROFILE ───
        function saveProfile() {
            var fullName = document.getElementById('editFullName').value.trim();
            var email = document.getElementById('editEmail').value.trim();
            var phone = document.getElementById('editPhone').value.trim();
            var location = document.getElementById('editLocation').value.trim();

            if (!fullName || !email || !phone || !location) {
                alert('Please fill in all fields.');
                return;
            }

            // Update display values locally before submit
            document.getElementById('displayFullName').textContent = fullName;
            document.getElementById('displayEmail').textContent = email;
            document.getElementById('displayPhone').textContent = phone;
            document.getElementById('displayLocation').textContent = location;
            document.getElementById('displayName').textContent = fullName;

            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            document.getElementById('profileCard').submit();
        }
    </script>

</body>
</html>