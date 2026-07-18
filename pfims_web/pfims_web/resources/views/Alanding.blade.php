<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Login - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Alanding.css') }}">
</head>
<body>

    <!-- ─── ERROR NOTIFICATION ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">⚠</span>
            <span id="errorMessage">Invalid credentials. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- ─── FORGOT PASSWORD MODAL ─── -->
    <div id="forgotModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Reset Password</h2>
                <button class="modal-close" onclick="closeForgotModal()">×</button>
            </div>
            <div class="modal-body">
                <p style="color: #888; margin-bottom: 20px; font-size: 0.95rem;">Enter your email address and we'll send you a link to reset your password.</p>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="resetEmail" placeholder="Enter your email" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeForgotModal()">Cancel</button>
                <button class="btn-save" onclick="sendResetLink()">Send Reset Link</button>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- ─── LEFT SIDE: BRAND ─── -->
        <div class="brand">
            <img src="{{ asset('images/logo.jpg') }}" alt="E.V. Catapang Logo" class="logo">
            <h1>
                E.V. CATAPANG
                <span>DESIGN &amp; CONSTRUCTION</span>
            </h1>
            <div class="decorative-line"></div>
            <p class="subtitle">
                A centralized Inventory, Project, and Financial Management System
                for E.V. Catapang Design-Construction &amp; Supply Company
            </p>
        </div>

        <!-- ─── RIGHT SIDE: SIGN-IN CARD ─── -->
        <div class="form-wrapper">
            <h2>Accounting Login</h2>
            <p class="form-subtitle">Please enter your accounting credentials below</p>

            <form action="{{ url('/alogin') }}" method="POST" onsubmit="return validateLogin(event)">
                @csrf

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" placeholder="Enter Username" name="username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" placeholder="Enter Password" name="password" required>
                </div>

                <button type="submit" class="btn-signin">Sign In</button>
            </form>

            <!-- Forgot Password Link -->
            <div class="forgot-password">
                <a href="#" onclick="openForgotModal()">Forgot Password?</a>
            </div>

            <div class="footer-text">
                <strong>Accounting Department Access</strong>
            </div>
        </div>

    </div>

    <script>
        // ─── ERROR NOTIFICATION ───
        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            setTimeout(function() {
                closeError();
            }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // ─── FORGOT PASSWORD MODAL ───
        function openForgotModal() {
            document.getElementById('forgotModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('resetEmail').value = '';
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function sendResetLink() {
            var email = document.getElementById('resetEmail').value.trim();
            if (!email) {
                showError('Please enter your email address.');
                return;
            }
            if (!email.includes('@') || !email.includes('.')) {
                showError('Please enter a valid email address.');
                return;
            }
            closeForgotModal();
            showError('Password reset link sent to ' + email + ' (Demo)');
            console.log('Reset link sent to:', email);
        }

        // ─── FORM VALIDATION ───
        function validateLogin(event) {
            var username = document.getElementById('username').value.trim();
            var password = document.getElementById('password').value.trim();

            if (!username || !password) {
                event.preventDefault();
                showError('Please enter both username and password.');
                return false;
            }
            return true;
        }

        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotModal();
            }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) {
                    closeError();
                }
            }
        });
    </script>

</body>
</html>