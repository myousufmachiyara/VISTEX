<!DOCTYPE html>
<html lang="en" class="fixed js flexbox flexboxlegacy no-touch csstransforms csstransforms3d no-overflowscrolling webkit chrome win js no-mobile-device custom-scroll sidebar-left-collapsed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>@yield('title', 'MH Fabrics')</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">

    {{-- CSRF token — read by all AJAX calls via $.ajaxSetup in custom.js --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet">

    {{-- Vendor CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/animate/animate.compat.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/boxicons/css/boxicons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/magnific-popup/magnific-popup.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/media/css/dataTables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-multiselect/css/bootstrap-multiselect.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/dropzone/basic.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/dropzone/dropzone.css') }}" />

    {{-- Theme --}}
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/skins/default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />

    {{-- Page-specific styles pushed from child views via @push('styles') --}}
    @stack('styles')

    {{--
        FIX: jQuery MUST load in <head> — NOT in footer.
        ────────────────────────────────────────────────────────────────
        WHY: app.blade.php has inline <script> blocks in <body> that
        use jQuery ($). When jQuery was in footer.blade.php it loaded
        AFTER the body content, so the inline scripts at line 613
        threw "$ is not defined" before jQuery was available.

        Loading jQuery here in <head> guarantees it is available to:
          • The change-password modal script (bottom of body)
          • The $.ajaxSetup() call
          • Any @push('scripts') blocks in child views
          • custom.js which uses $ throughout

        footer.blade.php no longer loads jQuery — see that file.
        ────────────────────────────────────────────────────────────────
    --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>

    <style>
        /* ── Global loader ─────────────────────────────────────────── */
        #loader {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity .3s ease;
        }
        #loader.hidden { display: none; }

        /* ── Layout helpers ────────────────────────────────────────── */
        .cust-pad { padding-top: 0; }
        @media (min-width: 768px) {
            .cust-pad         { padding: 60px 10px 0 20px; }
            .home-cust-pad    { padding: 60px 15px 0 15px; }
            .sidebar-logo     { width: 60%; height: auto; padding-top: 5px; }
        }
        @media (max-width: 767px) {
            .sidebar-logo     { height: 40%; }
        }

        /* ── Password eye-toggle ───────────────────────────────────── */
        .pw-wrap              { position: relative; }
        .pw-wrap .form-control{ padding-right: 2.5rem; }
        .pw-toggle {
            position: absolute; top: 50%; right: 10px;
            transform: translateY(-50%);
            background: none; border: none;
            padding: 0; cursor: pointer;
            color: #999; font-size: 14px; line-height: 1; z-index: 5;
        }
        .pw-toggle:hover { color: #444; }
    </style>
</head>
<body>

    {{-- ── Page loader (hidden once DOM is ready) ──────────────────── --}}
    <div id="loader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading…</span>
        </div>
    </div>

    {{-- ── Change Password Modal ────────────────────────────────────── --}}
    <div id="changePassword" class="zoom-anim-dialog modal-block modal-block-danger mfp-hide">
        <section class="card">
            <form id="changePasswordForm" autocomplete="off"
                  onkeydown="return event.key !== 'Enter';">
                @csrf
                <header class="card-header">
                    <h2 class="card-title">Change Password</h2>
                </header>
                <div class="card-body">
                    <div id="cp-alert" class="alert d-none mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control"
                                   name="current_password" id="cp_current"
                                   placeholder="Current password"
                                   autocomplete="current-password" required>
                            <button type="button" class="pw-toggle" tabindex="-1"
                                    onclick="togglePw('cp_current', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control"
                                   name="new_password" id="cp_new"
                                   placeholder="New password (min 8 characters)"
                                   minlength="8" autocomplete="new-password" required>
                            <button type="button" class="pw-toggle" tabindex="-1"
                                    onclick="togglePw('cp_new', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Confirm New Password</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control"
                                   name="new_password_confirmation" id="cp_confirm"
                                   placeholder="Confirm new password"
                                   minlength="8" autocomplete="new-password" required>
                            <button type="button" class="pw-toggle" tabindex="-1"
                                    onclick="togglePw('cp_confirm', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <footer class="card-footer">
                    <div class="text-end">
                        <button type="button" id="cp-submit-btn" class="btn btn-primary">
                            Change Password
                        </button>
                        <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                    </div>
                </footer>
            </form>
        </section>
    </div>

    {{-- ── Top Header ───────────────────────────────────────────────── --}}
    <header class="page-header">

        {{-- Desktop header --}}
        <div class="logo-container d-none d-md-block">
            <div id="userbox" class="userbox" style="float:right">
                <a href="#" data-bs-toggle="dropdown" style="margin-right:20px">
                    <div class="profile-info">
                        <span class="name">{{ session('user_name') }}</span>
                        <span class="role">{{ session('role_name') }}</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled">
                        <li>
                            <a role="menuitem" tabindex="-1"
                               href="#changePassword"
                               class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal">
                                <i class="bx bx-lock"></i> Change Password
                            </a>
                        </li>
                        <li>
                            <form action="/logout" method="POST">
                                @csrf
                                <button style="background:transparent;border:none;font-size:14px;"
                                        type="submit" role="menuitem" tabindex="-1">
                                    <i class="bx bx-power-off"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Mobile header --}}
        <div class="logo-container d-md-none">
            <a href="/" class="logo">
                <img class="pt-2" src="{{ asset('assets/img/billtrix-logo-black.png') }}"
                     width="35%" alt="Logo" />
            </a>
            <div id="userbox-mobile" class="userbox" style="float:right">
                <a href="#" data-bs-toggle="dropdown" style="margin-right:20px">
                    <div class="profile-info">
                        <span class="name">{{ session('user_name') }}</span>
                        <span class="role">{{ session('role_name') }}</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled">
                        <li>
                            <a role="menuitem" tabindex="-1"
                               href="#changePassword"
                               class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal">
                                <i class="bx bx-lock"></i> Change Password
                            </a>
                        </li>
                    </ul>
                </div>
                <i class="fas fa-bars toggle-sidebar-left"
                   data-toggle-class="sidebar-left-opened"
                   data-target="html"
                   data-fire-event="sidebar-left-opened"
                   aria-label="Toggle sidebar"></i>
            </div>
        </div>

    </header>

    {{-- ── Main body ───────────────────────────────────────────────── --}}
    <section class="body">
        <div class="inner-wrapper cust-pad">
            @include('layouts.sidebar')
            <section role="main" class="content-body">
                @yield('content')
            </section>
        </div>
    </section>

    {{-- ── Footer ──────────────────────────────────────────────────── --}}
    <footer>
        @include('layouts.footer')
        <div class="text-end">
            <div>Powered By <a target="_blank" href="https://syitrix.com/">SyiTrix</a></div>
        </div>
    </footer>

    {{-- Page-specific scripts pushed from child views --}}
    @stack('scripts')

    <script>
    // ── 1. Hide loader once page fully loaded ───────────────────────
    window.addEventListener('load', function () {
        var loader = document.getElementById('loader');
        if (loader) { loader.classList.add('hidden'); }
    });

    // ── 2. Global AJAX CSRF setup ───────────────────────────────────
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // ── 3. Password eye-toggle ──────────────────────────────────────
    function togglePw(fieldId, btn) {
        var input = document.getElementById(fieldId);
        var icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // ── 4. Change Password ──────────────────────────────────────────
    (function () {
        var form    = document.getElementById('changePasswordForm');
        var alertEl = document.getElementById('cp-alert');
        var btn     = document.getElementById('cp-submit-btn');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }, true);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            if (btn.dataset.submitting === '1') return;

            var newPw  = document.getElementById('cp_new').value;
            var confPw = document.getElementById('cp_confirm').value;

            function showError(msg) {
                alertEl.className   = 'alert alert-danger';
                alertEl.textContent = msg;
            }

            if (newPw.length < 8) {
                return showError('New password must be at least 8 characters.');
            }
            if (newPw !== confPw) {
                return showError('Passwords do not match.');
            }

            btn.dataset.submitting = '1';
            btn.disabled           = true;
            btn.textContent        = 'Saving…';
            alertEl.className      = 'alert d-none';

            var payload = new FormData();
            payload.append('_token',                    document.querySelector('meta[name="csrf-token"]').content);
            payload.append('current_password',          document.getElementById('cp_current').value);
            payload.append('new_password',              newPw);
            payload.append('new_password_confirmation', confPw);

            fetch('/change-my-password', {
                method : 'POST',
                headers: {
                    'Accept'           : 'application/json',
                    'X-Requested-With' : 'XMLHttpRequest',
                    'X-CSRF-TOKEN'     : document.querySelector('meta[name="csrf-token"]').content,
                },
                body: payload,
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    alertEl.className   = 'alert alert-success';
                    alertEl.textContent = data.message || 'Password changed successfully.';
                    ['cp_current', 'cp_new', 'cp_confirm'].forEach(function (id) {
                        var el = document.getElementById(id);
                        el.value = '';
                        el.type  = 'password';
                    });
                    form.querySelectorAll('.pw-toggle i').forEach(function (icon) {
                        icon.className = 'fas fa-eye';
                    });
                    setTimeout(function () {
                        if (typeof $.magnificPopup !== 'undefined') {
                            $.magnificPopup.close();
                        }
                        alertEl.className = 'alert d-none';
                    }, 1500);
                } else {
                    var msgs = [];
                    if (data.errors) {
                        msgs = Object.values(data.errors).flat();
                    } else if (data.message) {
                        msgs = [data.message];
                    }
                    alertEl.className   = 'alert alert-danger';
                    alertEl.textContent = msgs.join(' ') || 'Something went wrong.';
                }
            })
            .catch(function () {
                alertEl.className   = 'alert alert-danger';
                alertEl.textContent = 'Network error. Please try again.';
            })
            .finally(function () {
                btn.disabled           = false;
                btn.textContent        = 'Change Password';
                btn.dataset.submitting = '0';
            });
        });
    })();
    </script>

</body>
</html>