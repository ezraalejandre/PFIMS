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

        /* ─── TOAST NOTIFICATION (base look + success state) ─── */
        .error-notification {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .error-notification .error-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #errorNotification.error-notification.success,
        #errorNotification.success {
            background: #eafaf0 !important;
            border: 1px solid #9fdcb6 !important;
            color: #1e6b41 !important;
        }
        #errorNotification.success .error-icon {
            color: #1e8449 !important;
            font-weight: 700;
        }
        #errorNotification.success #errorMessage {
            color: #1e6b41 !important;
        }
        #errorNotification.success .error-close {
            color: #1e6b41 !important;
        }

        /* ─── SIGN-IN FAILURE ALERT ─── */
        .login-alert {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 14px 16px;
            margin-bottom: 20px;
            color: #7a1f1f;
            background: linear-gradient(180deg, #fff5f5 0%, #fff0f0 100%);
            border: 1px solid #f2b9b9;
            border-left: 4px solid #d64545;
            border-radius: 10px;
            font-size: 0.9rem;
            line-height: 1.45;
            box-shadow: 0 2px 8px rgba(214, 69, 69, 0.08);
        }

        .login-alert .alert-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #d64545;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 1px;
        }

        .login-alert strong {
            display: block;
            margin-bottom: 3px;
            color: #5c1414;
            font-size: 0.95rem;
        }

        .login-alert[hidden] {
            display: none;
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
        /* Hide the browser-provided reveal control; the app supplies its own. */
        .password-field input::-ms-reveal,
        .password-field input::-ms-clear {
            display: none;
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
            min-width: 30px;
            min-height: 30px;
            border-radius: 999px;
        }
        .password-toggle:hover {
            color: #555;
            background: #f6efe4;
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
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="landing-page">

    <!-- ─── ERROR / SUCCESS NOTIFICATION ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon" id="errorIcon">⚠</span>
            <span id="errorMessage">We couldn't sign you in — please check your details and try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>
    <div id="serverLoginError" data-message="{{ $errors->first() }}" hidden></div>

    <!-- ─── FORGOT PASSWORD MODAL ─── -->
    <div id="firstLoginVerificationModal" class="modal-overlay" style="display:none;">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Verify Your Email</h2>
            </div>
            <div class="modal-body">
                <p style="color:#888; margin-bottom:20px; font-size:.95rem;">
                    We sent a 6-digit code to <strong id="firstLoginEmailDisplay"></strong>. Enter it to complete your first login.
                </p>
                <div class="form-group">
                    <label for="firstLoginOtpInput">6-Digit Code</label>
                    <input type="text" id="firstLoginOtpInput" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" style="letter-spacing:6px; text-align:center; font-size:1.2rem;">
                </div>
                <div id="firstLoginOtpError" class="login-alert" role="alert" hidden>
                    <span class="alert-icon">!</span>
                    <div><span id="firstLoginOtpErrorMessage"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="resendFirstLoginBtn" onclick="resendFirstLoginCode()" disabled>Resend code (60s)</button>
                <button type="button" class="btn-save" id="verifyFirstLoginBtn" onclick="verifyFirstLoginCode()">Verify Code</button>
            </div>
        </div>
    </div>

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

            @if ($errors->any())
                <div class="login-alert" role="alert">
                    <span class="alert-icon">!</span>
                    <div>
                        <strong>We couldn't sign you in</strong>
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif
            <div id="loginInlineAlert" class="login-alert" role="alert" hidden>
                <span class="alert-icon">!</span>
                <div>
                    <strong>We couldn't sign you in</strong>
                    <span id="loginInlineMessage">That email or password doesn't look right. Please try again.</span>
                </div>
            </div>

            <form action="/login" method="POST" id="loginForm">
                @csrf

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Enter email" autocomplete="email">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-field">
                        <input type="password" name="password" id="loginPassword" placeholder="Enter Password" autocomplete="current-password">
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

        var firstLoginResendTimer = null;

        function startFirstLoginResendCountdown(seconds) {
            var button = document.getElementById('resendFirstLoginBtn');
            var remaining = Math.max(0, Number(seconds) || 60);
            if (firstLoginResendTimer) clearInterval(firstLoginResendTimer);

            function updateButton() {
                if (remaining > 0) {
                    button.disabled = true;
                    button.textContent = 'Resend code (' + remaining + 's)';
                    remaining--;
                } else {
                    button.disabled = false;
                    button.textContent = 'Resend code';
                    clearInterval(firstLoginResendTimer);
                    firstLoginResendTimer = null;
                }
            }

            updateButton();
            firstLoginResendTimer = setInterval(updateButton, 1000);
        }

        function openFirstLoginVerification(email) {
            document.getElementById('firstLoginEmailDisplay').textContent = email || '';
            document.getElementById('firstLoginOtpInput').value = '';
            document.getElementById('firstLoginOtpError').hidden = true;
            document.getElementById('firstLoginVerificationModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            startFirstLoginResendCountdown(60);
            setTimeout(function () { document.getElementById('firstLoginOtpInput').focus(); }, 0);
        }

        async function resendFirstLoginCode() {
            var button = document.getElementById('resendFirstLoginBtn');
            button.disabled = true;
            button.textContent = 'Sending...';
            document.getElementById('firstLoginOtpError').hidden = true;

            try {
                var res = await fetch("{{ route('login.resend-first-login') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({})
                });
                var data = await res.json();

                if (!res.ok || !data.success) {
                    showFirstLoginOtpError(data.message || 'Unable to resend the code.');
                    startFirstLoginResendCountdown(data.retry_after || 60);
                    return;
                }

                document.getElementById('firstLoginOtpInput').value = '';
                showError(data.message, true);
                startFirstLoginResendCountdown(data.retry_after || 60);
            } catch (err) {
                showFirstLoginOtpError('Unable to resend the code right now. Please try again.');
                startFirstLoginResendCountdown(60);
            }
        }

        function showFirstLoginOtpError(message) {
            document.getElementById('firstLoginOtpErrorMessage').textContent = message;
            document.getElementById('firstLoginOtpError').hidden = false;
        }

        async function verifyFirstLoginCode() {
            var otp = document.getElementById('firstLoginOtpInput').value.trim();
            var button = document.getElementById('verifyFirstLoginBtn');
            document.getElementById('firstLoginOtpError').hidden = true;

            if (!/^\d{6}$/.test(otp)) {
                showFirstLoginOtpError('Please enter the complete 6-digit code.');
                return;
            }

            button.disabled = true;
            button.textContent = 'Verifying...';

            try {
                var res = await fetch("{{ route('login.verify-first-login') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ otp: otp })
                });
                var data = await res.json();

                if (res.ok && data.success && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                showFirstLoginOtpError(firstValidationMessage(data.errors, data.message || 'Unable to verify the code.'));
            } catch (err) {
                showFirstLoginOtpError('Unable to verify the code right now. Please try again.');
            } finally {
                button.disabled = false;
                button.textContent = 'Verify Code';
            }
        }

        // ─── PASSWORD VISIBILITY TOGGLE ───
        function togglePasswordVisibility(inputId, btnEl) {
            if (window.event) window.event.preventDefault();
            var input = document.getElementById(inputId);
            if (!input || !btnEl) return;
            var isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            btnEl.classList.toggle('is-visible', !isVisible);
            btnEl.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            input.focus({ preventScroll: true });
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

        function showLoginInlineError(message) {
            var alert = document.getElementById('loginInlineAlert');
            var messageEl = document.getElementById('loginInlineMessage');
            if (messageEl) messageEl.textContent = message || "That email or password doesn't look right. Please try again.";
            if (alert) alert.hidden = false;
        }

        function hideLoginInlineError() {
            var alert = document.getElementById('loginInlineAlert');
            if (alert) alert.hidden = true;
        }

        function firstValidationMessage(errors, fallback) {
            if (!errors) return fallback;
            for (var key in errors) {
                if (Object.prototype.hasOwnProperty.call(errors, key) && errors[key] && errors[key][0]) {
                    return errors[key][0];
                }
            }
            return fallback;
        }

        async function submitLogin(event) {
            event.preventDefault();
            hideLoginInlineError();

            var form = event.currentTarget;
            var submitBtn = form.querySelector('.btn-signin');
            var defaultText = submitBtn ? submitBtn.textContent : 'Sign In';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
            }

            try {
                var res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new FormData(form)
                });
                var data = await res.json();

                if (res.ok && data.success && data.requires_first_login_verification) {
                    openFirstLoginVerification(data.masked_email);
                    return;
                }

                if (res.ok && data.success && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                showLoginInlineError(firstValidationMessage(data.errors, data.message || "That email or password doesn't look right. Please try again."));
            } catch (err) {
                showLoginInlineError('Unable to sign in right now. Please try again.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = defaultText;
                }
            }
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
                showError(data.message || 'Code sent! Check your inbox.', true);
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
                showError(data.message || 'Code verified!', true);
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

        document.getElementById('loginForm').addEventListener('submit', submitLogin);
        document.getElementById('firstLoginOtpInput').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                verifyFirstLoginCode();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            var serverLoginError = document.getElementById('serverLoginError');
            var serverLoginErrorMessage = serverLoginError ? serverLoginError.dataset.message : '';
            if (serverLoginErrorMessage) showError(serverLoginErrorMessage);
        });
    </script>

</body>
</html>
