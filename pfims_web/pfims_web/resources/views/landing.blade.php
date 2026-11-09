<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pfims-alert.css') }}?v={{ filemtime(public_path('css/pfims-alert.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}" defer></script>

    {{-- Set the styles used by this page section. --}}
    <style>
        /* Style the otp links. */
        .otp-links {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        /* Style the btn link. */
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
        /* Style the btn link on hover. */
        .btn-link:hover {
            background: #b8975a;
            transform: translateY(-1px);
        }
        /* Style the btn link. */
        .btn-link:disabled {
            background: #ddd;
            color: #999;
            cursor: not-allowed;
            transform: none;
        }
        /* Style the otp divider. */
        .otp-divider {
            color: #ccc;
            font-size: 0.8rem;
        }

        /* Toast notification (base look + success state) */
        .error-notification {
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        /* Style the error notification error content. */
        .error-notification .error-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* Style the error notification success. */
        #errorNotification.error-notification.success,
        #errorNotification.success {
            background: #eafaf0 !important;
            border: 1px solid #9fdcb6 !important;
            color: #1e6b41 !important;
        }
        /* Style the success error icon. */
        #errorNotification.success .error-icon {
            color: #1e8449 !important;
            font-weight: 700;
        }
        /* Style the success error message. */
        #errorNotification.success #errorMessage {
            color: #1e6b41 !important;
        }
        /* Style the success error close. */
        #errorNotification.success .error-close {
            color: #1e6b41 !important;
        }

        /* Sign-in failure alert */
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

        /* Style the login alert alert icon. */
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

        /* Style the login alert. */
        .login-alert strong {
            display: block;
            margin-bottom: 3px;
            color: #5c1414;
            font-size: 0.95rem;
        }

        /* Style the login alert. */
        .login-alert[hidden] {
            display: none;
        }

        /* Password visibility toggle */
        .password-field {
            position: relative;
        }
        /* Style the password field. */
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
        /* Style the password toggle. */
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
        /* Style the password toggle on hover. */
        .password-toggle:hover {
            color: #555;
            background: #f6efe4;
        }
        /* Style the password toggle. */
        .password-toggle svg {
            width: 20px;
            height: 20px;
            display: block;
        }
        /* Style the password toggle icon eye off. */
        .password-toggle .icon-eye-off {
            display: none;
        }
        /* Style the is visible icon eye. */
        .password-toggle.is-visible .icon-eye {
            display: none;
        }
        /* Style the is visible icon eye off. */
        .password-toggle.is-visible .icon-eye-off {
            display: block;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
</head>
<body class="landing-page" data-inactivity-timeout-seconds="{{ session()->pull('inactivity_timeout_seconds', 0) }}">

    <!-- Error / success notification -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon" id="errorIcon">!</span>
            <span id="errorMessage">We couldn't sign you in — please check your details and try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>
    {{-- Show the server login error section. --}}
    <div id="serverLoginError" data-message="{{ $errors->first() }}" hidden></div>

    <!-- Forgot password modal -->
    <div id="firstLoginVerificationModal" class="modal-overlay" style="display:none;">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Verify Your Email</h2>
                <button type="button" class="modal-close" aria-label="Cancel email verification" onclick="closeFirstLoginVerification()">×</button>
            </div>
            <div class="modal-body">
                <p style="color:#888; margin-bottom:20px; font-size:.95rem;">
                    We sent a 6-digit code to <strong id="firstLoginEmailDisplay"></strong>. Enter it to complete your first login.
                </p>
                <div class="form-group">
                    <label for="firstLoginOtpInput">6-Digit Code</label>
                    <input type="text" id="firstLoginOtpInput" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" style="letter-spacing:6px; text-align:center; font-size:1.2rem;">
                </div>
                {{-- Show the first login otp error section. --}}
                <div id="firstLoginOtpError" class="login-alert" role="alert" hidden>
                    <span class="alert-icon">!</span>
                    {{-- Show the first login otp error message section. --}}
                    <div><span id="firstLoginOtpErrorMessage"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="resendFirstLoginBtn" onclick="resendFirstLoginCode()" disabled>Resend code (60s)</button>
                <button type="button" class="btn-save" id="verifyFirstLoginBtn" onclick="verifyFirstLoginCode()">Verify Code</button>
            </div>
        </div>
    </div>

    {{-- Show the forgot modal section. --}}
    <div id="forgotModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            {{-- Show the reset inline alert section. --}}
            <div id="resetInlineAlert" class="login-alert" role="alert" hidden></div>

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

        <!-- Left side: brand (transparent, text on background) -->
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

        <!-- Right side: floating sign-in card -->
        <div class="form-wrapper">
            <h2>Sign In</h2>
            <p class="form-subtitle">Please enter your credentials below</p>

            {{-- Show this section when its display check passes. --}}
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    <span class="alert-icon">!</span>
                    <div>
                        <strong>We couldn't sign you in</strong>
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif
            {{-- Show the login inline alert section. --}}
            <div id="loginInlineAlert" class="login-alert" role="alert" hidden>
                <span class="alert-icon">!</span>
                <div>
                    <strong>We couldn't sign you in</strong>
                    <span id="loginInlineMessage">That email or password doesn't look right. Please try again.</span>
                </div>
            </div>

            {{-- Collect the details entered in this form. --}}
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

    {{-- Set up the actions used by this page section. --}}
    <script>
        const CSRF_TOKEN = '{{ csrf_token() }}';

        // Holds state across the 3 steps
        let resetState = {
            email: '',
            resetToken: ''
        };

        var firstLoginResendTimer = null;

        // Start first login resend countdown.
        function startFirstLoginResendCountdown(seconds) {
            var button = document.getElementById('resendFirstLoginBtn');
            var remaining = Math.max(0, Number(seconds) || 60);
            // Run this step if the first login resend timer is set.
            if (firstLoginResendTimer) clearInterval(firstLoginResendTimer);

            // Update button.
            function updateButton() {
                // Run this step if the remaining is above 0.
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

        // Open first login verification.
        function openFirstLoginVerification(email) {
            document.getElementById('firstLoginEmailDisplay').textContent = email || '';
            document.getElementById('firstLoginOtpInput').value = '';
            document.getElementById('firstLoginOtpError').hidden = true;
            document.getElementById('firstLoginVerificationModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            startFirstLoginResendCountdown(60);
            // Run this action after the delay.
            setTimeout(function () { document.getElementById('firstLoginOtpInput').focus(); }, 0);
        }

        // Get the resend first login code.
        async function resendFirstLoginCode() {
            var button = document.getElementById('resendFirstLoginBtn');
            button.disabled = true;
            button.textContent = 'Sending...';
            document.getElementById('firstLoginOtpError').hidden = true;

            // Try this action and handle any errors below.
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

                // Run this step if the res ok is missing or false or the success is missing or false.
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

        // Show first login otp error.
        function showFirstLoginOtpError(message) {
            document.getElementById('firstLoginOtpErrorMessage').textContent = message;
            document.getElementById('firstLoginOtpError').hidden = false;
        }

        // Get the verify first login code.
        async function verifyFirstLoginCode() {
            var otp = document.getElementById('firstLoginOtpInput').value.trim();
            var button = document.getElementById('verifyFirstLoginBtn');
            document.getElementById('firstLoginOtpError').hidden = true;

            // Run this step if the test is missing or false.
            if (!/^\d{6}$/.test(otp)) {
                showFirstLoginOtpError('Please enter the complete 6-digit code.');
                return;
            }

            button.disabled = true;
            button.textContent = 'Verifying...';

            // Try this action and handle any errors below.
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

                // Run this step if the res ok is set and the success is set and the redirect is set.
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

        // Password visibility toggle
        function togglePasswordVisibility(inputId, btnEl) {
            // Run this step if the event is set.
            if (window.event) window.event.preventDefault();
            var input = document.getElementById(inputId);
            // Return early if the input is missing or false or the btn el is missing or false.
            if (!input || !btnEl) return;
            var isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            btnEl.classList.toggle('is-visible', !isVisible);
            btnEl.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            input.focus({ preventScroll: true });
        }

        // Notification
        function showError(message, isSuccess) {
            var resetAlert = document.getElementById('resetInlineAlert');
            // Run this step if the reset alert is set and the style display does not equal none.
            if (resetAlert && document.getElementById('forgotModal').style.display !== 'none') {
                resetAlert.textContent = message || 'Please try again.';
                resetAlert.hidden = false;
            }
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            var icon = document.getElementById('errorIcon');
            // Run this step if the msg span is set.
            if (msgSpan) msgSpan.textContent = message || 'An error occurred. Please try again.';
            // Run this step if the icon is set.
            if (icon) icon.textContent = isSuccess ? '✓' : '!';
            notif.classList.toggle('success', !!isSuccess);
            notif.style.display = 'block';
            // Run this action after the delay.
            setTimeout(function() { closeError(); }, 5000);
        }

        // Close error.
        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // Close first login verification.
        function closeFirstLoginVerification() {
            document.getElementById('firstLoginVerificationModal').style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('firstLoginOtpInput').value = '';
        }

        // Show login inline error.
        function showLoginInlineError(message) {
            var alert = document.getElementById('loginInlineAlert');
            var messageEl = document.getElementById('loginInlineMessage');
            // Run this step if the message el is set.
            if (messageEl) messageEl.textContent = message || "That email or password doesn't look right. Please try again.";
            // Run this step if the alert is set.
            if (alert) alert.hidden = false;
        }

        // Hide login inline error.
        function hideLoginInlineError() {
            var alert = document.getElementById('loginInlineAlert');
            // Run this step if the alert is set.
            if (alert) alert.hidden = true;
        }

        // Get the first validation message.
        function firstValidationMessage(errors, fallback) {
            // Return early if the errors is missing or false.
            if (!errors) return fallback;
            // Process each item in errors.
            for (var key in errors) {
                // Return early if the call check matches and the key is set and the 0 is set.
                if (Object.prototype.hasOwnProperty.call(errors, key) && errors[key] && errors[key][0]) {
                    return errors[key][0];
                }
            }
            return fallback;
        }

        // Submit login.
        async function submitLogin(event) {
            event.preventDefault();
            hideLoginInlineError();

            var form = event.currentTarget;
            var submitBtn = form.querySelector('.btn-signin');
            var defaultText = submitBtn ? submitBtn.textContent : 'Sign In';
            // Run this step if the submit btn is set.
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
            }

            // Try this action and handle any errors below.
            try {
                var res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new FormData(form)
                });
                // Run this step if the res status equals 419.
                if (res.status === 419) {
                    showLoginInlineError('This sign-in page has been open too long. Refresh the page, then try signing in again.');
                    return;
                }
                var data = await res.json();

                // Run this step if the res ok is set and the success is set and the requires first login verification is set.
                if (res.ok && data.success && data.requires_first_login_verification) {
                    openFirstLoginVerification(data.masked_email);
                    return;
                }

                // Run this step if the res ok is set and the success is set and the redirect is set.
                if (res.ok && data.success && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                showLoginInlineError(firstValidationMessage(data.errors, data.message || "That email or password doesn't look right. Please try again."));
            } catch (err) {
                showLoginInlineError('Unable to sign in right now. Please try again.');
            } finally {
                // Run this step if the submit btn is set.
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = defaultText;
                }
            }
        }

        // Step navigation
        function goToStep(stepNumber) {
            document.getElementById('resetInlineAlert').hidden = true;
            // Repeat for each step in this range.
            for (let i = 1; i <= 4; i++) {
                document.getElementById('step' + i).style.display = (i === stepNumber) ? 'block' : 'none';
            }
        }

        // Modal open/close
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

        // Close forgot modal.
        function closeForgotModal() {
            document.getElementById('forgotModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Set button loading.
        function setButtonLoading(btnId, loading, defaultText) {
            var btn = document.getElementById(btnId);
            btn.disabled = loading;
            btn.textContent = loading ? 'Please wait...' : defaultText;
        }

        // Step 1: send otp
        async function sendResetLink(isResend) {
            var email = isResend ? resetState.email : document.getElementById('resetEmail').value.trim();

            // Run this step if the email is missing or false.
            if (!email) {
                showError('Please enter your email address.');
                return;
            }
            // Run this step if the includes is missing or false or the includes is missing or false.
            if (!email.includes('@') || !email.includes('.')) {
                showError('Please enter a valid email address.');
                return;
            }

            var loadingBtnId = isResend ? 'resendOtpBtn' : 'sendOtpBtn';
            var defaultLabel = isResend ? 'Resend code' : 'Send Code';
            setButtonLoading(loadingBtnId, true, defaultLabel);

            // Try this action and handle any errors below.
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

                // Run this step if the res ok is missing or false or the success is missing or false.
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

        // Step 2: verify otp
        async function verifyOtp() {
            var otp = document.getElementById('otpInput').value.trim();

            // Run this step if the test is missing or false.
            if (!/^\d{6}$/.test(otp)) {
                showError('Please enter the 6-digit code.');
                return;
            }

            setButtonLoading('verifyOtpBtn', true, 'Verify Code');

            // Try this action and handle any errors below.
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

                // Run this step if the res ok is missing or false or the success is missing or false.
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

        // Step 3: set new password
        async function submitNewPassword() {
            var password = document.getElementById('newPassword').value;
            var confirm = document.getElementById('confirmPassword').value;

            // Run this step if the password is missing or false or the password length is below 8.
            if (!password || password.length < 8) {
                showError('Password must be at least 8 characters.');
                return;
            }
            // Run this step if the password does not equal the confirm.
            if (password !== confirm) {
                showError('Passwords do not match.');
                return;
            }

            setButtonLoading('resetPasswordBtn', true, 'Reset Password');

            // Try this action and handle any errors below.
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

                // Run this step if the res ok is missing or false or the success is missing or false.
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

        // Handle the click event.
        document.getElementById('forgotModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) closeForgotModal();
        });

        // Handle the click event.
        document.addEventListener('click', function(e) {
            // Run this step if the style display equals block.
            if (document.getElementById('errorNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.error-notification')) closeError();
            }
        });

        document.getElementById('loginForm').addEventListener('submit', submitLogin);
        // Handle the keydown event.
        document.getElementById('firstLoginOtpInput').addEventListener('keydown', function(event) {
            // Run this step if the key equals Enter.
            if (event.key === 'Enter') {
                event.preventDefault();
                verifyFirstLoginCode();
            }
        });

        // Handle the dom content loaded event.
        document.addEventListener('DOMContentLoaded', function() {
            var serverLoginError = document.getElementById('serverLoginError');
            var serverLoginErrorMessage = serverLoginError ? serverLoginError.dataset.message : '';
            // Run this step if the server login error message is set.
            if (serverLoginErrorMessage) showError(serverLoginErrorMessage);
        });
    </script>

    {{-- Set up the actions used by this page section. --}}
    <script>
        // Show scrollbar thumbs only while their surface is scrolling.
        (function () {
            var timers = new WeakMap();
            // Handle the scroll event.
            document.addEventListener('scroll', function (event) {
                var surface = event.target === document ? document.documentElement : event.target;
                // Return early if the value is missing or false.
                if (!(surface instanceof Element)) return;
                surface.classList.add('pfims-landing-scrolling');
                window.clearTimeout(timers.get(surface));
                // Run this action after the delay.
                timers.set(surface, window.setTimeout(function () {
                    surface.classList.remove('pfims-landing-scrolling');
                }, 700));
            }, { capture: true, passive: true });
        })();
    </script>
</body>
</html>
