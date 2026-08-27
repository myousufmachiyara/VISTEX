<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>BillTrix | Login</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/animate/animate.compat.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/boxicons/css/boxicons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/owl.carousel/assets/owl.carousel.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/owl.carousel/assets/owl.theme.default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/skins/default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />

    <script src="{{ asset('assets/vendor/modernizr/modernizr.js') }}"></script>

    <style>
        .resp-cont { width: 60%; }
        @media (max-width: 768px) { .resp-cont { width: 85%; } }

        .pw-wrap { position: relative; }
        .pw-wrap .form-control { padding-right: 2.5rem; }
        .pw-toggle {
            position: absolute; top: 50%; right: 10px;
            transform: translateY(-50%);
            background: none; border: none;
            padding: 0; cursor: pointer;
            color: #999; font-size: 14px; z-index: 5;
        }
        .pw-toggle:hover { color: #444; }
    </style>
</head>
<body style="background:#fff;">

    <div class="row g-0" style="min-height:100vh;">

        {{-- ── Login form column ──────────────────────────────── --}}
        <div class="col-12 col-md-5 d-flex align-items-center justify-content-center">
            <div class="resp-cont text-center">

                <h2 class="mb-0 text-primary">Welcome Back</h2>
                <p class="text-dark mb-4">Please login to continue</p>

                {{-- Validation / auth errors --}}
                @if($errors->any())
                    <div class="alert alert-danger text-center" style="border-radius:10px;">
                        {{-- FIX: shows the first error which covers both wrong credentials
                             AND the "account deactivated" message from LoginController --}}
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"
                                  style="border-radius:15px 0 0 15px;">
                                <i class="bx bx-user"></i>
                            </span>
                            <input class="form-control" required
                                   name="username"
                                   placeholder="Username"
                                   type="text"
                                   value="{{ old('username') }}"
                                   autocomplete="username"
                                   style="border-radius:0 15px 15px 0;" />
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="input-group pw-wrap">
                            <span class="input-group-text bg-light text-primary"
                                  style="border-radius:15px 0 0 15px;">
                                <i class="bx bx-lock"></i>
                            </span>
                            <input class="form-control" required
                                   name="password"
                                   placeholder="Password"
                                   type="password"
                                   id="loginPassword"
                                   autocomplete="current-password"
                                   style="border-radius:0 15px 15px 0; padding-right:2.5rem;" />
                            {{-- FIX: replaced onclick showPassword() with pw-toggle pattern
                                 consistent with app.blade.php and users/index.blade.php --}}
                            <button type="button" class="pw-toggle" tabindex="-1"
                                    onclick="toggleLoginPw(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-sm-12 text-start mb-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Your session is secure and encrypted.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"
                            style="border-radius:15px; font-size:0.95rem; padding:10px;">
                        Continue <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </form>

                <p class="text-center text-muted mt-4 mb-0">
                    &copy; {{ date('Y') }} BillTrix. All Rights Reserved.
                </p>
            </div>
        </div>

        {{-- ── Carousel image column ──────────────────────────── --}}
        <div class="col-md-7 d-none d-lg-block p-0">
            <div class="owl-carousel owl-theme mb-0"
                 data-plugin-carousel
                 data-plugin-options='{ "dots": false, "nav": false, "items": 1, "autoplay": true, "loop": true }'>
                <img src="{{ asset('assets/img/slide1.png') }}"
                     style="height:100vh; width:100%; object-fit:cover;" alt="">
            </div>
        </div>

    </div>

    {{-- Scripts — jQuery once, from vendor --}}
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/owl.carousel/owl.carousel.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}"></script>
    <script src="{{ asset('assets/js/custom.js') }}"></script>
    <script src="{{ asset('assets/js/theme.init.js') }}"></script>

    <script>
    function toggleLoginPw(btn) {
        var input = document.getElementById('loginPassword');
        var icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>

</body>
</html>