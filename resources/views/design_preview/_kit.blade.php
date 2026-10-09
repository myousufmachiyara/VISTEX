{{-- Shared look for the design-preview mockups. Static only: nothing on these pages is saved. --}}
<style>
  .dp-banner { background:#fff8e1; border:1px solid #f3d27a; color:#6b5200; border-radius:6px; padding:8px 14px; font-size:13px; margin-bottom:14px; display:flex; gap:10px; align-items:center; }
  .dp-banner i { font-size:16px; }
  .dp-section { border:1px solid #e6e8ec; border-radius:8px; background:#fff; margin-bottom:16px; }
  .dp-section > .dp-head { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; border-bottom:1px solid #eef0f3; background:#fafbfc; border-radius:8px 8px 0 0; }
  .dp-section > .dp-head h5 { margin:0; font-size:14px; font-weight:600; color:#2b3a4a; letter-spacing:.2px; }
  .dp-section > .dp-head h5 .dp-num { display:inline-block; width:22px; height:22px; line-height:22px; text-align:center; border-radius:50%; background:#6b8e4e; color:#fff; font-size:12px; margin-right:8px; }
  .dp-section > .dp-head small { color:#8a94a3; }
  .dp-section > .dp-body { padding:14px 16px 4px; }
  .dp-section label { font-size:12.5px; font-weight:600; color:#4a5565; margin-bottom:3px; }
  .dp-hint { font-size:11.5px; color:#8a94a3; }
  .dp-auto { background:#f3f6f0 !important; }
  .dp-chip { display:inline-block; padding:2px 9px; border-radius:12px; font-size:11.5px; font-weight:600; }
  .dp-chip.draft { background:#eef0f3; color:#55606e; }
  .dp-chip.sample { background:#fde2e2; color:#b42318; }
  .dp-chip.bulk { background:#e3f1e3; color:#2f6b2f; }
  .dp-table th { background:#6b8e4e; color:#fff; font-size:12px; font-weight:600; white-space:nowrap; vertical-align:middle; }
  .dp-table td { vertical-align:middle; font-size:13px; }
  .dp-table .form-control { height:32px; padding:3px 8px; font-size:13px; }
  .dp-table tfoot td { background:#f3f6f0; font-weight:700; }
  .dp-pattern { display:inline-block; background:#eef3ea; border:1px solid #d6e2cc; color:#3c5a28; border-radius:4px; padding:1px 6px; margin:1px 2px 1px 0; font-family:monospace; font-size:12px; white-space:nowrap; }
  .dp-summary { position:sticky; top:80px; }
  .dp-summary .dp-kv { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6e8ec; font-size:13px; }
  .dp-summary .dp-kv:last-child { border-bottom:0; }
  .dp-summary .dp-kv strong { color:#2b3a4a; }
  .dp-steps { list-style:none; padding:0; margin:0; }
  .dp-steps li { position:relative; padding:0 0 12px 22px; font-size:12.5px; color:#8a94a3; }
  .dp-steps li:before { content:''; position:absolute; left:4px; top:4px; width:10px; height:10px; border-radius:50%; border:2px solid #c5ccd6; background:#fff; }
  .dp-steps li:after { content:''; position:absolute; left:8px; top:15px; bottom:0; width:2px; background:#e6e8ec; }
  .dp-steps li:last-child:after { display:none; }
  .dp-steps li.done { color:#2b3a4a; font-weight:600; }
  .dp-steps li.done:before { border-color:#6b8e4e; background:#6b8e4e; }
  .dp-feedback { border-left:3px solid #f0ad4e; background:#fffaf2; padding:6px 10px; font-size:12px; color:#7a5a1e; margin:0 0 12px; border-radius:0 4px 4px 0; }
  .dp-feedback b { color:#5d4210; }
  .dp-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; }
  .dp-toast { position:fixed; right:20px; bottom:20px; z-index:2000; background:#2b3a4a; color:#fff; padding:10px 16px; border-radius:6px; font-size:13px; display:none; box-shadow:0 4px 14px rgba(0,0,0,.2); }
  .select2-container .select2-selection__choice { white-space:normal; max-width:100%; }
  @media (max-width: 991px) { .dp-summary { position:static; } }
</style>

<div class="dp-toast" id="dpToast"></div>
<script>
  // Every action button on a preview page just explains itself.
  function dpToast(msg) {
    const t = document.getElementById('dpToast');
    t.textContent = msg || 'Design preview — nothing is saved.';
    $(t).stop(true, true).fadeIn(150).delay(2200).fadeOut(300);
  }
  $(document).on('click', '[data-preview]', function (e) { e.preventDefault(); dpToast($(this).data('preview')); });
</script>
