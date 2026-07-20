<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">

    <style>
        .otp-links {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-link {
            background: #c9a96e;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            font-family: inherit;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-link:hover {
            background: #b8975a;
            transform: translateY(-1px);
        }
        .btn-link:disabled {
            background: #ddd;
            color: #999;
            cursor: not-allowed;
            transform: none;
        }
        .otp-divider {
            color: #ccc;
            font-size: 0.8rem;
        }

        /* ─── PASSWORD VISIBILITY TOGGLE ─── */
        .password-field {
            position: relative;
        }
        .password-field input {
            width: 100%;
            padding-right: 42px; /* room for the icon */
            box-sizing: border-box;
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 4px;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            line-height: 0;
        }
        .password-toggle:hover {
            color: #555;
        }
        .password-toggle svg {
            width: 20px;
            height: 20px;
            display: block;
        }
        .password-toggle .icon-eye-off {
            display: none;
        }
        .password-toggle.is-visible .icon-eye {
            display: none;
        }
        .password-toggle.is-visible .icon-eye-off {
            display: block;
        }
    </style>
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

                    <div class="otp-links">
                        <button type="button" class="btn-link" id="resendOtpBtn" onclick="sendResetLink(true)">Resend code</button>
                        <span class="otp-divider">·</span>
                        <button type="button" class="btn-link" onclick="goToStep(1)">Change email</button>
                    </div>
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
                        <div class="password-field">
                            <input type="password" id="newPassword" placeholder="Enter new password">
                            <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePasswordVisibility('newPassword', this)">
                                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="password-field">
                            <input type="password" id="confirmPassword" placeholder="Re-enter new password">
                            <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePasswordVisibility('confirmPassword', this)">
                                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
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
                    <div class="password-field">
                        <input type="password" name="password" id="loginPassword" placeholder="Enter Password">
                        <button type="button" class="password-toggle" aria-label="Show password" onclick="togglePasswordVisibility('loginPassword', this)">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
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

        // ─── PASSWORD VISIBILITY TOGGLE ───
        function togglePasswordVisibility(inputId, btnEl) {
            var input = document.getElementById(inputId);
            var isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            btnEl.classList.toggle('is-visible', !isVisible);
            btnEl.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        }

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

            var loadingBtnId = isResend ? 'resendOtpBtn' : 'sendOtpBtn';
            var defaultLabel = isResend ? 'Resend code' : 'Send Code';
            setButtonLoading(loadingBtnId, true, defaultLabel);

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
                setButtonLoading(loadingBtnId, false, defaultLabel);
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

        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', function() {
                showError(@json($errors->first()));
            });
        @endif
    </script>

</body>
</html>