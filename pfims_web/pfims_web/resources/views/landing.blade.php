<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>
    
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>

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

        </div>

    </div>

</body>
</html>