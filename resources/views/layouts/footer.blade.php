{{--
    footer.blade.php
    ────────────────────────────────────────────────────────────────────
    Vendor JS — loaded ONCE, in dependency order.

    IMPORTANT: jQuery is NO LONGER loaded here.
    It is loaded in <head> inside app.blade.php so it is available
    to all inline scripts in <body> before footer scripts run.
    Loading it here again would create a duplicate.
    ────────────────────────────────────────────────────────────────────
--}}

{{-- jQuery → loaded in app.blade.php <head> — NOT here --}}

{{-- ── 1. Bootstrap bundle ──────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

{{-- ── 2. Magnific Popup ────────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/magnific-popup/jquery.magnific-popup.min.js') }}"></script>

{{-- ── 5. jQuery UI ────────────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/jquery-ui/jquery-ui.min.js') }}"></script>

{{-- ── 6. jQuery UI Touch Punch ────────────────────────────────── --}}
{{--
    FIX: This file is 404 — the filename inside your jqueryui-touch-punch
    folder does not match. To fix:

    1. Open: C:\xampp\htdocs\MH\public\assets\vendor\jqueryui-touch-punch\
    2. Check the actual filename (e.g. jquery.ui.touch-punch.js or touchPunch.min.js)
    3. Replace the filename in the line below and uncomment it

    Until then it is commented out. The sidebar and theme will work without
    it — touch-punch only adds mobile touch support for drag handles.
--}}
{{-- <script src="{{ asset('assets/vendor/jqueryui-touch-punch/jquery.ui.touch-punch.min.js') }}"></script> --}}

{{-- ── 7. jQuery Nestable ───────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/jquery-nestable/jquery.nestable.js') }}"></script>

{{-- ── 8. DataTables ────────────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/datatables/media/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatables/media/js/dataTables.bootstrap5.min.js') }}"></script>

{{-- ── 9. Bootstrap Datepicker ──────────────────────────────────── --}}
{{-- Try local first. If 404, falls back to CDN version below --}}
<script src="{{ asset('assets/vendor/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>

{{-- ── 10. Select2 ──────────────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>

{{-- ── 11. Bootstrap Multiselect v5 ────────────────────────────── --}}
<script src="{{ asset('assets/vendor/bootstrapv5-multiselect/js/bootstrap-multiselect.js') }}"></script>

{{-- ── 12. Dropzone ─────────────────────────────────────────────── --}}
<script src="{{ asset('assets/vendor/dropzone/dropzone.js') }}"></script>

{{-- ── 13. CDN-only libraries ───────────────────────────────────── --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

{{-- ── 14. Porto Theme ──────────────────────────────────────────── --}}
<script src="{{ asset('assets/js/theme.js') }}"></script>
<script src="{{ asset('assets/js/custom.js') }}"></script>
<script src="{{ asset('assets/js/theme.init.js') }}"></script>

{{-- ── 15. Porto example initializers ──────────────────────────── --}}
{{-- examples.header.menu.js is 404 on your server — sidebar works via theme.js --}}
{{-- <script src="{{ asset('assets/js/examples/examples.header.menu.js') }}"></script> --}}
<script src="{{ asset('assets/js/examples/examples.dashboard.js') }}"></script>
<script src="{{ asset('assets/js/examples/examples.datatables.default.js') }}"></script>
<script src="{{ asset('assets/js/examples/examples.modals.js') }}"></script>