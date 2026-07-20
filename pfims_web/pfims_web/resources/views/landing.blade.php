<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>

    <!-- ─── ERROR / SUCCESS NOTIFICATION ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon" id="errorIcon">⚠</span>
            <span id="errorMessage">Invalid credentials. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- ─── FORGOT PASSWORD MODAL ─── -->
    <div id="forgotModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">

            <!-- STEP 1: EMAIL -->
            <div id="step1" class="modal-step">
                <div class="modal-header">
                    <h2>Reset Password</h2>
                    <button class="modal-close" onclick="closeForgotModal()">×</button>
                </div>
                <div class="modal-body">
                    <p style="color: #888; margin-bottom: 20px; font-size: 0.95rem;">Enter your email address and we'll send you a 6-digit code.</p>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="resetEmail" placeholder="Enter your email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeForgotModal()">Cancel</button>
                    <button class="btn-save" id="sendOtpBtn" onclick="sendResetLink()">Send Code</button>
                </div>
            </div>

            <!-- STEP 2: OTP -->
            <div id="step2" class="modal-step" style="display:none;">
                <div class="modal-header">
                    <h2>Enter Code</h2>
                    <button class="modal-close" onclick="closeForgotModal()">×</button>
                </div>
                <div class="modal-body">
                    <p style="color: #888; margin-bottom: 20px; font-size: 0.95rem;">
                        We sent a 6-digit code to <strong id="otpEmailDisplay"></strong>. Enter it below.
                    </p>
                    <div class="form-group">
                        <label>6-Digit Code</label>
                        <input type="text" id="otpInput" maxlength="6" inputmode="numeric" placeholder="000000" style="letter-spacing:6px; text-align:center; font-size:1.2rem;">
                    </div>
                    <p style="margin-top:12px;">
                        <a href="#" onclick="event.preventDefault(); sendResetLink(true);" style="font-size:0.85rem;">Resend code</a>
                        &nbsp;·&nbsp;
                        <a href="#" onclick="event.preventDefault(); goToStep(1);" style="font-size:0.85rem;">Change email</a>
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeForgotModal()">Cancel</button>
                    <button class="btn-save" id="verifyOtpBtn" onclick="verifyOtp()">Verify Code</button>
                </div>
            </div>

            <!-- STEP 3: NEW PASSWORD -->
            <div id="step3" class="modal-step" style="display:none;">
                <div class="modal-header">
                    <h2>Set New Password</h2>
                    <button class="modal-close" onclick="closeForgotModal()">×</button>
                </div>
                <div class="modal-body">
                    <p style="color: #888; margin-bottom: 20px; font-size: 0.95rem;">Code verified. Choose your new password.</p>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" id="newPassword" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="confirmPassword" placeholder="Re-enter new password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeForgotModal()">Cancel</button>
                    <button class="btn-save" id="resetPasswordBtn" onclick="submitNewPassword()">Reset Password</button>
                </div>
            </div>

            <!-- STEP 4: DONE -->
            <div id="step4" class="modal-step" style="display:none;">
                <div class="modal-header">
                    <h2>Password Reset</h2>
                    <button class="modal-close" onclick="closeForgotModal()">×</button>
                </div>
                <div class="modal-body">
                    <p style="color: #333; font-size: 0.95rem;">Your password has been reset successfully. You can now sign in with your new password.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-save" onclick="closeForgotModal()">Back to Sign In</button>
                </div>
            </div>

        </div>
    </div>

    <div class="container">

        <!-- ─── LEFT SIDE: BRAND (Transparent, text on background) ─── -->
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

        <!-- ─── RIGHT SIDE: FLOATING SIGN-IN CARD ─── -->
        <div class="form-wrapper">
            <h2>Sign In</h2>
            <p class="form-subtitle">Please enter your credentials below</p>

            <form action="/login" method="POST">
                @csrf

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Enter email">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter Password">
                </div>

                <button type="submit" class="btn-signin">Sign In</button>
            </form>

            <!-- Forgot Password Link -->
            <div class="forgot-password">
                <a href="#" onclick="openForgotModal()">Forgot Password?</a>
            </div>

        </div>

    </div>

    <script>
        const CSRF_TOKEN = '{{ csrf_token() }}';

        // Holds state across the 3 steps
        let resetState = {
            email: '',
            resetToken: ''
        };

        // ─── NOTIFICATION ───
        function showError(message, isSuccess) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            var icon = document.getElementById('errorIcon');
            if (msgSpan) msgSpan.textContent = message || 'An error occurred. Please try again.';
            if (icon) icon.textContent = isSuccess ? '✓' : '⚠';
            notif.classList.toggle('success', !!isSuccess);
            notif.style.display = 'block';
            setTimeout(function() { closeError(); }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // ─── STEP NAVIGATION ───
        function goToStep(stepNumber) {
            for (let i = 1; i <= 4; i++) {
                document.getElementById('step' + i).style.display = (i === stepNumber) ? 'block' : 'none';
            }
        }

        // ─── MODAL OPEN/CLOSE ───
        function openForgotModal() {
            document.getElementById('forgotModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('resetEmail').value = '';
            document.getElementById('otpInput').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            resetState = { email: '', resetToken: '' };
            goToStep(1);
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function setButtonLoading(btnId, loading, defaultText) {
            var btn = document.getElementById(btnId);
            btn.disabled = loading;
            btn.textContent = loading ? 'Please wait...' : defaultText;
        }

        // ─── STEP 1: SEND OTP ───
        async function sendResetLink(isResend) {
            var email = isResend ? resetState.email : document.getElementById('resetEmail').value.trim();

            if (!email) {
                showError('Please enter your email address.');
                return;
            }
            if (!email.includes('@') || !email.includes('.')) {
                showError('Please enter a valid email address.');
                return;
            }

            setButtonLoading('sendOtpBtn', true, 'Send Code');

            try {
                const res = await fetch("{{ route('password.send-otp') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({ email: email })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showError(data.message || 'Something went wrong. Please try again.');
                    return;
                }

                resetState.email = email;
                document.getElementById('otpEmailDisplay').textContent = email;
                document.getElementById('otpInput').value = '';
                showError(data.message, true);
                goToStep(2);

            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                setButtonLoading('sendOtpBtn', false, 'Send Code');
            }
        }

        // ─── STEP 2: VERIFY OTP ───
        async function verifyOtp() {
            var otp = document.getElementById('otpInput').value.trim();

            if (!/^\d{6}$/.test(otp)) {
                showError('Please enter the 6-digit code.');
                return;
            }

            setButtonLoading('verifyOtpBtn', true, 'Verify Code');

            try {
                const res = await fetch("{{ route('password.verify-otp') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({ email: resetState.email, otp: otp })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showError(data.message || 'Invalid or expired code.');
                    return;
                }

                resetState.resetToken = data.reset_token;
                showError(data.message, true);
                goToStep(3);

            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                setButtonLoading('verifyOtpBtn', false, 'Verify Code');
            }
        }

        // ─── STEP 3: SET NEW PASSWORD ───
        async function submitNewPassword() {
            var password = document.getElementById('newPassword').value;
            var confirm = document.getElementById('confirmPassword').value;

            if (!password || password.length < 8) {
                showError('Password must be at least 8 characters.');
                return;
            }
            if (password !== confirm) {
                showError('Passwords do not match.');
                return;
            }

            setButtonLoading('resetPasswordBtn', true, 'Reset Password');

            try {
                const res = await fetch("{{ route('password.reset') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        email: resetState.email,
                        reset_token: resetState.resetToken,
                        password: password,
                        password_confirmation: confirm
                    })
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    showError(data.message || 'Could not reset password. Please try again.');
                    return;
                }

                goToStep(4);

            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                setButtonLoading('resetPasswordBtn', false, 'Reset Password');
            }
        }

        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if (e.target === this) closeForgotModal();
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) closeError();
            }
        });
    </script>

</body>
</html>